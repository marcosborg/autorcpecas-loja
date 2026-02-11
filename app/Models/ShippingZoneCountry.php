<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingZoneCountry extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_zone_id',
        'country_iso2',
    ];

    protected $casts = [
        'shipping_zone_id' => 'integer',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}

