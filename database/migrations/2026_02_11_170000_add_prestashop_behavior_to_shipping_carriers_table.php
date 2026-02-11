<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_carriers', function (Blueprint $table): void {
            $table->boolean('need_range')->default(true)->after('is_pickup');
            $table->unsignedTinyInteger('range_behavior')->default(1)->after('need_range');
            $table->boolean('is_free')->default(false)->after('range_behavior');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_carriers', function (Blueprint $table): void {
            $table->dropColumn(['need_range', 'range_behavior', 'is_free']);
        });
    }
};

