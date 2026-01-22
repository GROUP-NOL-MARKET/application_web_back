<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Mail\OrderPaidAdmin;
use App\Services\FasterMessageService;
use App\Mail\OrderPaidUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class MomoController extends Controller
{

    private function getAccessToken()
    {
        $subscriptionKey = env("MTN_PRIMARY_KEY");
        $userId = env("MTN_USER_ID");
        $apikey = env("MTN_API_KEY");

        $response = Http::withHeaders([
            "Ocp-Apim-Subscription-Key" => $subscriptionKey,
        ])->withBasicAuth($userId, $apikey)
            ->post("https://sandbox.momodeveloper.mtn.com/collection/token/");

        if (!$response->successful()) {
            throw new \Exception("Impossible de récupérer le token MTN", 500);
        }

        return $response->json()["access_token"];
    }


    private function confirmPayment(Payment $payment, ?string $transactionId = null)
    {
        if ($payment->status === 'validee') {
            return;
        }

        \DB::transaction(function () use ($payment, $transactionId) {
            $payment->update([
                'status' => 'validee',
                'transaction_id' => $transactionId,
            ]);

            $order = Order::create([
                'user_id' => $payment->user_id,
                'reference' => $payment->reference_id,
                'total' => $payment->amount,
                'status' => 'validee',
                'produits' => $payment->products,
            ]);

            $payment->update(['order_id' => $order->id]);
        });

        // RECHARGEMENT PROPRE
        $order = Order::with(['user', 'payment'])
            ->where('id', $payment->order_id)
            ->first();

        // ADMIN
        // Mail::to(config('mail.admin_address', 'groupnolmarket@gmail.com'))
            // ->send(new OrderPaidAdmin($order));

        // CLIENT
        if (!empty($payment->email)) {
            Mail::to($payment->email)
                ->send(new OrderPaidUser($order));
        }
        // SMS
        elseif (!empty($order->user?->phone)) {

            app(FasterMessageService::class)->send(
                $order->user->phone,
                "Votre commande n°{$order->reference} a été validée. Vous serez livrés d'ici peu et nous vous recommandons de suivre le statut de la commande dans le menu 'Mon compte' sur notre plateforme. Nol Market vous remercie pour votre achat. Info: 0165002800"
            );
        }
    }

    public function pay(Request $request)
    {
        $request->validate([
            "amount" => "required|numeric",
            "phone" => "required|string",
            "products" => "required|array",
        ]);

        // Génération du token MTN
        $token = $this->getAccessToken();

        // Référence MTN pour identifier ce paiement
        $referenceId = (string) Str::uuid();

        // On enregistre juste la demande de paiement
        $payment = Payment::create([
            "user_id" => Auth::id(),
            "reference_id" => $referenceId,
            "email" => Auth::user()?->email,
            "amount" => $request->amount,
            "phone" => $request->phone,
            "products" => $request->products,
            "status" => "pending",
            "method" => "momo",
        ]);

        $response = Http::withHeaders([
            "Authorization" => "Bearer " . $token,
            "X-Reference-Id" => $referenceId,
            "X-Target-Environment" => "sandbox",
            "Ocp-Apim-Subscription-Key" => env("MTN_PRIMARY_KEY"),
            "Content-Type" => "application/json"
        ])->post("https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay", [
            "amount" => $request->amount,
            "currency" => "EUR",
            "externalId" => $payment->id,
            "payer" => [
                "partyIdType" => "MSISDN",
                "partyId" => $request->phone
            ],
            "payerMessage" => "Paiement Nol Market",
            "payeeNote" => "Merci pour votre achat"
        ]);

        if (!$response->successful()) {
            $payment->status = "failed";
            $payment->save();

            return response()->json([
                "message" => "Erreur lors de l’initiation du paiement.",
                "details" => $response->json()
            ], 500);
        }

        return response()->json([
            "message" => "Paiement initié. En attente de validation.",
            "reference" => $referenceId
        ]);
    }



    // ======================
    // 3. CALLBACK MTN
    // ======================
    public function callback(Request $request)
    {
        $referenceId = $request->referenceId;
        $status = strtoupper($request->status);

        $payment = Payment::where('reference_id', $referenceId)->first();

        if (!$payment) {
            return response()->json(['message' => 'Paiement introuvable'], 404);
        }

        if ($status === 'SUCCESSFUL') {
            $this->confirmPayment(
                $payment,
                $request->transactionId ?? null
            );

            return response()->json(['message' => 'Paiement validé']);
        }

        if (in_array($status, ['FAILED', 'CANCELLED'])) {
            $payment->update(['status' => 'annulee']);
        }

        return response()->json(['message' => 'Paiement non validé']);
    }

    public function checkStatus($reference)
    {
        $payment = Payment::where('reference_id', $reference)->firstOrFail();

        $token = $this->getAccessToken();

        $response = Http::withHeaders([
            "Authorization" => "Bearer $token",
            "X-Target-Environment" => "sandbox",
            "Ocp-Apim-Subscription-Key" => env("MTN_PRIMARY_KEY")
        ])->get("https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay/$reference");

        $status = strtoupper($response->json('status'));

        if ($status === 'SUCCESSFUL') {
            $this->confirmPayment(
                $payment,
                $response->json('financialTransactionId')
            );
        }

        if ($status === 'FAILED') {
            $payment->update(['status' => 'annulee']);
        }

        return response()->json([
            'status' => $payment->status
        ]);
    }
}
