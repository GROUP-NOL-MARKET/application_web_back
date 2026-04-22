<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;

class ProductController extends Controller
{
    // Instance Cloudinary réutilisable
    private function cloudinary(): Cloudinary
    {
        return new Cloudinary(
            Configuration::instance([
                'cloud' => [
                    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                    'api_key'    => env('CLOUDINARY_API_KEY'),
                    'api_secret' => env('CLOUDINARY_API_SECRET'),
                ],
                'url' => ['secure' => true]
            ])
        );
    }

    //  Nettoyer le nom du fichier pour Cloudinary
    private function cleanPublicId(string $filename): string
    {
        return strtolower(
            preg_replace('/[^a-zA-Z0-9_\-]/', '_',
                preg_replace('/\s+/', '_',
                    pathinfo($filename, PATHINFO_FILENAME)
                )
            )
        );
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name'          => 'required|string|max:255',
            'reference'     => 'nullable|string|max:255',
            'price'         => 'required|numeric',
            'quantity'      => 'nullable|integer',
            'family'        => 'required|string|max:255',
            'category'      => 'required|string|max:255',
            'sous_category' => 'required|string|max:255',
            'description'   => 'nullable|string',
            'disponibility' => 'required|string|max:15',
            'image'         => 'nullable|image|mimes:jpeg,png,jpg,webp,avif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $filename  = $request->file('image')->getClientOriginalName();
            $cleanName = $this->cleanPublicId($filename);

            $result = $this->cloudinary()->uploadApi()->upload(
                $request->file('image')->getRealPath(),
                [
                    'folder'    => 'products',
                    'public_id' => $cleanName,
                ]
            );

            $validatedData['image'] = $result['public_id'];
        }

        $validatedData['selled'] = 0;
        $validatedData['reste']  = 0;

        $product = Product::create($validatedData);

        return response()->json([
            'message' => 'Produit créé avec succès',
            'product' => $product
        ]);
    }

    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'name'          => 'required|string|max:255',
            'reference'     => 'nullable|string|max:255',
            'price'         => 'required|numeric',
            'quantity'      => 'nullable|integer',
            'family'        => 'required|string|max:255',
            'category'      => 'required|string|max:255',
            'sous_category' => 'required|string|max:255',
            'description'   => 'nullable|string',
            'disponibility' => 'required|string|max:15',
            'image'         => 'nullable|file|mimes:jpeg,png,jpg,webp,avif|max:2048',
        ]);

        $product = Product::findOrFail($id);

        if ($request->hasFile('image')) {
            //  Supprimer l'ancienne image sur Cloudinary
            if ($product->image && str_contains($product->image, 'products/')) {
                $this->cloudinary()->uploadApi()->destroy($product->image);
            }

            $filename  = $request->file('image')->getClientOriginalName();
            $cleanName = $this->cleanPublicId($filename);

            $result = $this->cloudinary()->uploadApi()->upload(
                $request->file('image')->getRealPath(),
                [
                    'folder'    => 'products',
                    'public_id' => $cleanName,
                ]
            );

            $validatedData['image'] = $result['public_id'];
        }

        $quantity = $validatedData['quantity'] ?? $product->quantity;
        $validatedData['reste'] = $quantity - ($product->selled ?? 0);

        $product->update($validatedData);

        return response()->json([
            'message' => 'Produit mis à jour avec succès',
            'product' => $product
        ]);
    }

    public function delete($id)
    {
        $product = Product::findOrFail($id);

        //  Supprimer l'image sur Cloudinary
        if ($product->image && str_contains($product->image, 'products/')) {
            $this->cloudinary()->uploadApi()->destroy($product->image);
        }

        $product->delete();

        return response()->json(['message' => 'Produit supprimé avec succès']);
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
        $category = $request->query('category');
        $limit = intval($request->query('limit', 10));
        $popular = $request->boolean('popular');

        $query = Product::query();

        // PRODUITS POPULAIRES
        if ($popular || strtoupper($category) === 'POPULAIRES') {
            $query->where('is_popular', true);
        }
        // CATÉGORIE CLASSIQUE
        elseif ($category) {
            $query->whereRaw(
                'LOWER(category) LIKE ?',
                ['%' . strtolower($category) . '%']
            );
        } else {
            return response()->json([
                'message' => "Le paramètre 'category' ou 'popular' est requis."
            ], 400);
        }

        $products = $query
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'new_price' => $product->new_price,
                    'image' => $product->image
                        // ? asset('storage/' . $product->image)
                        // : asset('placeholder.png'),
                ];
            });

        return response()->json([
            'data' => $products
        ]);
    }


    public function togglePopular($id)
    {
        $product = Product::findOrFail($id);

        $product->is_popular = !$product->is_popular;
        $product->save();

        return response()->json([
            'message' => 'Statut populaire mis à jour',
            'is_popular' => $product->is_popular,
        ]);
    }



    /**
     * Afficher un produit
     */
    public function show($id)
    {
        return response()->json(Product::findOrFail($id));
    }

    public function bestByCategory(Request $request)
    {
        $limit = intval($request->query('limit', 3));

        // Liste des catégories (tu peux aussi les récupérer depuis la DB)
        $categories = [
            'Produits Locaux',
            'Electroménager',
            'Epicerie',
            'Boissons',
            'Droguerie',
            'Produits Frais',
        ];

        $result = [];

        foreach ($categories as $category) {
            $result[$category] = Product::where('category', $category)
                ->orderBy('selled', 'desc')
                ->limit($limit)
                ->get();
        }

        return response()->json([
            'data' => $result
        ]);
    }
}