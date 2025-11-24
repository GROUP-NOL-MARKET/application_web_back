<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Events\OrderPaid;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class FedapayController extends Controller
{
    protected $secret;
    protected $apiBase;
    protected $mode;

    public function __construct()
    {
        $this->secret = config('app.fedapay_secret', env('FEDAPAY_SECRET_KEY'));
        $this->apiBase = env('FEDAPAY_API_BASE', 'https://sandbox-api.fedapay.com/v1'); // ✔ FIX
        $this->mode = env('FEDAPAY_MODE', 'sandbox');
    }


    public function createPayment(Request $req)
    {
        $req->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        // ✔ JWT Auth correct
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['user' => false, 'error' => 'Not authenticated'], 401);
        }

        $reference = 'ORD-' . Str::upper(Str::random(8));
        $amount = (int) round($req->amount); // ✔ montant entier

        DB::beginTransaction();
        try {

            $order = Order::create([
                'user_id'   => $user->id,
                'total'     => $amount,
                'reference' => $reference,
                'status'    => 'pending',
            ]);

            $payment = $order->payment()->create([
                'transaction_id' => null,
                'amount' => $amount,
                'status' => 'pending',
                'phone'  => $user->phone ?? '',
                'meta'   => null,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Order creation failed', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => 'Impossible de créer la commande'], 500);
        }


        $txPayload = [
            'description' => "Order {$reference}",
            'amount' => $amount,
            'currency' => [
                'iso' => 'XOF'
            ],
            'callback_url' => route('fedapay.webhook'),
            'customer' => [
                'first_name' => $user->firstName ?? $user->name ?? 'Client',
                'last_name'  => $user->lastName ?? '',
                'email'      => $user->email ?? '',
                'phone'      => $user->phone ?? ''
            ],
            'metadata' => [
                'order_reference' => $reference,
                'local_order_id' => $order->id
            ]
        ];

        try {

            // ✔ URL correcte
            $createResp = Http::withToken($this->secret)
                ->post("{$this->apiBase}/transactions", $txPayload);

            if ($createResp->failed()) {
                $order->update(['status' => 'failed']);
                $payment->update(['status' => 'failed', 'meta' => $createResp->body()]);
                Log::error('FedaPay create transaction failed', ['resp' => $createResp->body()]);
                return response()->json(['ok' => false, 'error' => 'Échec création transaction FedaPay'], 500);
            }

            $txData = $createResp->json();
            $txId = $txData['id'] ?? null;

            if (!$txId) {
                throw new \Exception("Missing transaction id from FedaPay");
            }

            // Save transaction
            $payment->update(['transaction_id' => $txId]);

            // ✔ Checkout URL
            $tokenResp = Http::withToken($this->secret)
                ->post("{$this->apiBase}/transactions/{$txId}/token");

            if ($tokenResp->failed()) {
                Log::error('FedaPay get token failed', ['resp' => $tokenResp->body()]);
                return response()->json(['ok' => false, 'error' => 'Échec génération lien paiement'], 500);
            }

            $tokenData = $tokenResp->json();
            $checkoutUrl = $tokenData['url'] ?? ($tokenData['data']['url'] ?? null);

            return response()->json([
                'ok' => true,
                'checkout_url' => $checkoutUrl,
                'transaction_id' => $txId,
                'reference' => $reference
            ], 201);
        } catch (\Throwable $e) {
            Log::error('FedaPay error', ['error' => $e->getMessage()]);
            $order->update(['status' => 'failed']);
            $payment->update(['status' => 'failed']);
            return response()->json(['ok' => false, 'error' => 'Erreur serveur'], 500);
        }
    }




    /* Webhook identique à ton code, pas de correction nécessaire */

    /**
     * Webhook endpoint to receive FedaPay notifications.
     * Update order/payment status and dispatch your OrderPaid event.
     */
    public function webhook(Request $req)
    {
        $raw = $req->getContent();
        Log::info('Fedapay webhook received', ['headers' => $req->headers->all(), 'body' => $raw]);

        $payload = $req->json()->all();

        // FedaPay envoie les informations d'événement -> cherche transaction id ou metadata
        $txId = $payload['data']['id'] ?? $payload['id'] ?? null;
        $status = strtolower($payload['event'] ?? ($payload['data']['status'] ?? ''));

        // try to find order via metadata or transaction id saved earlier
        $order = null;
        if (isset($payload['data']['metadata']['order_reference'])) {
            $order = Order::where('reference', $payload['data']['metadata']['order_reference'])->first();
        }
        if (!$order && $txId) {
            $order = Order::whereHas('payment', function ($q) use ($txId) {
                $q->where('transaction_id', $txId);
            })->first();
        }

        if (!$order) {
            Log::warning('Fedapay webhook: order not found', ['payload' => $payload]);
            return response()->json(['ok' => false], 404);
        }

        $payment = $order->payment;
        // handle final statuses: 'paid', 'canceled', 'refused' etc (adapte selon payload)
        // Exemple (à adapter si FedaPay retourne d'autres valeurs)
        if (strpos($status, 'paid') !== false || strpos($status, 'success') !== false || $payload['data']['status'] === 'paid') {
            if ($order->status !== 'approved') {
                $order->update(['status' => 'approved']);
                $payment->update(['status' => 'approved', 'meta' => $payload]);
                event(new OrderPaid($order));
            }
            return response()->json(['ok' => true], 200);
        }

        if (strpos($status, 'cancel') !== false || strpos($status, 'refused') !== false || ($payload['data']['status'] ?? '') === 'refused') {
            $order->update(['status' => 'declined']);
            $payment->update(['status' => 'declined', 'meta' => $payload]);
            Log::info('Fedapay payment declined', ['order' => $order->id, 'payload' => $payload]);
            return response()->json(['ok' => true], 200);
        }

        // otherwise store metadata and keep pending
        $payment->update(['meta' => $payload]);
        return response()->json(['ok' => true], 202);
    }
}
