<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promo extends Model
{
    protected $fillable = [
        'product_id',
        'initial_price',
        'new_price',
        'active',
        'pourcentage_vendu',
        'start_at',
        'end_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'pourcentage_vendu' => 'integer',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Product::class);
    }
}
