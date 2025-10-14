<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;

class AdminStatsController extends Controller
{
    public function index(Request $request)
    {

        $revenus = Order::where('status', 'en_cours')->sum('total');
        $pertes = Order::where('status', 'livrée')->sum('total');
        $commandes = Order::count();

        $ventes_mensuelles = Order::selectRaw('MONTH(created_at) as mois, SUM(total) as total')
            ->groupBy('mois')
            ->orderBy('mois')
            ->get();

        return response()->json([
            'revenus' => $revenus,
            'pertes' => $pertes,
            'commandes' => $commandes,
            'ventes_mensuelles' => $ventes_mensuelles,
        ]);
    }
}
