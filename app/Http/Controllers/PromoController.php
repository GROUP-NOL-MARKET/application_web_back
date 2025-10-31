<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class PromoController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $promos = Promo::with('product')->where('start_at', '<=', $now)
            ->where('end_at', '>=', $now)->orderByDesc('created_at')->paginate(20);
        return response()->json($promos);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'initial_price' => 'required|numeric|min:0',
            'new_price' => 'required|numeric|min:0|lt:initial_price',
            'pourcentage_vendu' => 'required|numeric|min:1',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
        ]);

        // Optionnel : override initial_price if not provided from product
        if (empty($data['initial_price'])) {
            $product = Product::findOrFail($data['product_id']);
            $data['initial_price'] = $product->price;
        }

        if (Promo::where('product_id', $data['product_id'])->where('active', true)->exists()) {
            return response()->json(['message' => 'Il existe déjà une promotion active pour ce produit.'], 422);
        }


        $promo = Promo::create(array_merge($data, ['active' => true]));

        $promo->load('product');

        return response()->json($promo, Response::HTTP_CREATED);
    }

    public function show($id)
    {
        $promo = Promo::with('product')->findOrFail($id);
        return response()->json($promo);
    }

    public function update(Request $request, $id)
    {
        $promo = Promo::findOrFail($id);

        $data = $request->validate([
            'new_price' => 'nullable|numeric|min:0|lt:initial_price',
            'initial_price' => 'nullable|numeric|min:0',
            'pourcentage_vendu' => 'nullable|numeri|min:1',
            'active' => 'nullable|boolean',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
        ]);

        $promo->fill($data);

        // si new_price a changé, recalculer éventuellement pourcentage_vendu ou autres
        $promo->save();
        $promo->load('product');

        return response()->json($promo);
    }

    public function destroy($id)
    {
        $promo = Promo::findOrFail($id);
        $promo->delete();
        return response()->json(['message' => 'Supprimé']);
    }
}
