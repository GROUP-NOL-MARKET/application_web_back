<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\SousCategory;
use App\Models\Product;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $term = trim($request->query('q'));
        if (!$term) {
            return response()->json(['error' => 'Le terme de recherche est requis.'], 400);
        }
        // Recherche dans les produits
        $products = Product::where('name', 'LIKE', "%{$term}%")->get();
        if ($products->count() > 0) {
            return response()->json([
                'type' => 'product',
                'data' => $products,
            ]);
        }

        // Rien trouvé
        return response()->json([
            'type' => 'none',
            'message' => 'Aucun résultat trouvé pour votre recherche.',
        ]);
    }
}
