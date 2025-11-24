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

    public function __construct()
    {
        $this->secret = env('FEDAPAY_SECRET_KEY');
        $this->apiBase = rtrim(env('FEDAPAY_API_BASE', 'https://sandbox-api.fedapay.com/v1'), '/');
    }

    /**
     * Helper: send request to FedaPay trying different Authorization formats.
     * Returns Response object (Illuminate\Http\Client\Response)
     */
    protected function sendFedapayRequest(string $method, string $path, $payload = null): Response
    {
        // Ensure secret exists
        if (empty($this->secret)) {
            throw new \RuntimeException('FEDAPAY_SECRET_KEY is not set in environment.');
        }

        $url = "{$this->apiBase}{$path}";

        // prepare headers common
        $commonHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Try 1: Authorization: Token <secret>
        $headersToken = array_merge($commonHeaders, ['Authorization' => 'Token ' . $this->secret]);
        Log::info('Fedapay sending request (Token header)', ['method' => $method, 'url' => $url, 'headers' => $headersToken, 'payload' => $payload]);

        $req = Http::withHeaders($headersToken);
        $resp = $this->dispatchHttp($req, $method, $url, $payload);

        // If unauthorized (401), try with Bearer (some accounts / SDK expect Bearer)
        if ($resp->status() === 401) {
            Log::warning('Fedapay Token auth got 401, retrying with Bearer', ['url' => $url, 'status' => $resp->status(), 'body' => $resp->body()]);
            $headersBearer = array_merge($commonHeaders, ['Authorization' => 'Bearer ' . $this->secret]);
            Log::info('Fedapay sending request (Bearer header)', ['method' => $method, 'url' => $url, 'headers' => $headersBearer, 'payload' => $payload]);

            $req2 = Http::withHeaders($headersBearer);
            $resp2 = $this->dispatchHttp($req2, $method, $url, $payload);

            // return second response whether ok or not
            return $resp2;
        }

        return $resp;
    }

    /**
     * Dispatch HTTP request depending on method (post/get)
     */
    protected function dispatchHttp($httpClient, string $method, string $url, $payload = null): Response
    {
        $method = strtolower($method);
        if ($method === 'post') {
            return $httpClient->post($url, $payload);
        } elseif ($method === 'get') {
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
     * Front calls this to create a FedaPay transaction and get a checkout url.
     * Body: { amount: number, products: [...], address: string (optional) }
     */
    public function createTransaction(Request $req)
    {
        $req->validate([
            'amount' => 'required|numeric|min:1',
            'products' => 'required|array|min:1',
            'address' => 'nullable|string'
        ]);

        // auth via JWT
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Token invalide ou expiré'], 401);
        }

        // basic env checks
        if (empty($this->secret)) {
            Log::error('Fedapay missing secret key env');
            return response()->json(['ok' => false, 'error' => 'Clé FedaPay non configurée (FEDAPAY_SECRET_KEY)'], 500);
        }

        if (empty($this->apiBase)) {
            Log::error('Fedapay missing api base env');
            return response()->json(['ok' => false, 'error' => 'Endpoint FedaPay non configuré (FEDAPAY_API_BASE)'], 500);
        }

        // quick sanity: if secret starts with sk_sandbox_ require sandbox url
        if (strpos($this->secret, 'sk_sandbox_') === 0 && strpos($this->apiBase, 'sandbox-api.fedapay.com') === false) {
            Log::warning('Fedapay secret looks sandbox but api base is not sandbox', ['secret' => substr($this->secret,0,20).'...', 'apiBase' => $this->apiBase]);
            // not fatal, just warning
        }

        $reference = 'ORD-' . Str::upper(Str::random(8));
        $amount = (int) round($req->amount); // XOF as integer (FCFA)

        // Create a payment row (no order yet)
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
            // store products as JSON string to be safe in metadata
            'products' => json_encode($req->products),
            'address' => $req->address ?? ''
        ];

        $txPayload = [
            'description' => "Order {$reference}",
            'amount' => $amount,
            'currency' => ['iso' => 'XOF'],
            'callback_url' => route('fedapay.webhook'),
            'customer' => [
                'firstname' => $user->firstName ?? $user->name ?? 'Client',
                'lastname' => $user->lastName ?? '',
                'email' => $user->email ?? '',
                'phone' => $user->phone ?? ''
            ],
            'metadata' => $metadata,
        ];

        Log::info('Fedapay createTransaction payload', ['payload' => $txPayload]);

        try {
            // 1) create transaction (tries Token then Bearer automatically)
            $createResp = $this->sendFedapayRequest('post', '/transactions', $txPayload);

            Log::info('Fedapay createResp', [
                'status' => $createResp->status(),
                'body' => $createResp->body()
            ]);

            if ($createResp->status() === 401) {
                // auth failed even after retry — return helpful error
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

            $txData = $createResp->json();

            // id often lives in data.id
            $txId = $txData['data']['id'] ?? $txData['id'] ?? null;
            if (!$txId) {
                Log::error('Fedapay create: missing id', ['resp' => $txData]);
                $payment->update(['status' => 'failed', 'meta' => $txData]);
                return response()->json(['ok' => false, 'error' => 'ID transaction manquant dans réponse FedaPay', 'details' => $txData], 500);
            }

            // save transaction id on payment
            $payment->update(['transaction_id' => (string)$txId, 'meta' => $txData]);

            // 2) request token (checkout url) — also tries Token then Bearer
            $tokenResp = $this->sendFedapayRequest('post', "/transactions/{$txId}/token", null);

            Log::info('Fedapay tokenResp', ['status' => $tokenResp->status(), 'body' => $tokenResp->body()]);

            if ($tokenResp->status() === 401) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Erreur d\'authentification FedaPay lors génération token (401).',
                    'details' => $tokenResp->body()
                ], 401);
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
                'transaction_id' => (string)$txId,
                'payment_id' => $payment->id,
                'reference' => $metadata['reference'],
                'token' => $token
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Fedapay createTransaction exception', ['err' => $e->getMessage()]);
            try { $payment->update(['status' => 'failed']); } catch (\Throwable $_) {}
            return response()->json(['ok' => false, 'error' => 'Erreur serveur', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Webhook: FedaPay will call this when transaction status changes.
     * We create the order and update payment when status is paid.
     */
    public function webhook(Request $req)
    {
        $raw = $req->getContent();
        Log::info('Fedapay webhook raw', ['headers' => $req->headers->all(), 'body' => $raw]);

        $payload = $req->json()->all();
        $data = $payload['data'] ?? $payload['transaction'] ?? [];

        $txId = $data['id'] ?? null;
        $status = strtolower($payload['event'] ?? ($data['status'] ?? ''));

        Log::info('Fedapay webhook parsed', ['txId' => $txId, 'status' => $status, 'data' => $data]);

        // find payment by transaction_id
        $payment = null;
        if ($txId) {
            $payment = \App\Models\Payment::where('transaction_id', (string)$txId)->first();
        }

        // fallback: if metadata.local_payment_id is present, try find by id
        if (!$payment && isset($data['metadata']['local_payment_id'])) {
            $payment = \App\Models\Payment::find($data['metadata']['local_payment_id']);
        }

        if (!$payment) {
            Log::warning('Fedapay webhook: payment not found', ['payload' => $payload]);
            return response()->json(['ok' => false, 'message' => 'payment not found'], 404);
        }

        $isPaid = (isset($data['status']) && in_array($data['status'], ['paid', 'approved', 'success']))
            || stripos($status, 'paid') !== false
            || stripos($status, 'success') !== false;

        if ($isPaid) {
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

        $isFailed = (isset($data['status']) && in_array($data['status'], ['canceled', 'refused', 'failed']))
            || stripos($status, 'cancel') !== false
            || stripos($status, 'refused') !== false;

        if ($isFailed) {
            $payment->update(['status' => 'declined', 'meta' => $data]);
            if ($payment->order_id) {
                $order = Order::find($payment->order_id);
                if ($order) $order->update(['status' => 'declined']);
            }
            return response()->json(['ok' => true], 200);
        }

        $payment->update(['meta' => $data]);
        return response()->json(['ok' => true], 202);
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
