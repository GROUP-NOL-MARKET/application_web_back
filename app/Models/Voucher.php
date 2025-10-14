<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'sub_title',
        'code',
        'valeur',
        'date',
        'until',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
