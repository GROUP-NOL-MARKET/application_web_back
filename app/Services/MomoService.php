<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MomoService
{
    public function getToken()
    {
        $response = Http::withHeaders([
            "Authorization" => "Basic " . base64_encode(env("MOMO_COLLECTION_API_USER") . ":" . env("MOMO_COLLECTION_API_KEY")),
            "Ocp-Apim-Subscription-Key" => env("MOMO_COLLECTION_PRIMARY_KEY"),
        ])->post(env("MOMO_COLLECTION_BASE_URL") . "/collection/token/");

        return $response->json()['access_token'] ?? null;
    }

    public function requestToPay($amount, $phone, $externalId)
    {
        $token = $this->getToken();
        $referenceId = Str::uuid()->toString();

        Http::withHeaders([
            "Authorization" => "Bearer " . $token,
            "X-Reference-Id" => $referenceId,
            "X-Target-Environment" => env("MOMO_ENV"),
            "Ocp-Apim-Subscription-Key" => env("MOMO_COLLECTION_PRIMARY_KEY"),
            "Content-Type" => "application/json"
        ])->post(env("MOMO_COLLECTION_BASE_URL") . "/collection/v1_0/requesttopay", [
            "amount" => $amount,
            "currency" => "XOF",
            "externalId" => $externalId,
            "payer" => [
                "partyIdType" => "MSISDN",
                "partyId" => $phone
            ],
            "payerMessage" => "Paiement NolMarket",
            "payeeNote" => "Merci pour votre achat"
        ]);

        return $referenceId;
    }

    public function getPaymentStatus($referenceId)
    {
        $token = $this->getToken();

        $response = Http::withHeaders([
            "Authorization" => "Bearer " . $token,
            "X-Target-Environment" => env("MOMO_ENV"),
            "Ocp-Apim-Subscription-Key" => env("MOMO_COLLECTION_PRIMARY_KEY"),
        ])->get(env("MOMO_COLLECTION_BASE_URL") . "/collection/v1_0/requesttopay/{$referenceId}");

        return $response->json();
    }
}