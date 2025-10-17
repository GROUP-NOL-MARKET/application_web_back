<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function store(Request $request)
    {
        // Validation des champs
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'reference' => 'nullable|string|max:255',
            'price' => 'required|numeric',
            'quantity' => 'required|integer',
            'family' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'sous_category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'disponibility' => 'required|string|max:15',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        //  Gestion de l'image
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
            $validatedData['image'] = $imagePath; // on ajoute le chemin dans les données validées
        }

        // s Enregistrement du produit
        $product = Product::create($validatedData);

        return response()->json([
            'message' => 'Produit créé avec succès',
            'product' => $product
        ]);
    }

    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->has('search') && $request->search !== '') {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('reference', 'like', '%' . $request->search . '%');
        }

        if ($request->has('category') && $request->category !== '') {
            $query->where('category', $request->category);
        }

        if ($request->has('sous_category') && $request->sous_category !== '') {
            $query->where('sous_category', $request->sous_category);
        }

        if ($request->sort === 'Nom') {
            $query->orderBy('name', 'asc');
        }

        $products = $query->paginate(12);

        return response()->json([
            'data' => $products->items(),
            'total_pages' => $products->lastPage(),
            'total' => $products->total(),
        ]);
    }

    public function show($id)
    {
        return response()->json(Product::findOrFail($id));
    }

    public function delete($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Produit supprimé avec succès']);
    }
}
