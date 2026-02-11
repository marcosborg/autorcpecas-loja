<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('position')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('shipping_zone_countries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->char('country_iso2', 2)->index();
            $table->timestamps();

            $table->unique(['shipping_zone_id', 'country_iso2'], 'uq_shipping_zone_country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_countries');
        Schema::dropIfExists('shipping_zones');
    }
};

