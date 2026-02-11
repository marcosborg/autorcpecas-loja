<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'currency',
        'vat_rate',
        'subtotal_ex_vat',
        'shipping_ex_vat',
        'payment_fee_ex_vat',
        'total_ex_vat',
        'total_inc_vat',
        'shipping_address_snapshot',
        'billing_address_snapshot',
        'shipping_method_snapshot',
        'payment_method_snapshot',
        'customer_note',
        'placed_at',
    ];

    protected $casts = [
        'vat_rate' => 'float',
        'subtotal_ex_vat' => 'float',
        'shipping_ex_vat' => 'float',
        'payment_fee_ex_vat' => 'float',
        'total_ex_vat' => 'float',
        'total_inc_vat' => 'float',
        'shipping_address_snapshot' => 'array',
        'billing_address_snapshot' => 'array',
        'shipping_method_snapshot' => 'array',
        'payment_method_snapshot' => 'array',
        'placed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }
}

