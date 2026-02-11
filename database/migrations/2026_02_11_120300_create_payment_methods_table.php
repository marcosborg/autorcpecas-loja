<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->string('provider', 120)->nullable();
            $table->string('fee_type', 16)->default('none'); // none|fixed|percent
            $table->decimal('fee_value', 10, 4)->default(0);
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('position')->default(0)->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_method_shipping_carrier', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->cascadeOnDelete();
            $table->foreignId('shipping_carrier_id')->constrained('shipping_carriers')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['payment_method_id', 'shipping_carrier_id'], 'uq_payment_carrier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_method_shipping_carrier');
        Schema::dropIfExists('payment_methods');
    }
};

