<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->boolean('show_in_footer_menu')->default(false)->after('contact_button_label');
            $table->boolean('open_in_footer_popup')->default(false)->after('show_in_footer_menu');
            $table->string('footer_menu_label', 120)->nullable()->after('open_in_footer_popup');
        });
    }

    public function down(): void
    {
        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->dropColumn([
                'show_in_footer_menu',
                'open_in_footer_popup',
                'footer_menu_label',
            ]);
        });
    }
};
