<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'provider',
        'fee_type',
        'fee_value',
        'active',
        'position',
        'meta',
    ];

    protected $casts = [
        'fee_value' => 'float',
        'active' => 'boolean',
        'position' => 'integer',
        'meta' => 'array',
    ];

    public function carriers(): BelongsToMany
    {
        return $this->belongsToMany(ShippingCarrier::class, 'payment_method_shipping_carrier');
    }
}

