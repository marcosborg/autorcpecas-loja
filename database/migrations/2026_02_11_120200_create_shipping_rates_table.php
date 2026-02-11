<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_carrier_id')->constrained('shipping_carriers')->cascadeOnDelete();
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->string('calc_type', 16)->default('price'); // price|weight
            $table->decimal('range_from', 10, 2)->default(0);
            $table->decimal('range_to', 10, 2)->nullable();
            $table->decimal('price_ex_vat', 10, 2)->default(0);
            $table->decimal('handling_fee_ex_vat', 10, 2)->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->index(['shipping_carrier_id', 'shipping_zone_id', 'calc_type'], 'idx_shipping_rates_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};

