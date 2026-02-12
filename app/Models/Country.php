<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = [
        'iso2',
        'name',
        'phone_code',
        'is_eu',
        'active',
        'position',
    ];

    protected $casts = [
        'is_eu' => 'boolean',
        'active' => 'boolean',
        'position' => 'integer',
    ];
}
