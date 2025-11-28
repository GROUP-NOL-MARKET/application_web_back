<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Publicite extends Model
{
       protected $fillable = ['path','active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    // accessor pour url pleine si utile
    public function getUrlAttribute()
    {
        return url("storage/{$this->path}");
    }
}
