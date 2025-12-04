<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'reference_id',
        'user_id',
        'order_id',
        'transaction_id',
        'amount',
        'status',
        'method',
        "phone",

    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
