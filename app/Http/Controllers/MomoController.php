<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

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
    public function pay(Request $request)
    {
        $request->validate([
            "amount" => "required|numeric",
            "phone" => "required|string",
        ]);

        // Génération du token MTN
        $token = $this->getAccessToken();

        // Référence MTN pour identifier ce paiement
        $referenceId = (string) Str::uuid();

        // On enregistre juste la demande de paiement
        $payment = Payment::create([
            "user_id" => Auth::id(),
            "reference_id" => $referenceId,
            "amount" => $request->amount,
            "phone" => $request->phone,
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

        $payment = Payment::where("reference_id", $referenceId)->first();

        if (!$payment) {
            return response()->json(["message" => "Paiement introuvable"], 404);
        }

        if ($status === "SUCCESSFUL") {

            // Mettre à jour le paiement
            $payment->status = "validee";
            $payment->transaction_id = $request->transactionId ?? null;
            $payment->save();

            // CRÉER LA COMMANDE ICI UNIQUEMENT
            $order = Order::create([
                "user_id" => $payment->user_id,
                "total" => $payment->amount,
                "status" => "payé",
            ]);

            // Attacher la commande au paiement
            $payment->order_id = $order->id;
            $payment->save();

            return response()->json(["message" => "Paiement validé, commande créée"]);
        }

        // Si paiement non validé
        $payment->status = "annulee";
        $payment->save();

        return response()->json(["message" => "Paiement refusé"]);
    }

    public function checkStatus($reference)
    {
        $payment = Payment::where("reference_id", $reference)->firstOrFail();

        $token = $this->getAccessToken();

        $response = Http::withHeaders([
            "Authorization" => "Bearer " . $token,
            "X-Target-Environment" => "sandbox",
            "Ocp-Apim-Subscription-Key" => env("MTN_PRIMARY_KEY")
        ])->get("https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay/$reference");

        $status = strtoupper($response->json("status"));

        if ($status === "SUCCESSFUL") {

            if ($payment->status !== "successful") {

                // Mettre à jour le paiement
                $payment->status = "validee";
                $payment->transaction_id = $response->json("financialTransactionId");
                $payment->save();

                // Créer la commande
                $order = Order::create([
                    "user_id" => $payment->user_id,
                    "amount" => $payment->amount,
                    "status" => "payé",
                ]);

                $payment->order_id = $order->id;
                $payment->save();
            }
        } elseif ($status === "FAILED") {
            $payment->status = "annulee";
            $payment->save();
        }

        return response()->json(["status" => $payment->status]);
    }
}
