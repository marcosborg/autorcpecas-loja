<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 80)->default('Morada');
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('phone', 40)->nullable();
            $table->string('company', 180)->nullable();
            $table->string('vat_number', 60)->nullable();
            $table->string('address_line1', 255);
            $table->string('address_line2', 255)->nullable();
            $table->string('postal_code', 30);
            $table->string('city', 120);
            $table->string('state', 120)->nullable();
            $table->string('country_iso2', 2)->default('PT');
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('open')->index();
            $table->string('currency', 3)->default('EUR');
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->string('product_key', 120)->index();
            $table->string('reference', 120)->nullable();
            $table->string('title', 255);
            $table->decimal('unit_price_ex_vat', 10, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('weight_kg', 8, 3)->default(1);
            $table->json('product_payload')->nullable();
            $table->timestamps();
            $table->unique(['cart_id', 'product_key']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('order_number', 40)->unique();
            $table->string('status', 40)->default('awaiting_payment')->index();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('vat_rate', 5, 2)->default(23.00);
            $table->decimal('subtotal_ex_vat', 10, 2)->default(0);
            $table->decimal('shipping_ex_vat', 10, 2)->default(0);
            $table->decimal('payment_fee_ex_vat', 10, 2)->default(0);
            $table->decimal('total_ex_vat', 10, 2)->default(0);
            $table->decimal('total_inc_vat', 10, 2)->default(0);
            $table->json('shipping_address_snapshot');
            $table->json('billing_address_snapshot');
            $table->json('shipping_method_snapshot')->nullable();
            $table->json('payment_method_snapshot')->nullable();
            $table->text('customer_note')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('product_key', 120)->index();
            $table->string('reference', 120)->nullable();
            $table->string('title', 255);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price_ex_vat', 10, 2)->default(0);
            $table->decimal('line_total_ex_vat', 10, 2)->default(0);
            $table->decimal('weight_kg', 8, 3)->default(1);
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('status', 40)->index();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('customer_addresses');
    }
};

