<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'family',
        'category',
        'sous_category',
        'is_popular',
        'disponibility',
        'image',
        'quantity',
        'selled',
        'reste',
        'description',
        'reference',
    ];
    public function image()
    {
        return $this->hasOne(ProductImage::class);
    }
     protected $casts = [
        'is_popular' => 'boolean',

    ];
}
