<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\Order;
use App\Models\Payment;

class MoovmoneyController extends Controller
{
    public function pay(Request $request)
    {
        $request->validate([
            'phone' => 'required|regex:/^0(1[0-4]|6|7|8|9)\d{7}$/',
            'amount' => 'required|numeric|min:100'
        ]);

        $user = Auth::user();
        $userId = $user->id;

        // 🟦 Générer un transactionId unique
        $transactionId = Str::uuid();

        // 🟦 Créer une commande "en cours"
        $order = Order::create([
            'user_id' => $userId,
            'total_amount' => $request->amount,
            'status' => 'en cours',
        ]);

        // 🟦 Appel API Moov Money (format officiel)
        $moovResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . env('MOOV_MONEY_TOKEN'),
        ])->post(env('MOOV_MONEY_URL') . '/merchant/pay', [
            'msisdn' => $request->phone,
            'amount' => $request->amount,
            'transref' => $transactionId,
            'callbackUrl' => env('APP_URL') . '/api/moov/callback',
        ]);

        if ($moovResponse->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur Moov Money',
                'details' => $moovResponse->json(),
            ], 500);
        }

        // 🟦 Enregistrer la transaction
        Payment::create([
            'order_id' => $order->id,
            'transaction_id' => $transactionId,
            'phone' => $request->phone,
            'amount' => $request->amount,
            'method' => 'MoovMoney',
            'status' => 'en cours',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande de paiement envoyée. Veuillez valider sur votre téléphone.',
            'transactionId' => $transactionId,
            'orderId' => $order->id,
        ]);
    }

    // 🟦 CALLBACK / WEBHOOK MOOV
    public function callback(Request $request)
    {
        $payment = Payment::where('transaction_id', $request->transref)->first();

        if (!$payment) {
            return response()->json(['error' => 'Transaction inconnue'], 404);
        }

        // Statut retourné par Moov : SUCCESS, FAILED, CANCELLED
        $payment->status = strtolower($request->status);
        $payment->save();

        if ($request->status === "SUCCESS") {
            $order = Order::find($payment->order_id);
            $order->status = "approuvé";
            $order->save();
        }

        return response()->json(['message' => 'OK']);
    }
}