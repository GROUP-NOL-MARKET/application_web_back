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
}
