<?php

namespace App\Http\Controllers;

use App\Models\Order;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use FedaPay\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function createTransaction(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100',
            'description' => 'nullable|string',
            'email' => 'required|email',
            'firstName' => 'required|string',
        ]);

        try {
            Log::info('FedaPay Key: ' . env('FEDAPAY_SECRET_KEY'));
            //  Configuration FedaPay
            FedaPay::setApiKey(env('FEDAPAY_SECRET_KEY'));
            FedaPay::setEnvironment(config('fedapay.mode'));

            //  Création (ou récupération) du client
            try {
                $customer = Customer::create([
                    'email' => $validated['email'],
                    'firstname' => $validated['firstName'],
                    'lastname' => $validated['firstName'],
                ]);
            } catch (\FedaPay\Error\Base $e) {
                $body = $e->getJsonBody();
                Log::error("Erreur FedaPay: " . $e->getMessage());
                Log::error("Détails: " . json_encode($body));

                // Si c’est une erreur d’email déjà existant
                if (isset($body['errors']['email'])) {
                    $customers = Customer::all();
                    $customer = collect($customers)->firstWhere('email', $validated['email']);
                    if (!$customer) {
                        throw new \Exception("Impossible de récupérer le client existant");
                    }
                    Log::info("Client existant récupéré: {$customer->id}");
                } else {
                    throw $e;
                }
            }


            //  Création de la transaction
            $transaction = Transaction::create([
                'description' => $validated['description'] ?? 'Paiement commande',
                'amount' => $validated['amount'],
                'currency' => ['iso' => 'XOF'],
                'callback_url' => route('fedapay.callback'),
                'customer' => ['id' => $customer->id],
            ]);

            //  Enregistre la commande localement
            $order = Order::create([
                'user_id' => 1,
                'user_email' => $validated['email'],
                'transaction_id' => $transaction->id,
                'status' => 'en attente',
                'total' => $validated['amount'],
            ]);

            //  Retour au frontend
            return response()->json([
                'transaction_id' => $transaction->id,
                'url' => $transaction->generateToken()->url,
                'order_id' => $order->id,
                'customer_id' => $customer->id,
            ]);
        } catch (\FedaPay\Error\Base $e) {
            Log::error("Erreur FedaPay: " . $e->getMessage());
            Log::error("Détails: " . json_encode($e->getJsonBody()));
            return response()->json(['error' => 'Impossible de créer la transaction'], 500);
        }
    }
    public function webhook(Request $request)
    {
        try {
            $data = $request->json()->all();

            $eventName = $data['name'] ?? null;
            $entity = $data['entity'] ?? null;

            if (!$eventName || !$entity) {
                Log::error('Webhook FedaPay: Données incomplètes', $data);
                return response()->json(['error' => 'Format invalide'], 400);
            }

            $transactionId = $entity['id'];
            $status = $entity['status']; // pending, approved, canceled...

            // Recherche de la commande correspondante
            $order = Order::where('transaction_id', $transactionId)->first();

            if (!$order) {
                Log::warning("Aucune commande trouvée pour la transaction $transactionId");
                return response()->json(['message' => 'Commande introuvable'], 200);
            }

            // Mise à jour du statut selon l'événement reçu
            switch ($eventName) {
                case 'transaction.created':
                    $order->status = 'en attente';
                    break;

                case 'transaction.approved':
                    $order->status = 'validée';
                    break;

                case 'transaction.declined':
                case 'transaction.canceled':
                    $order->status = 'annulée';
                    break;

                case 'transaction.transferred':
                    $order->status = 'transférée';
                    break;

                default:
                    Log::info("Événement ignoré : $eventName");
                    break;
            }

            $order->save();

            Log::info("Webhook traité avec succès pour transaction $transactionId — statut: {$order->status}");

            // Toujours répondre 2xx sinon FedaPay retente
            return response()->json(['status' => 'ok'], 200);
        } catch (\Exception $e) {
            Log::error("Erreur webhook FedaPay: " . $e->getMessage());
            return response()->json(['error' => 'Erreur interne'], 500);
        }
    }
}