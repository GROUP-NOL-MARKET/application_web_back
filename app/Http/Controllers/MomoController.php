<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Events\OrderPaid;
use Illuminate\Support\Facades\DB;

class MomoController extends Controller
{
    protected $base;
    protected $subscriptionKey;
    protected $apiUser;
    protected $apiKey;
    protected $targetEnv;
    protected $callbackUrl;
    protected $currency;

    public function __construct()
    {
        $this->base = env('MOMO_API_BASE');
        $this->subscriptionKey = env('MOMO_SUBSCRIPTION_KEY');
        $this->apiUser = env('MOMO_API_USER');
        $this->apiKey = env('MOMO_API_KEY');
        $this->targetEnv = env('MOMO_TARGET_ENV', 'sandbox');
        $this->callbackUrl = env('MOMO_CALLBACK_URL');
        $this->currency = ($this->targetEnv === 'sandbox') ? env('MOMO_CURRENCY_SANDBOX', 'EUR') : env('MOMO_CURRENCY_PROD', 'XOF');
    }

    /**
     * Get access token (OAuth)
     */
    protected function getAccessToken()
    {
        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        ])->withBasicAuth($this->apiUser, $this->apiKey)
            ->post(rtrim($this->base, '/') . '/collection/token/');

        if ($response->failed()) {
            Log::error('Momo token error', ['body' => $response->body()]);
            throw new \Exception('Impossible d\'obtenir le token MoMo');
        }

        return $response->json()['access_token'];
    }

    /**
     * Create payment (init RequestToPay)
     * - ne met pas la commande en approved : reste pending
     */
    public function createPayment(Request $req)
    {
        $req->validate([
            'amount' => 'required|numeric|min:1',
            'partyId' => 'required|string'
        ]);

        $user = JWTAuth::parseToken()->authenticate();
        $reference = 'ORD-' . Str::upper(Str::random(8));
        $transactionId = (string) Str::uuid();

        DB::beginTransaction();
        try {
            $order = Order::create([
                'user_id'   => $user->id,
                'total'     => $req->amount,
                'currency'  => $this->currency,
                'reference' => $reference,
                'status'    => 'pending',
            ]);

            // SAUVEGARDE DU NUMÉRO ICI
            $payment = $order->payment()->create([
                'transaction_id' => $transactionId,
                'amount'         => $req->amount,
                'status'         => 'pending',
                'phone'          => $req->partyId,
                'meta'           => null,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Order creation failed', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => 'Impossible de créer la commande'], 500);
        }

        Log::info("INIT MoMo → Numéro: {$req->partyId}, Réf: {$reference}, Montant: {$req->amount}");


        // Prepare payload
        $amountString = number_format($req->amount, 2, '.', '');
        $payload = [
            'amount' => $amountString,
            'currency' => $this->currency,
            'externalId' => $reference,
            'payer' => [
                'partyIdType' => 'MSISDN',
                'partyId' => $req->partyId,
            ],
            'payerMessage' => "Payment $reference",
            'payeeNote' => "Order $reference"
        ];

        try {
            $token = $this->getAccessToken();

            $headers = [
                'Authorization' => 'Bearer ' . $token,
                'X-Reference-Id' => $transactionId,
                'X-Target-Environment' => $this->targetEnv,
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                'Content-Type' => 'application/json'
            ];

            // If you have a callback URL, include X-Callback-Url (recommended)
            if (!empty($this->callbackUrl)) {
                $headers['X-Callback-Url'] = $this->callbackUrl;
            }

            $response = Http::withHeaders($headers)
                ->post(rtrim($this->base, '/') . '/collection/v1_0/requesttopay', $payload);

            // 201 Created -> request accepted. We must still wait for callback/status.
            if ($response->status() >= 200 && $response->status() < 300) {
                // keep order pending; payment pending
                Log::info('requestToPay initiated', ['tx' => $transactionId, 'order' => $order->id, 'resp_code' => $response->status()]);
                return response()->json([
                    'ok' => true,
                    'reference' => $reference,
                    'transaction_id' => $transactionId,
                    'message' => 'Paiement initié, en attente de confirmation.'
                ], 201);
            } else {
                // API returned error - mark failed
                $order->update(['status' => 'failed']);
                $payment->update(['status' => 'failed', 'meta' => $response->body()]);
                Log::error('requestToPay failed', ['resp' => $response->body()]);
                return response()->json(['ok' => false, 'error' => 'Échec init paiement', 'details' => $response->body()], 500);
            }
        } catch (\Throwable $e) {
            Log::error('Momo createPayment exception', ['error' => $e->getMessage()]);
            $order->update(['status' => 'failed']);
            $payment->update(['status' => 'failed']);
            return response()->json(['ok' => false, 'error' => 'Erreur serveur'], 500);
        }
    }

    /**
     * Polling (optionnel fallback if callbacks fail)
     */
    public function getStatus($reference)
    {
        $order = Order::where('reference', $reference)->firstOrFail();
        $payment = $order->payment;

        if (!$payment || !$payment->transaction_id) {
            return response()->json(['status' => 'unknown']);
        }

        try {
            $token = $this->getAccessToken();
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'X-Target-Environment' => $this->targetEnv,
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            ])->get(rtrim($this->base, '/') . "/collection/v1_0/requesttopay/{$payment->transaction_id}");

            if ($response->failed()) {
                Log::warning('Momo polling failed', ['resp' => $response->body()]);
                return response()->json(['status' => 'error', 'meta' => $response->body()], 500);
            }

            $data = $response->json();
            $statusRaw = isset($data['status']) ? strtolower($data['status']) : null;

            // map statuses
            if (in_array($statusRaw, ['success', 'successful', 'completed'])) {
                if ($order->status !== 'approved') {
                    $order->update(['status' => 'approved']);
                    $payment->update(['status' => 'approved', 'meta' => $data]);
                    event(new OrderPaid($order));
                }
            } elseif (in_array($statusRaw, ['failed', 'declined', 'rejected'])) {
                // extract reason if present
                $reason = $data['reason'] ?? ($data['message'] ?? null);

                $order->update(['status' => 'declined']);
                $payment->update(['status' => 'declined', 'meta' => $data]);

                Log::info('Payment declined/polling', ['reason' => $reason, 'order' => $order->id]);
            } else {
                // pending etc.
                $payment->update(['meta' => $data]);
            }

            return response()->json(['reference' => $reference, 'status' => $payment->status, 'meta' => $data]);
        } catch (\Throwable $e) {
            Log::error('Momo getStatus exception', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Webhook endpoint to receive callback from MoMo
     * - route -> POST /momo/webhook
     */
    public function webhook(Request $req)
    {
        // Save raw body
        $raw = $req->getContent();
        Log::info('Momo webhook received', ['headers' => $req->headers->all(), 'body' => $raw]);

        // Basic validation: check subscription key / origin headers if needed
        // Optionally verify a signature if provider sends one (check docs)
        $payload = $req->json()->all();

        // Example expected payload shape may include transactionId/reference and status
        // The exact structure depends on provider; adapt accordingly
        $reference = $payload['externalId'] ?? $payload['reference'] ?? ($req->header('X-Reference-Id') ?? null);
        $statusRaw = isset($payload['status']) ? strtolower($payload['status']) : (isset($payload['result']['code']) ? $payload['result']['code'] : null);

        if (!$reference) {
            Log::warning('Webhook missing reference', ['payload' => $payload]);
            return response()->json(['ok' => false], 400);
        }

        $order = Order::where('reference', $reference)->first();

        if (!$order) {
            Log::warning('Webhook reference not found', ['reference' => $reference, 'payload' => $payload]);
            return response()->json(['ok' => false], 404);
        }

        $payment = $order->payment;
        // If already final, return 200 (idempotence)
        if (in_array($order->status, ['approved', 'declined', 'cancelled'])) {
            Log::info('Webhook ignored - already final', ['order' => $order->id, 'status' => $order->status]);
            return response()->json(['ok' => true]);
        }

        // Decide status based on payload - adapt this logic to the exact webhook body from MoMo
        if (in_array($statusRaw, ['success', 'successful', 'completed'])) {
            $order->update(['status' => 'approved']);
            $payment->update(['status' => 'approved', 'meta' => $payload]);
            event(new OrderPaid($order));
            return response()->json(['ok' => true], 200);
        }

        // Some providers include detailed 'reason' -> check for insufficient funds tokens
        $reason = $payload['reason'] ?? $payload['message'] ?? null;
        if ($statusRaw && in_array($statusRaw, ['failed', 'declined', 'rejected']) || ($reason && stripos($reason, 'insufficient') !== false)) {
            // Cancel order and free resources
            $order->update(['status' => 'declined']);
            $payment->update(['status' => 'declined', 'meta' => $payload]);
            // If you want to mark explicitly cancelled:
            // $order->update(['status' => 'cancelled']);
            Log::info('Payment failed or insufficient funds', ['order' => $order->id, 'reason' => $reason]);
            return response()->json(['ok' => true], 200);
        }

        // default: keep pending and store meta
        $payment->update(['meta' => $payload]);
        return response()->json(['ok' => true], 202);
    }
}
