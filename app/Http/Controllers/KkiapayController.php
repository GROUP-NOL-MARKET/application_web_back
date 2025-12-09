<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Payment;
use App\Mail\OrderPaidAdmin;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Mail;

class KkiapayController extends Controller
{
    public function callback(Request $request)
    {
        $request->validate([
            'transactionId' => 'required|string',
            'amount' => 'required|numeric',
            'cart' => 'required|array',
            'phone' => 'required|string',
        ]);

        try {
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
            'status' => 'pending',
        ]);

        // Paiement
        Payment::create([
            'order_id' => $order->id,
            'transaction_id' => $request->transactionId,
            'amount' => $request->amount,
            'status' => 'pending',
            'method' => 'Kkiapay',
            'phone' => $request->phone,
        ]);

        return response()->json([
            'message' => 'Commande enregistrée, en attente de confirmation',
            'order' => $order
        ]);
    }


    public function paymentSuccess(Request $request)
    {
        $request->validate([
            'order_id' => 'required',
            'status' => 'required|string',
            'transaction_id' => 'required|string',
        ]);

        // Kkiapay renvoie "success" ou "successful"
        if (!in_array(strtolower($request->status), ['success', 'successful'])) {
            return response()->json(['message' => 'Paiement non confirmé'], 400);
        }

        // Récupération commande
        $order = Order::findOrFail($request->order_id);

        // Mise à jour commande
        $order->status = 'paid';
        $order->payment_status = 'success';
        $order->payment_reference = $request->transaction_id;
        $order->save();

        // Produits du panier (JSON)
        $products = $order->produits;

        // Décrémentation du stock
        foreach ($products as $item) {

            $product = Product::find($item['id']);

            if (!$product) {
                continue; // Produit supprimé ou inexistant
            }

            if ($product->quantity < $item['quantity']) {
                return response()->json([
                    'message' => "Stock insuffisant pour " . $product->name
                ], 400);
            }

            $product->quantity -= $item['quantity'];
            $product->save();
        }

        // Envoi email admin
        Mail::to("groupnolmarket@gmail.com")->send(new OrderPaidAdmin($order));

        return response()->json([
            'message' => 'Paiement validé, stock mis à jour et email envoyé'
        ], 200);
    }
}
