<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'featured_image_path',
        'content',
        'google_maps_embed_url',
        'show_contact_button',
        'contact_button_label',
        'is_published',
        'sort_order',
        'published_at',
    ];

    protected $casts = [
        'show_contact_button' => 'boolean',
        'is_published' => 'boolean',
        'sort_order' => 'integer',
        'published_at' => 'datetime',
    ];
}
