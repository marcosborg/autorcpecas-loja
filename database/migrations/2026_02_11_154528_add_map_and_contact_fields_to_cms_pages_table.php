<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->string('google_maps_embed_url', 1200)->nullable()->after('content');
            $table->boolean('show_contact_button')->default(false)->after('google_maps_embed_url');
            $table->string('contact_button_label', 80)->nullable()->after('show_contact_button');
        });
    }

    public function down(): void
    {
        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->dropColumn([
                'google_maps_embed_url',
                'show_contact_button',
                'contact_button_label',
            ]);
        });
    }
};
