<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_key',
        'reference',
        'title',
        'quantity',
        'unit_price_ex_vat',
        'line_total_ex_vat',
        'weight_kg',
        'payload',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_ex_vat' => 'float',
        'line_total_ex_vat' => 'float',
        'weight_kg' => 'float',
        'payload' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

