<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    //
    public function store(Request $request)
    {
        $order = Order::create([
            // 'user_id' => auth()->id(),
            'total_price' => 0, // sera calculé
            'status' => 'pending',
        ]);

        $total = 0;
        foreach ($request->items as $item) {
            $order->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ]);
            $total += $item['quantity'] * $item['price'];
        }

        $order->update(['total_price' => $total]);

        return response()->json($order->load('items'), 201);
    }
}
