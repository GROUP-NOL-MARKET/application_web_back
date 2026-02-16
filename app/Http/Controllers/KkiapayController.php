<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Payment;
use App\Mail\OrderPaidAdmin;
use App\Mail\OrderPaidUser;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Services\FasterMessageService;

class KkiapayController extends Controller
{
    public function callback(Request $request)
    {
        $request->validate([
            'transactionId' => 'required|string',
            'amount' => 'required|numeric',
            'cart' => 'required|array',
            'phone' => 'required|string',
            'address' => 'required|string',  // Adresse de livraison
            'paymentData' => 'nullable|array'
        ]);

        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        $email = $user->email ?? $request->paymentData['email'] ?? null;

        // Référence interne
        $reference = 'KKIA-' . strtoupper(uniqid());

        // Création de la commande validée directement
        $order = Order::create([
            'user_id' => $user->id,
            'produits' => $request->cart,
            'reference' => $reference,
            'total' => $request->amount,
            'status' => 'validee',
            'payment_status' => 'success',
            'payment_reference' => $request->transactionId,
            'payment_method' => 'Kkiapay',
            'delivery_address' => $request->address,
            'delivery_phone' => $request->phone,
        ]);

        // Mise à jour des paiements
        Payment::create([
            'reference_id' => $reference,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'email' => $email,
            'transaction_id' => $request->transactionId,
            'products' => json_encode($request->cart),
            'amount' => $request->amount,
            'status' => 'success',
            'method' => 'Kkiapay',
            'phone' => $request->phone,
            'payload' => $request->paymentData,
        ]);

        // Décrémentation du stock
        foreach ($request->cart as $item) {
            $product = Product::find($item['id']);
            if (!$product)
                continue;

            if ($product->quantity < $item['quantity']) {
                return response()->json([
                    'message' => "Stock insuffisant pour " . $product->name
                ], 400);
            }

            $product->quantity -= $item['quantity'];
            $product->save();
        }

        $order->load(['user', 'payment']);

        // Email admin
        Mail::to("groupnolmarket@gmail.com")->send(new OrderPaidAdmin($order));

        //Email utilisateur
        if (!empty($user->email)) {
            Mail::to($user->email)->send(new OrderPaidUser($order));
        }
        // SMS
        elseif (!empty($user->phone)) {
            app(FasterMessageService::class)->send(
                $user->phone,
                "Votre commande n°{$order->reference} a été validée. Vous serez livrés d'ici peu et nous vous recommandons de suivre le statut de la commande dans le menu 'Mon compte' sur notre plateforme. Nol Market vous remercie pour votre achat. Info: 0165002800"
            );
        }

        return response()->json([
            'message' => 'Paiement validé et commande enregistrée',
            'order_id' => $order->id
        ], 200);
    }
}
