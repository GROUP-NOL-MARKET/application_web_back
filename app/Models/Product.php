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
        'disponibility',
        'image',
        'description',
        'reference',
    ];
}
