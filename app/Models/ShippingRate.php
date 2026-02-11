<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_carrier_id',
        'shipping_zone_id',
        'calc_type',
        'range_from',
        'range_to',
        'price_ex_vat',
        'handling_fee_ex_vat',
        'active',
    ];

    protected $casts = [
        'shipping_carrier_id' => 'integer',
        'shipping_zone_id' => 'integer',
        'range_from' => 'float',
        'range_to' => 'float',
        'price_ex_vat' => 'float',
        'handling_fee_ex_vat' => 'float',
        'active' => 'boolean',
    ];

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class, 'shipping_carrier_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}

