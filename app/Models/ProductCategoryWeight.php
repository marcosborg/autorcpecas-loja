<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCategoryWeight extends Model
{
    protected $fillable = ['category', 'weight_kg', 'active'];

    protected $casts = [
        'weight_kg' => 'float',
        'active' => 'boolean',
    ];
}
