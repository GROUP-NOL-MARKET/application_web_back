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
use Illuminate\Http\Client\Response;

class FedapayController extends Controller
{
    protected $secret;
    protected $apiBase;
    protected $frontendUrl;

    public function __construct()
    {
        $this->secret = env('FEDAPAY_SECRET_KEY');
        $this->apiBase = rtrim(env('FEDAPAY_API_BASE', 'https://sandbox-api.fedapay.com/v1'), '/');
        $this->frontendUrl = rtrim(env('APP_FRONTEND_URL', ''), '/');
    }

    protected function sendFedapayRequest(string $method, string $path, $payload = null): Response
    {
        if (empty($this->secret)) {
            throw new \RuntimeException('FEDAPAY_SECRET_KEY is not set in environment.');
        }

        $url = "{$this->apiBase}{$path}";

        $commonHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $headersToken = array_merge($commonHeaders, ['Authorization' => 'Token ' . $this->secret]);
        Log::info('Fedapay sending request (Token header)', ['method' => $method, 'url' => $url]);

        $req = Http::withHeaders($headersToken);
        $resp = $this->dispatchHttp($req, $method, $url, $payload);

        if ($resp->status() === 401) {
            Log::warning('Fedapay Token auth got 401, retrying with Bearer', ['url' => $url]);
            $headersBearer = array_merge($commonHeaders, ['Authorization' => 'Bearer ' . $this->secret]);
            $req2 = Http::withHeaders($headersBearer);
            $resp2 = $this->dispatchHttp($req2, $method, $url, $payload);
            return $resp2;
        }

        return $resp;
    }

    protected function dispatchHttp($httpClient, string $method, string $url, $payload = null): Response
    {
        $method = strtolower($method);
        if ($method === 'post') {
            return $httpClient->post($url, $payload);
        } elseif ($method === 'get') {
            // pass query params if payload is an array
            if (is_array($payload) && !empty($payload)) {
                return $httpClient->get($url, $payload);
            }
            return $httpClient->get($url);
        } elseif ($method === 'put') {
            return $httpClient->put($url, $payload);
        } elseif ($method === 'delete') {
            return $httpClient->delete($url);
        } else {
            throw new \InvalidArgumentException("Unsupported HTTP method: {$method}");
        }
    }

    /**
     * Create transaction and return checkout URL
     */
    public function createTransaction(Request $req)
    {
        $req->validate([
            'amount' => 'required|numeric|min:1',
            'products' => 'required|array|min:1',
            'address' => 'nullable|string'
        ]);

        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Token invalide ou expiré'], 401);
        }

        if (empty($this->secret)) {
            Log::error('Fedapay missing secret key env');
            return response()->json(['ok' => false, 'error' => 'Clé FedaPay non configurée (FEDAPAY_SECRET_KEY)'], 500);
        }

        $reference = 'ORD-' . Str::upper(Str::random(8));
        $amount = (int) round($req->amount);

        DB::beginTransaction();
        try {
            $payment = \App\Models\Payment::create([
                'order_id' => null,
                'transaction_id' => null,
                'phone' => $user->phone ?? null,
                'amount' => $amount,
                'status' => 'pending',
            ]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Payment row creation failed', ['err' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => 'Impossible de démarrer le paiement'], 500);
        }

        $metadata = [
            'reference' => $reference,
            'local_payment_id' => $payment->id,
            'user_id' => $user->id,
            'products' => json_encode($req->products),
            'address' => $req->address ?? ''
        ];


        $returnUrl = $this->frontendUrl ? ($this->frontendUrl . '/payment-result') : null;
        if (!$returnUrl) {
            Log::warning('APP_FRONTEND_URL not set. Using a local fallback for return_url (not recommended in prod).');
            $returnUrl = route('payment.success'); // fallback internal route (GET). Better to set APP_FRONTEND_URL.
        }

        $txPayload = [
            'description' => "Order {$reference}",
            'amount' => $amount,
            'currency' => ['iso' => 'XOF'],
            'callback_url' => route('fedapay.webhook'),
            'return_url' => $returnUrl,             
            'customer' => [
                'firstname' => $user->firstName ?? $user->name ?? 'Client',
                'lastname' => $user->lastName ?? '',
                'email' => $user->email ?? 'test@example.com',
                'phone' => $user->phone ?? ''
            ],
            'metadata' => $metadata,
        ];

        Log::info('Fedapay createTransaction payload', ['payload' => $txPayload]);

        try {
            $createResp = $this->sendFedapayRequest('post', '/transactions', $txPayload);
            Log::info('Fedapay createResp', ['status' => $createResp->status(), 'body' => $createResp->body()]);

            if ($createResp->status() === 401) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Erreur d\'authentification FedaPay (401). Vérifie ta clé (FEDAPAY_SECRET_KEY) et le endpoint (FEDAPAY_API_BASE).',
                    'details' => $createResp->body()
                ], 401);
            }

            if ($createResp->failed()) {
                $payment->update(['status' => 'failed', 'meta' => $createResp->body()]);
                return response()->json(['ok' => false, 'error' => 'Échec création transaction FedaPay', 'details' => $createResp->body()], 500);
            }
            $txData = (array) $createResp->json();
            $txId = $txData['data']['id']
                ?? $txData['id']
                ?? ($txData['transaction']['id'] ?? null);

            // FIX pour la nouvelle structure Fedapay
            if (!$txId && isset($txData['v1/transaction']['id'])) {
                $txId = $txData['v1/transaction']['id'];
            }

            if (!$txId) {
                Log::error('Fedapay create: missing id', ['resp' => $txData]);
                $payment->update(['status' => 'failed', 'meta' => $txData]);
                return response()->json(['ok' => false, 'error' => 'ID transaction manquant dans réponse FedaPay', 'details' => $txData], 500);
            }

            $payment->update(['transaction_id' => (string) $txId, 'meta' => $txData]);

            // request token / checkout url
            $tokenResp = $this->sendFedapayRequest('post', "/transactions/{$txId}/token", null);
            Log::info('Fedapay tokenResp', ['status' => $tokenResp->status(), 'body' => $tokenResp->body()]);

            if ($tokenResp->status() === 401) {
                return response()->json(['ok' => false, 'error' => 'Erreur d\'authentification FedaPay lors génération token (401).', 'details' => $tokenResp->body()], 401);
            }

            if ($tokenResp->failed()) {
                $payment->update(['status' => 'failed', 'meta' => $tokenResp->body()]);
                return response()->json(['ok' => false, 'error' => 'Échec génération lien paiement', 'details' => $tokenResp->body()], 500);
            }

            $tokenData = $tokenResp->json();
            $checkoutUrl = $tokenData['token']['url'] ?? $tokenData['url'] ?? ($tokenData['data']['url'] ?? null);
            $token = $tokenData['token']['key'] ?? $tokenData['token'] ?? ($tokenData['data']['token'] ?? null);

            if (!$checkoutUrl) {
                Log::error('Fedapay token missing url', ['resp' => $tokenData]);
                return response()->json(['ok' => false, 'error' => 'Lien de paiement introuvable', 'details' => $tokenData], 500);
            }

            return response()->json([
                'ok' => true,
                'checkout_url' => $checkoutUrl,
                'transaction_id' => (string) $txId,
                'payment_id' => $payment->id,
                'reference' => $metadata['reference'],
                'token' => $token
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Fedapay createTransaction exception', ['err' => $e->getMessage()]);
            try {
                $payment->update(['status' => 'failed']);
            } catch (\Throwable $_) {
            }
            return response()->json(['ok' => false, 'error' => 'Erreur serveur', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Webhook: process POST events from FedaPay
     */
    public function webhook(Request $req)
    {
        Log::info("FEDAPAY RAW DATA", ['content' => $req->getContent()]);

        $payload = $req->json()->all();

        // CORRECTION → FedaPay utilise "entity" pour les données transaction
        $data = $payload['entity'] ?? null;

        if (!$data || !is_array($data)) {
            Log::error("Webhook Fedapay: format invalide", ["payload" => $payload]);
            return response()->json(["ok" => false, "error" => "Format webhook invalide"], 200);
        }

        $event = strtolower($payload['name'] ?? '');
        $txId = $data['id'] ?? null;
        $status = strtolower($data['status'] ?? '');

        Log::info('Fedapay webhook parsed', [
            'event' => $event,
            'txId' => $txId,
            'status' => $status,
            'data' => $data
        ]);
        $payment = null;
        if ($txId) {
            $payment = \App\Models\Payment::where('transaction_id', (string) $txId)->first();
        }

        if (!$payment && isset($data['metadata']['local_payment_id'])) {
            $payment = \App\Models\Payment::find($data['metadata']['local_payment_id']);
        }

        if (!$payment) {
            Log::warning('Fedapay webhook: payment not found', ['payload' => $payload]);
            // return 200 to acknowledge the webhook even if we ignore it (avoid retries)
            return response()->json(['ok' => true, 'message' => 'ignored - payment not found'], 200);
        }

        $isPaid = (isset($data['status']) && in_array(strtolower($data['status']), ['paid', 'approved', 'success']))
            || stripos($status, 'paid') !== false
            || stripos($status, 'success') !== false;

        if ($isPaid) {
            // create order if none
            if (!$payment->order_id) {
                $meta = $data['metadata'] ?? [];
                $productsJson = $meta['products'] ?? null;
                $products = [];
                if ($productsJson) {
                    $products = is_array($productsJson) ? $productsJson : json_decode($productsJson, true);
                    if (!is_array($products))
                        $products = [];
                }

                DB::beginTransaction();
                try {
                    $order = Order::create([
                        'user_id' => $meta['user_id'] ?? $payment->user_id ?? null,
                        'produits' => json_encode($products),
                        'total' => $payment->amount,
                        'reference' => $meta['reference'] ?? ('ORD-' . Str::upper(Str::random(8))),
                        'status' => 'paid',
                    ]);

                    $payment->update(['order_id' => $order->id, 'status' => 'approved', 'meta' => $data]);
                    DB::commit();

                    event(new OrderPaid($order));
                    Log::info('Order created from webhook', ['order_id' => $order->id, 'payment_id' => $payment->id]);
                } catch (\Throwable $e) {
                    DB::rollBack();
                    Log::error('Error creating order in webhook', ['err' => $e->getMessage(), 'payload' => $payload]);
                    return response()->json(['ok' => false, 'error' => 'Erreur création commande'], 500);
                }
            } else {
                $payment->update(['status' => 'approved', 'meta' => $data]);
            }

            return response()->json(['ok' => true], 200);
        }

        $isFailed = (isset($data['status']) && in_array(strtolower($data['status']), ['canceled', 'refused', 'failed']))
            || stripos($status, 'cancel') !== false
            || stripos($status, 'refused') !== false;

        if ($isFailed) {
            $payment->update(['status' => 'declined', 'meta' => $data]);
            if ($payment->order_id) {
                $order = Order::find($payment->order_id);
                if ($order)
                    $order->update(['status' => 'declined']);
            }
            return response()->json(['ok' => true], 200);
        }

        // default: store meta and return accepted
        $payment->update(['meta' => $data]);
        return response()->json(['ok' => true], 202);
    }

    /**
     * GET endpoint used as fallback redirect (if needed).
     * Usually FedaPay should redirect directly to the FRONT return_url,
     * but some flows may use a server-side redirect — this forwards to FRONT.
     */
    public function redirectPayment(Request $req)
    {
        $status = $req->get('status');
        $id = $req->get('id');        // transaction id

        // use FRONTEND url
        $front = $this->frontendUrl ?: '/';
        if (in_array(strtolower($status), ['approved', 'success'])) {
            return redirect($front . '/payment-result?status=success&id=' . urlencode($id));
        }

        return redirect($front . '/payment-result?status=failed&id=' . urlencode($id));
    }

    /**
     * Optional: check status by polling FedaPay (fallback)
     */
    public function checkStatus($transactionId)
    {
        try {
            $resp = $this->sendFedapayRequest('get', "/transactions/{$transactionId}");
            if ($resp->failed()) {
                return response()->json(['ok' => false, 'error' => 'error from fedapay', 'details' => $resp->body()], 500);
            }
            return response()->json(['ok' => true, 'data' => $resp->json()]);
        } catch (\Throwable $e) {
            Log::error('checkStatus error', ['err' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => 'exception', 'message' => $e->getMessage()], 500);
        }
    }
}
