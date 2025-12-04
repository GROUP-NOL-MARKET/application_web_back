<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function show($orderId)
    {
        $payment = Payment::where('order_id', $orderId)->firstOrFail();
        return response()->json($payment);
    }
    public function index(Request $request)
    {
        $query = Payment::query();

        // Tri
        if ($request->has('sort')) {
            $query->orderBy('created_at', $request->sort === 'asc' ? 'asc' : 'desc');
        }

        // Filtre par dates
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

        // Pagination
        $perPage = $request->get('per_page', 10);

        return response()->json($query->paginate($perPage));
    }
}