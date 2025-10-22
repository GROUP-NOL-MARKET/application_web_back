<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('limit', 3);

        $orders = Auth::user()
            ->orders()
            ->with('items.product')
            ->paginate($perPage);

        return response()->json($orders);
    }



    public function create(Request $request)
    {
        $request->validate([
            'produits' => 'required|array',
            'total' => 'required|numeric',
            'statut' => 'required|string',
        ]);

        $order = new Order();
        $order->user_id = $request->user()->id;
        $order->produits = json_encode($request->produits);
        $order->total = $request->total;
        $order->status = $request->statut;
        $order->save();

        return response()->json(['message' => 'Commande enregistrée avec succès'], 201);
    }


    public function show($id)
    {
        $order = Auth::user()->orders()->with('items.product')->findOrFail($id);
        return $order;
    }
    public function dashboard()
    {
        // Récupération de toutes les commandes
        $orders = Order::with('user')->latest()->get();

        // Statistiques globales
        $stats = [
            'completed' => $orders->where('status', 'completed')->count(),
            'confirmed' => $orders->where('status', 'confirmed')->count(),
            'deleted'   => $orders->where('status', 'deleted')->count(),
            'found'     => $orders->count(),
            'product_views_rate' => 75, // Valeur temporaire
            'cart_abandon_rate' => 25,  // Valeur temporaire
        ];

        return response()->json([
            'stats' => $stats,
            'orders' => $orders,
        ]);
    }
}