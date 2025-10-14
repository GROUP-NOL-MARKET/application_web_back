<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'reference' => 'nullable|string|max:255',
            'price' => 'required|numeric',
            'quantity' => 'required|integer',
            'family' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'sous_category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $product = new Product();
        $product->fill($request->except('images'));
        $product->status = $request->input('status', 'published');
        $product->save();

        // Sauvegarde des images (si présentes)
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $product->images()->create(['path' => $path]);
            }
        }

        return response()->json(['message' => 'Produit ajouté avec succès', 'product' => $product], 201);
    }

    public function edit(Request $request) {}
    public function delete(Request $request, $id)
    {
        //
    }
    public function show(Request $request, $id)
    {
        //
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

        // 🔄 Tri
        if ($request->sort === 'Nom') {
            $query->orderBy('name', 'asc');
        }
        // } elseif ($request->sort === 'Meilleurs ventes') {
        //     $query->orderBy('selled', 'desc');
        // } elseif ($request->sort === 'Pires ventes') {
        //     $query->orderBy('selled', 'asc');
        // }

        $products = $query->paginate(12);

        return response()->json([
            'data' => $products->items(),
            'total_pages' => $products->lastPage(),
            'total' => $products->total(),
            // 'published' => Product::where('status', 'published')->count(),
            // 'deleted' => Product::onlyTrashed()->count(),
            // 'draft' => Product::where('status', 'draft')->count(),
        ]);
    }
}
