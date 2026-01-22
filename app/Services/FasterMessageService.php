<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FasterMessageService
{
    protected string $endpoint = 'https://api.fastermessage.com/v1/sms/send';

    public function send(string $phone, string $message): bool
    {
        $username = trim(config('services.fastermessage.username'));
        $password = trim(config('services.fastermessage.password'));
        $sender = config('services.fastermessage.sender');

        $auth = base64_encode($username . ':' . $password);

        $normalizedPhone = $this->normalizePhone($phone);

        // Payload EXACT attendu par FasterMessage
        $payload = [
            'from' => $sender,
            'to' => [$normalizedPhone], // OBLIGATOIRE : tableau
            'text' => $message,
            'accents'=>true,
        ];

        // Log AVANT envoi
        Log::info('FasterMessage SMS payload', $payload);

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $auth,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post($this->endpoint, $payload);

        if (!$response->successful()) {
            Log::error('FasterMessage HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        $data = $response->json();

        if (!isset($data['status']) || $data['status'] !== true) {
            Log::error('FasterMessage API error', $data);
            return false;
        }

        return true;
    }

    /**
     * Normalisation du numéro (format international sans +)
     * Exemple : 229XXXXXXXX
     */
    private function normalizePhone(string $phone): string
    {
        // Supprime tout sauf chiffres
        $phone = preg_replace('/\D/', '', $phone);

        // Retire indicatif si déjà présent
        if (str_starts_with($phone, '229')) {
            $phone = substr($phone, 3);
        }

        return '229' . $phone;
    }
}
