<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Enregistrer un nouveau produit
     */
    public function store(Request $request)
    {
        //  Validation des données
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'reference' => 'nullable|string|max:255',
            'price' => 'required|numeric',
            'quantity' => 'nullable|integer',
            'family' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'sous_category' => 'required|string|max:255',
            'description' => 'nullable|string',
            'disponibility' => 'required|string|max:15',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        // Gestion de l'image
        if ($request->hasFile('image')) {
            $validatedData['image'] = $request->file('image')->store('products', 'public');
        }
        $validatedData['selled'] === 0;

        $validatedData['reste'] === 0;

        // Création du produit
        $product = Product::create($validatedData);

        return response()->json([
            'message' => 'Produit créé avec succès',
            'product' => $product
        ]);
    }

    /**
     * Liste des produits avec filtres et pagination
     */
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('reference', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('sous_category')) {
            $query->where('sous_category', $request->sous_category);
        }

        if ($request->sort === 'Nom') {
            $query->orderBy('name', 'asc');
        }

        $products = $query->paginate(24);

        return response()->json([
            'data' => $products->items(),
            'total_pages' => $products->lastPage(),
            'total' => $products->total(),
        ]);
    }


    /**
     * Récupérer un nombre limité de produits d'une sous-catégorie
     */
    public function limited(Request $request)
    {
        $category = $request->query('category', null);
        $limit = intval($request->query('limit', 10));

        if (!$category) {
            return response()->json([
                'message' => "Le paramètre 'category' est requis."
            ], 400);
        }

        $products = Product::whereRaw('LOWER(category) LIKE ?', ['%' . strtolower($category) . '%'])
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $products
        ]);
    }




    /**
     * Afficher un produit
     */
    public function show($id)
    {
        return response()->json(Product::findOrFail($id));
    }

    /**
     * Mettre à jour un produit existant
     */
    public function update(Request $request, $id)
    {
        // Validation
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'reference' => 'nullable|string|max:255',
            'price' => 'required|numeric',
            'quantity' => 'nullable|integer',
            'family' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'sous_category' => 'required|string|max:255',
            'description' => 'nullable|string',
            'disponibility' => 'required|string|max:15',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $product = Product::findOrFail($id);

        // Si une nouvelle image est envoyée
        if ($request->hasFile('image')) {
            // Supprime l’ancienne image si elle existe
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }

            // Stocke la nouvelle image
            $validatedData['image'] = $request->file('image')->store('products', 'public');
        }

        $quantity = $validatedData['quantity'] ?? $product->quantity;
        $selled = $product->selled ?? 0;

        $validatedData['reste'] = $quantity - $selled;

        // Mise à jour du produit
        $product->update($validatedData);

        return response()->json([
            'message' => 'Produit mis à jour avec succès',
            'product' => $product
        ]);
    }

    /**
     * Supprimer un produit
     */
    public function delete($id)
    {
        $product = Product::findOrFail($id);

        // Supprimer aussi l’image si elle existe
        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return response()->json(['message' => 'Produit supprimé avec succès']);
    }
}
