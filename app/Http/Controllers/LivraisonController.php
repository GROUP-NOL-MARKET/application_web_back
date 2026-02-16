<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Payment;
use App\Mail\OrderPaidAdmin;
use App\Mail\OrderLivraisonUser;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Services\FasterMessageService;

class LivraisonController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|string|in:livraison',
            'amount' => 'required|numeric|min:0',
            'cart' => 'required|array|min:1',
            'cart.*.id' => 'required|exists:products,id',
            'cart.*.quantity' => 'required|integer|min:1',
            'address' => 'required|string',
            'phone' => 'required|string',
        ]);

        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        // Générer une référence unique
        $reference = 'LIV-' . strtoupper(uniqid());

        // Vérifier la disponibilité des produits
        foreach ($request->cart as $item) {
            $product = Product::find($item['id']);

            if (!$product) {
                return response()->json([
                    'message' => "Produit avec l'ID {$item['id']} introuvable"
                ], 404);
            }

            if ($product->quantity < $item['quantity']) {
                return response()->json([
                    'message' => "Stock insuffisant pour {$product->name}. Disponible: {$product->quantity}, demandé: {$item['quantity']}"
                ], 400);
            }
        }

        // Créer la commande avec statut "en attente de livraison"
        $order = Order::create([
            'user_id' => $user->id,
            'produits' => $request->cart,
            'reference' => $reference,
            'total' => $request->amount,
            'status' => 'en_attente', // ou 'validee' selon votre logique
            'payment_status' => 'pending', // Paiement en attente
            'payment_method' => 'livraison',
            'delivery_address' => $request->address,
            'delivery_phone' => $request->phone,
        ]);

        // Enregistrer le paiement
        Payment::create([
            'reference_id' => $reference,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'email' => $user->email,
            'transaction_id' => $reference,
            'products' => json_encode($request->cart),
            'amount' => $request->amount,
            'status' => 'pending',
            'method' => 'Livraison',
            'phone' => $request->phone,
            'payload' => [
                'address' => $request->address,
                'payment_type' => 'cash_on_delivery'
            ],
        ]);

        // Décrémenter le stock
        foreach ($request->cart as $item) {
            $product = Product::find($item['id']);
            $product->quantity -= $item['quantity'];
            $product->save();
        }

        // Charger les relations
        $order->load(['user', 'payment']);

        // Email admin avec indication paiement à la livraison
        try {
            Mail::to("groupnolmarket@gmail.com")->send(new OrderPaidAdmin($order));
        } catch (\Exception $e) {
            \Log::error('Erreur envoi email admin: ' . $e->getMessage());
        }

        // Email ou SMS utilisateur
        if (!empty($user->email)) {
            try {
                Mail::to($user->email)->send(new OrderLivraisonUser($order));
            } catch (\Exception $e) {
                \Log::error('Erreur envoi email user: ' . $e->getMessage());
            }
        } elseif (!empty($user->phone)) {
            try {
                app(FasterMessageService::class)->send(
                    $user->phone,
                    "Votre commande n°{$order->reference} a été enregistrée. Paiement à la livraison. Montant: {$request->amount} FCFA. Adresse: {$request->address}. Info: 0165002800"
                );
            } catch (\Exception $e) {
                \Log::error('Erreur envoi SMS: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Commande enregistrée avec succès. Paiement à la livraison.',
            'order' => [
                'id' => $order->id,
                'reference' => $order->reference,
                'total' => $order->total,
                'payment_method' => 'livraison',
                'status' => $order->status
            ]
        ], 201);
    }
}
