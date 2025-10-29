<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use FedaPay\Customer;

class PaymentController extends Controller
{
    /**
     * Crée une transaction FedaPay et une commande locale.
     * Attendu du front: { amount, description, email, firstName, produits }.
     */
    public function createTransaction(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100',
            'description' => 'nullable|string|max:255',
            'email' => 'required|email',
            'firstName' => 'required|string|max:120',
            'produits' => 'nullable|array', // tableau d'objets {id, name, price, quantite}
        ]);

        try {
            // ---------- 1) Init SDK ----------
            FedaPay::setApiKey(env('FEDAPAY_SECRET_KEY'));
            // config('fedapay.mode') attend 'sandbox' ou 'live'
            FedaPay::setEnvironment(config('fedapay.mode', 'sandbox'));

            // ---------- 2) Rechercher client local (recommencé) ----------
            // OPTION RECOMMANDÉE: tu dois idéalement avoir une table locale liant user/email -> fedapay_id
            // Si tu as un modèle LocalFedapayCustomer, utilise-le pour retrouver fedapay_id.
            $fedapayCustomer = null;
            $localCustomer = null;

            // Si l'utilisateur est authentifié, chercher dans users (exemple)
            if ($request->user()) {
                //  stockes fedapay_id sur users: $request->user()->fedapay_id
                // Adaptation: chercher dans users->fedapay_id si existant
                if (isset($request->user()->fedapay_id) && $request->user()->fedapay_id) {
                    try {
                        $fedapayCustomer = Customer::retrieve($request->user()->fedapay_id);
                    } catch (\Exception $e) {
                        Log::warning("Impossible de récupérer customer FedaPay depuis user->fedapay_id: " . $e->getMessage());
                    }
                }
            }

            // Si pas trouvé via local, on tente de retrouver parmi les clients FedaPay (fallback)
            if (!$fedapayCustomer) {
                // ATTENTION: Customer::where() n'existe pas dans le SDK
                // On récupère tous les clients puis on filtre côté serveur (bon pour faible volume)
                try {
                    $customers = Customer::all();
                    $existing = collect($customers)->firstWhere('email', $validated['email']);
                    if ($existing) {
                        $fedapayCustomer = $existing;
                    }
                } catch (\Exception $e) {
                    Log::warning("Impossible de lister les customers FedaPay: " . $e->getMessage());
                }
            }

            // Si toujours rien, on crée le customer chez FedaPay
            if (!$fedapayCustomer) {
                try {
                    $fedapayCustomer = Customer::create([
                        'email' => $validated['email'],
                        'firstname' => $validated['firstName'],
                        'lastname' => $validated['firstName'],
                    ]);
                    Log::info("Client FedaPay créé: {$fedapayCustomer->id}");
                    // sauvegarde fedapay_id localement si tu as une table pour ça
                } catch (\FedaPay\Error\Base $e) {
                    // Si erreur "email already exists" (ou autre) : tenter de récupérer
                    $body = $e->getJsonBody() ?? [];
                    $message = strtolower($body['message'] ?? $e->getMessage());
                    if (str_contains($message, 'email') && str_contains($message, 'exists')) {
                        // fallback: récupérer tous et filtrer
                        $customers = Customer::all();
                        $existing = collect($customers)->firstWhere('email', $validated['email']);
                        if ($existing) {
                            $fedapayCustomer = $existing;
                        } else {
                            Log::error("Erreur création customer: email existant mais non retrouvé dans la liste");
                            throw $e;
                        }
                    } else {
                        Log::error("Erreur FedaPay lors création client: " . $e->getMessage());
                        throw $e;
                    }
                }
            }

            if (!$fedapayCustomer || !isset($fedapayCustomer->id)) {
                Log::error("Impossible d'obtenir un customer FedaPay valide");
                return response()->json(['error' => 'Impossible d’obtenir le client FedaPay'], 500);
            }

            // ---------- 3) Création de la commande locale (avant la transaction) ----------
            $order = new Order();
            $order->user_id = $request->user()->id ?? null;

            $order->produits = isset($validated['produits']) ? json_encode($validated['produits']) : null;
            $order->total = $validated['amount'];
            $order->status = 'pending'; // statut initial
            $order->save();

            // Si tu as une relation items() et un modèle OrderItem, tu peux créer les items
            if (method_exists($order, 'items') && !empty($validated['produits'])) {
                try {
                    $items = array_map(function ($p) {
                        return [
                            'product_id' => $p['id'] ?? null,
                            'name' => $p['name'] ?? null,
                            'quantity' => $p['quantite'] ?? ($p['quantity'] ?? 1),
                            'price' => $p['price'] ?? 0,
                            'total' => (($p['price'] ?? 0) * ($p['quantite'] ?? ($p['quantity'] ?? 1))),
                        ];
                    }, $validated['produits']);
                    // createMany attend un array d'array
                    $order->items()->createMany($items);
                } catch (\Exception $e) {
                    Log::warning("Impossible de créer order items: " . $e->getMessage());
                }
            }

            // ---------- 4) Création de la transaction FedaPay ----------
            // S'ASSURER que le montant correspond exactement à la commande
            $amount = (int) $validated['amount']; // FedaPay attend un entier en centimes? (selon config) — vérifier doc
            if ($amount <= 0) {
                Log::error("Montant invalide fourni pour la transaction: {$amount}");
                return response()->json(['error' => 'Montant invalide'], 422);
            }

            $transaction = Transaction::create([
                'description' => $validated['description'] ?? "Paiement commande #{$order->id}",
                'amount' => $amount,
                'currency' => ['iso' => 'XOF'],
                'callback_url' => route('payment.webhook'),
                'customer' => ['id' => $fedapayCustomer->id],
            ]);

            // Génération du token (url pour le widget)
            $token = $transaction->generateToken();

            // Sauvegarder l'id de transaction FedaPay sur la commande locale
            $order->transaction_id = $transaction->id;
            $order->save();

            // ---------- 5) Réponse front ----------
            return response()->json([
                'payment_url' => $token->url ?? null,
                'order_id' => $order->id,
                'transaction_id' => $transaction->id,
            ]);
        } catch (\FedaPay\Error\Base $e) {
            Log::error("FedaPay Error: " . $e->getMessage(), ['body' => $e->getJsonBody() ?? null]);
            return response()->json(['error' => 'Erreur FedaPay lors de la création du paiement.'], 500);
        } catch (\Exception $e) {
            Log::error("Erreur interne createTransaction: " . $e->getMessage());
            return response()->json(['error' => 'Erreur interne serveur.'], 500);
        }
    }

    /**
     * Webhook endpoint pour FedaPay.
     */
    public function webhook(Request $request)
    {
        try {
            $payload = $request->json()->all();
            Log::info('Webhook reçu FedaPay', $payload);

            // Structure typique: ['name' => 'transaction.approved', 'entity' => [...]]
            $eventName = $payload['name'] ?? null;
            $entity = $payload['entity'] ?? null;

            if (!$eventName || !$entity) {
                Log::error('Webhook FedaPay: données manquantes', $payload);
                return response()->json(['error' => 'Webhook invalide'], 400);
            }

            $transactionId = $entity['id'] ?? null;
            $status = $entity['status'] ?? null;

            if (!$transactionId) {
                Log::error('Webhook FedaPay: transaction id absent', $payload);
                return response()->json(['error' => 'transaction id absent'], 400);
            }

            $order = Order::where('transaction_id', $transactionId)->first();
            if (!$order) {
                Log::warning("Webhook FedaPay: commande introuvable pour transaction {$transactionId}");
                return response()->json(['message' => 'Commande introuvable'], 200);
            }

            // Mapping propre des statuts FedaPay -> statut local
            $statusMap = [
                'pending'    => 'en_attente',
                'created'    => 'en_attente',
                'approved'   => 'validée',
                'declined'   => 'annulée',
                'canceled'   => 'annulée',
                'transferred' => 'transférée',
            ];

            $newStatus = $statusMap[$status] ?? $order->status;
            $order->status = $newStatus;

            // Optionnel: stocker payload complet pour futur audit
            $order->raw_webhook_payload = json_encode($payload);
            $order->save();

            Log::info("Webhook FedaPay traité: {$eventName} pour commande #{$order->id}, statut => {$order->status}");

            // Toujours répondre 2xx pour arrêter les retries FedaPay
            return response()->json(['ok' => true], 200);
        } catch (\Exception $e) {
            Log::error("Erreur webhook FedaPay: " . $e->getMessage());
            return response()->json(['error' => 'Erreur interne'], 500);
        }
    }
}