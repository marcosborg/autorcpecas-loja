<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingCarrier extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'rate_basis',
        'transit_delay',
        'free_shipping_over_ex_vat',
        'is_pickup',
        'active',
        'position',
    ];

    protected $casts = [
        'free_shipping_over_ex_vat' => 'float',
        'is_pickup' => 'boolean',
        'active' => 'boolean',
        'position' => 'integer',
    ];

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }

    public function paymentMethods(): BelongsToMany
    {
        return $this->belongsToMany(PaymentMethod::class, 'payment_method_shipping_carrier');
    }
}

