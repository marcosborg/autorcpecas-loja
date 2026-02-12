<?php

namespace Tests\Unit;

use App\Services\Tax\VatValidationService;
use Tests\TestCase;

class VatValidationServiceTest extends TestCase
{
    public function test_valid_portuguese_nif_is_accepted_without_vies_lookup(): void
    {
        $base = '20536494';
        $nif = $base.$this->calculatePtCheckDigit($base);

        $result = app(VatValidationService::class)->validate('PT', $nif);

        $this->assertFalse((bool) $result['is_valid']);
        $this->assertSame('PT'.$nif, $result['vat_number']);
        $this->assertTrue((bool) $result['checked']);
        $this->assertNull($result['error']);
    }

    public function test_invalid_portuguese_nif_is_rejected(): void
    {
        $result = app(VatValidationService::class)->validate('PT', '205364940');

        $this->assertFalse((bool) $result['is_valid']);
        $this->assertSame('NIF portugues com formato invalido.', $result['error']);
    }

    private function calculatePtCheckDigit(string $firstEightDigits): int
    {
        $sum = 0;
        for ($i = 0; $i < 8; $i++) {
            $sum += ((int) $firstEightDigits[$i]) * (9 - $i);
        }

        $mod = $sum % 11;

        return $mod < 2 ? 0 : 11 - $mod;
    }
}
