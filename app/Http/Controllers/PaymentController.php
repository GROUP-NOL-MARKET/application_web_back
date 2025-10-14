<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }
    public function webhook(Request $request)
    {
        $payload = $request->all();

        if (!isset($payload['event']) || !isset($payload['data'])) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $event = $payload['event'];
        $data = $payload['data'];

        $order = Order::where('transaction_id', $data['id'])->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        if ($event === 'transaction.succeeded') {
            $order->update(['statut' => 'validée']);
        }

        if ($event === 'transaction.failed') {
            $order->update(['statut' => 'échouée']);
        }

        return response()->json(['message' => 'Webhook processed'], 200);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
