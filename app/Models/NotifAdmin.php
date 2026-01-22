<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotifAdmin extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'image',
        'title',
        'content',
        'sender',
        'type',
        'can_act',
        'related_id',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
