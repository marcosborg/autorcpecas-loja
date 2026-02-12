<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table): void {
            $table->string('phone_country_code', 8)->default('+351')->after('phone');
            $table->string('zone_code', 32)->nullable()->after('country_iso2');
            $table->char('vat_country_iso2', 2)->nullable()->after('vat_number');
            $table->boolean('vat_is_valid')->nullable()->after('vat_country_iso2')->index();
            $table->timestamp('vat_validated_at')->nullable()->after('vat_is_valid');
        });
    }

    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table): void {
            $table->dropColumn([
                'phone_country_code',
                'zone_code',
                'vat_country_iso2',
                'vat_is_valid',
                'vat_validated_at',
            ]);
        });
    }
};
