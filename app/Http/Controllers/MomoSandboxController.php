<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Events\OrderPaid;


class MomoSandboxController extends Controller
{
    protected $base;
    protected $subscriptionKey;
    protected $apiUser;
    protected $apiKey;
    protected $targetEnv;

    public function __construct()
    {
        $this->base = env('MOMO_API_BASE');
        $this->subscriptionKey = env('MOMO_SUBSCRIPTION_KEY');
        $this->apiUser = env('MOMO_API_USER');
        $this->apiKey = env('MOMO_API_KEY');
        $this->targetEnv = env('MOMO_TARGET_ENV', 'sandbox');
    }

    /**
     * Obtient un token d'accès sandbox (OAuth2)
     */
    protected function getAccessToken()
    {
        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        ])->withBasicAuth($this->apiUser, $this->apiKey)
            ->post($this->base . '/collection/token/');

        if ($response->failed()) {
            throw new \Exception('Impossible d’obtenir le token MoMo');
        }

        return $response->json()['access_token'];
    }

    /**
     * Étape 1 : Création de la commande + init paiement sandbox
     */
    public function createPayment(Request $req)
    {
        $req->validate(['amount' => 'required|numeric|min:1']);

        $user = JWTAuth::parseToken()->authenticate();

        $reference = 'ORD-' . Str::upper(Str::random(8));

        // 1. Création commande
        $order = Order::create([

            'user_id' => $user->id,
            'total' => $req->amount,
            'currency' => 'XOF',
            'reference' => $reference,
            'status' => 'pending',
        ]);

        // 2. Création paiement
        $payment = $order->payment()->create([
            'transaction_id' => 1,
            'amount' => $req->amount,
            'status' => 'pending',
        ]);

        $token = $this->getAccessToken();
        $transactionId = (string) Str::uuid();

        $payload = [
            'amount' => number_format($req->amount, 2, '.', ''),
            'currency' => 'EUR',
            'externalId' => $reference,
            'payer' => [
                'partyIdType' => 'MSISDN',
                'partyId' => '46733123453'
            ],
            'payerMessage' => "Sandbox Payment $reference",
            'payeeNote' => "Sandbox test"
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Reference-Id' => $transactionId,
            'X-Target-Environment' => $this->targetEnv,
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            'Content-Type' => 'application/json'
        ])->post($this->base . '/collection/v1_0/requesttopay', $payload);

        if ($response->failed()) {
            $order->update(['status' => 'failed']);
            $payment->update(['status' => 'failed']);
            return response()->json(['ok' => false, 'error' => 'Échec init paiement'], 500);
        }

        // 3. Mise à jour transaction
        $order->update(['status' => 'approved']);
        $payment->update([
            'transaction_id' => $transactionId,
            'status' => 'approved'
        ]);

        return response()->json([
            'ok' => true,
            'reference' => $reference,
            'transaction_id' => $transactionId,
            'message' => 'Paiement sandbox initié.'
        ]);
    }

    /**
     * Étape 2 : Vérification du statut sandbox (polling)
     */
    public function getStatus($reference)
    {
        $order = Order::where('reference', $reference)->firstOrFail();
        $payment = $order->payment;

        $txId = $payment->transaction_id;
        if (!$txId) {
            return response()->json(['status' => 'unknown']);
        }

        $token = $this->getAccessToken();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Target-Environment' => $this->targetEnv,
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        ])->get($this->base . "/collection/v1_0/requesttopay/{$txId}");

        if ($response->failed()) {
            return response()->json(['status' => 'error']);
        }

        $data = $response->json();
        $status = strtolower($data['status']);

        // Synchronisation commande + paiement
        if (in_array($status, ['success', 'completed'])) {

            // Déjà payé ? On évite d’envoyer plusieurs mails
            if ($order->status !== 'approved') {
                event(new OrderPaid($order));
            }

            $order->update(['status' => 'approved']);
            $payment->update(['status' => 'approved']);

        } elseif (in_array($status, ['failed', 'declined'])) {
            $order->update(['status' => 'declined']);
            $payment->update(['status' => 'declined']);
        }

        return response()->json([
            'reference' => $reference,
            'status' => $payment->status,
            'meta' => $data,
        ]);
    }
}
