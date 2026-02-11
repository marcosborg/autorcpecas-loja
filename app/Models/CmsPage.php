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
        'show_in_footer_menu',
        'open_in_footer_popup',
        'footer_menu_label',
        'is_published',
        'sort_order',
        'published_at',
    ];

    protected $casts = [
        'show_contact_button' => 'boolean',
        'show_in_footer_menu' => 'boolean',
        'open_in_footer_popup' => 'boolean',
        'is_published' => 'boolean',
        'sort_order' => 'integer',
        'published_at' => 'datetime',
    ];
}
