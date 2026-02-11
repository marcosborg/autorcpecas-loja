<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_key',
        'reference',
        'title',
        'unit_price_ex_vat',
        'quantity',
        'weight_kg',
        'product_payload',
    ];

    protected $casts = [
        'unit_price_ex_vat' => 'float',
        'quantity' => 'integer',
        'weight_kg' => 'float',
        'product_payload' => 'array',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }
}

