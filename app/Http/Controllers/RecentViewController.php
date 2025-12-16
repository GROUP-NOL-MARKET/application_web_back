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
            'expires_at' => Carbon::now()->addDays(14),
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

        // Récupère le nombre par page depuis la requête (par défaut 8)
        $perPage = $request->query('per_page', 8);

        $views = RecentView::with('product')
            ->where('user_id', $user->id)
            ->orderByDesc('viewed_at')
            ->paginate($perPage);

        return response()->json($views);
    }
}
