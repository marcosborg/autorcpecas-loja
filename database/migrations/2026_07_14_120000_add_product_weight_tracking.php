<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_category_weights', function (Blueprint $table): void {
            $table->id();
            $table->string('category')->unique();
            $table->decimal('weight_kg', 8, 3);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->string('weight_source', 32)->nullable()->after('weight_kg');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('weight_source', 32)->nullable()->after('weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('weight_source'));
        Schema::table('cart_items', fn (Blueprint $table) => $table->dropColumn('weight_source'));
        Schema::dropIfExists('product_category_weights');
    }
};
