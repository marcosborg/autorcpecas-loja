<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_carriers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->string('rate_basis', 16)->default('price'); // price|weight
            $table->string('transit_delay', 180)->nullable();
            $table->decimal('free_shipping_over_ex_vat', 10, 2)->nullable();
            $table->boolean('is_pickup')->default(false)->index();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('position')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_carriers');
    }
};

