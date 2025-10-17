<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RecentView;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class RecentViewController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        // Supprime les anciennes vues du même produit pour éviter les doublons
        RecentView::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->delete();

        // Enregistre la nouvelle vue
        $recentView = RecentView::create([
            'user_id' => $user->id,
            'product_id' => $request->product_id,
            'viewed_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addDays(14), // expire dans 2 semaines
        ]);

        return response()->json([
            'message' => 'Produit vu enregistré avec succès',
            'data' => $recentView,
        ]);
    }
    public function index(Request $request)
    {
        $user = $request->user();

        // Supprime les vues expirées automatiquement
        RecentView::where('expires_at', '<', Carbon::now())->delete();
        $views = RecentView::with('product')
            ->where('user_id', $user->id)
            ->orderByDesc('viewed_at')
            ->take(20)
            ->get();

        return response()->json(['data' => $views]);
    }
}