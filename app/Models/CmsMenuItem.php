<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsMenuItem extends Model
{
    protected $fillable = [
        'label',
        'link_type',
        'url',
        'cms_page_id',
        'open_in_new_tab',
        'is_button',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'cms_page_id' => 'integer',
        'open_in_new_tab' => 'boolean',
        'is_button' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'cms_page_id');
    }

    public function resolvedUrl(): string
    {
        if ($this->link_type === 'cms_page' && $this->page?->slug) {
            return url('/pagina/'.$this->page->slug);
        }

        $url = trim((string) ($this->url ?? ''));
        if ($url === '') {
            return '#';
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        return url($url);
    }
}
