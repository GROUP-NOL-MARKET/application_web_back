<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\User;
use App\Models\Order;
use App\Models\Message;
use App\Models\NotifAdmin;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('limit', 3);

        $orders = Auth::user()
            ->orders()
            ->paginate($perPage);

        return response()->json([
            'data' => $orders->items(),
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'total' => $orders->total(),
        ]);
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
        $orders = Order::with(['user', 'payment'])->latest()->get();

        // Statistiques globales
        $stats = [
            'completed' => $orders->where('status', 'livree')->count(),
            'confirmed' => $orders->where('status', 'validee')->count(),
            'deleted' => $orders->where('status', 'annulee')->count(),
            'found' => $orders->count(),
            'product_views_rate' => 75,
            'cart_abandon_rate' => 25,
        ];

        return response()->json([
            'stats' => $stats,
            'orders' => $orders,
        ]);
    }
    public function updateStatus($id, Request $request)
    {
        $order = Order::findOrFail($id);

        $order->status = $request->status;
        $order->save();

        if ($order) {
            $payment = Payment::where('order_id', $order->id)->first();
            if ($payment) {
                $payment->status = $request->status;
                $payment->save();
            }
        }

        return response()->json(['message' => 'Statut mis à jour']);
    }

    public function cancel(Order $order)
    {

        if ($order->status !== 'validee') {
            return response()->json([
                'message' => 'Cette commande ne peut plus être annulée'
            ], 400);
        }

        $order->update([
            'status' => 'annulee'
        ]);

        return response()->json([
            'message' => 'Commande annulée avec succès'
        ]);
    }
    public function requestRefund(Order $order)
    {
        // Sécurité
        if ($order->status !== 'annulee') {
            return response()->json([
                'message' => 'Le remboursement est possible uniquement pour une commande annulée'
            ], 400);
        }

        if ($order->refund_requested) {
            return response()->json([
                'message' => 'Une demande de remboursement a déjà été envoyée'
            ], 400);
        }

        // Marquer la commande
        $order->update([
            'refund_requested' => true
        ]);

        // Créer le message utilisateur
        Message::create([
            'user_id' => \Auth::user()->id,
            'title' => "Remboursement demandé",
            'sender' => 'Système',
            'content' => "Demande de remboursement pour la commande n°{$order->reference} annulée",
        ]);
        // Créer la notification admin
        $admins = Admin::where('id', 1)->get();

        foreach ($admins as $admin) {
            NotifAdmin::create([
                'admin_id' => $admin->id,
                'title' => "Demande de remboursement",
                'image' => $order->user_id,
                'sender' => 'Système',
                'content' => "Commande n°{$order->reference} – remboursement demandé",
                'type' => 'refund',
                'can_act' => true,
                'related_id' => $order->id
            ]);
        }





        return response()->json([
            'message' => 'Demande de remboursement envoyée'
        ]);
    }
}
