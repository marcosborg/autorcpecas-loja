<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['iso2' => 'PT', 'name' => 'Portugal', 'phone_code' => '+351', 'is_eu' => true, 'position' => 1],
            ['iso2' => 'ES', 'name' => 'Espanha', 'phone_code' => '+34', 'is_eu' => true, 'position' => 2],
            ['iso2' => 'FR', 'name' => 'Franca', 'phone_code' => '+33', 'is_eu' => true, 'position' => 3],
            ['iso2' => 'DE', 'name' => 'Alemanha', 'phone_code' => '+49', 'is_eu' => true, 'position' => 4],
            ['iso2' => 'IT', 'name' => 'Italia', 'phone_code' => '+39', 'is_eu' => true, 'position' => 5],
            ['iso2' => 'BE', 'name' => 'Belgica', 'phone_code' => '+32', 'is_eu' => true, 'position' => 6],
            ['iso2' => 'NL', 'name' => 'Paises Baixos', 'phone_code' => '+31', 'is_eu' => true, 'position' => 7],
            ['iso2' => 'LU', 'name' => 'Luxemburgo', 'phone_code' => '+352', 'is_eu' => true, 'position' => 8],
            ['iso2' => 'IE', 'name' => 'Irlanda', 'phone_code' => '+353', 'is_eu' => true, 'position' => 9],
            ['iso2' => 'AT', 'name' => 'Austria', 'phone_code' => '+43', 'is_eu' => true, 'position' => 10],
            ['iso2' => 'PL', 'name' => 'Polonia', 'phone_code' => '+48', 'is_eu' => true, 'position' => 11],
            ['iso2' => 'CZ', 'name' => 'Chequia', 'phone_code' => '+420', 'is_eu' => true, 'position' => 12],
            ['iso2' => 'SE', 'name' => 'Suecia', 'phone_code' => '+46', 'is_eu' => true, 'position' => 13],
            ['iso2' => 'DK', 'name' => 'Dinamarca', 'phone_code' => '+45', 'is_eu' => true, 'position' => 14],
            ['iso2' => 'FI', 'name' => 'Finlandia', 'phone_code' => '+358', 'is_eu' => true, 'position' => 15],
            ['iso2' => 'NO', 'name' => 'Noruega', 'phone_code' => '+47', 'is_eu' => false, 'position' => 16],
            ['iso2' => 'CH', 'name' => 'Suica', 'phone_code' => '+41', 'is_eu' => false, 'position' => 17],
            ['iso2' => 'GB', 'name' => 'Reino Unido', 'phone_code' => '+44', 'is_eu' => false, 'position' => 18],
        ];

        foreach ($rows as $row) {
            Country::query()->updateOrCreate(
                ['iso2' => $row['iso2']],
                [
                    'name' => $row['name'],
                    'phone_code' => $row['phone_code'],
                    'is_eu' => $row['is_eu'],
                    'active' => true,
                    'position' => $row['position'],
                ]
            );
        }
    }
}
