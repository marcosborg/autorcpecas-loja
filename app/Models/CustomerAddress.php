<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'first_name',
        'last_name',
        'phone',
        'phone_country_code',
        'company',
        'vat_number',
        'vat_country_iso2',
        'vat_is_valid',
        'vat_validated_at',
        'address_line1',
        'address_line2',
        'postal_code',
        'city',
        'state',
        'country_iso2',
        'zone_code',
        'is_default_shipping',
        'is_default_billing',
    ];

    protected $casts = [
        'is_default_shipping' => 'boolean',
        'is_default_billing' => 'boolean',
        'vat_is_valid' => 'boolean',
        'vat_validated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_iso2', 'iso2');
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'phone_country_code' => $this->phone_country_code,
            'company' => $this->company,
            'vat_number' => $this->vat_number,
            'vat_country_iso2' => $this->vat_country_iso2,
            'vat_is_valid' => $this->vat_is_valid,
            'vat_validated_at' => optional($this->vat_validated_at)->toIso8601String(),
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'state' => $this->state,
            'country_iso2' => $this->country_iso2,
            'zone_code' => $this->zone_code,
        ];
    }
}
