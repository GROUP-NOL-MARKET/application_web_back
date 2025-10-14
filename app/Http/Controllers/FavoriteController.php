<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    // Récupérer tous les favoris de l’utilisateur connecté
    public function index()
    {
        $favorites = Auth::user()
            ->favorites()
            ->with('product') // on charge les infos du produit
            ->get();

        return response()->json($favorites);
    }

    // Ajouter un produit aux favoris
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $favorite = Favorite::firstOrCreate([
            'user_id' => Auth::id(),
            'product_id' => $request->product_id,
        ]);

        return response()->json([
            'message' => 'Produit ajouté aux favoris',
            'favorite' => $favorite->load('product'),
        ], 201);
    }

    // Supprimer un favori
    public function destroy($id)
    {
        $favorite = Auth::user()->favorites()->findOrFail($id);
        $favorite->delete();

        return response()->json([
            'message' => 'Favori supprimé avec succès',
        ]);
    }
}
