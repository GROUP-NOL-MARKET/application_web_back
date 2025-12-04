<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Payment;
use Tymon\JWTAuth\Facades\JWTAuth;

class KkiapayController extends Controller
{
    public function callback(Request $request)
    {
        // Vérification basique des données
        $request->validate([
            'transactionId' => 'required|string',
            'amount' => 'required|numeric',
            'cart' => 'required|array',      // Produits du panier
            'phone' => 'required|string',    // Téléphone du client
        ]);

        try {
            // Récupérer l'utilisateur via JWT
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        // Création de la commande
        $order = Order::create([
            'user_id' => $user->id,
            'produits' => $request->cart,
            'reference' => 'KKIA-' . strtoupper(uniqid()),
            'total' => $request->amount,
            'status' => 'validee',
        ]);

        // Création du paiement associé
        $payment = Payment::create([
            'order_id' => $order->id,
            'transaction_id' => $request->transactionId,
            'amount' => $request->amount,
            'status' => 'validee',
            'method' => 'Kkiapay',
            'phone' => $request->phone,
        ]);

        return response()->json([
            'message' => 'Commande et paiement enregistrés',
            'order' => $order,
            'payment' => $payment
        ]);
    }
}