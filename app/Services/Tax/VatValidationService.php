<?php

namespace App\Services\Tax;

use Illuminate\Support\Facades\Http;

class VatValidationService
{
    /**
     * @return array{
     *   vat_number: string,
     *   vat_country_iso2: string|null,
     *   is_valid: bool|null,
     *   checked: bool,
     *   error: string|null
     * }
     */
    public function validate(string $countryIso2, ?string $vatInput): array
    {
        $countryIso2 = mb_strtoupper(trim($countryIso2), 'UTF-8');
        $raw = mb_strtoupper(trim((string) $vatInput), 'UTF-8');
        $raw = preg_replace('/\s+/', '', $raw) ?? '';

        if ($raw === '') {
            return [
                'vat_number' => '',
                'vat_country_iso2' => null,
                'is_valid' => null,
                'checked' => false,
                'error' => null,
            ];
        }

        $prefix = substr($raw, 0, 2);
        $hasPrefix = ctype_alpha($prefix);
        $vatCountry = $hasPrefix ? $prefix : $countryIso2;
        $vatNumber = $hasPrefix ? substr($raw, 2) : $raw;
        $vatNumber = preg_replace('/[^A-Z0-9]/', '', $vatNumber) ?? '';

        if ($vatCountry === 'PT') {
            if (! $this->isValidPortugueseNif($vatNumber)) {
                return [
                    'vat_number' => $vatCountry.$vatNumber,
                    'vat_country_iso2' => $vatCountry,
                    'is_valid' => false,
                    'checked' => true,
                    'error' => 'NIF portugues com formato invalido.',
                ];
            }

            // For Portuguese personal/company NIF, checksum validation is enough.
            // It may be valid for invoicing but never grants VAT exemption by itself.
            return [
                'vat_number' => $vatCountry.$vatNumber,
                'vat_country_iso2' => $vatCountry,
                'is_valid' => false,
                'checked' => true,
                'error' => null,
            ];
        }

        try {
            $isValid = class_exists(\SoapClient::class)
                ? $this->checkViaSoapExtension($vatCountry, $vatNumber)
                : $this->checkViaHttpSoap($vatCountry, $vatNumber);

            return [
                'vat_number' => $vatCountry.$vatNumber,
                'vat_country_iso2' => $vatCountry,
                'is_valid' => $isValid,
                'checked' => true,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'vat_number' => $vatCountry.$vatNumber,
                'vat_country_iso2' => $vatCountry,
                'is_valid' => null,
                'checked' => false,
                'error' => 'Falha na validacao VIES: '.$e->getMessage(),
            ];
        }
    }

    private function isValidPortugueseNif(string $nif): bool
    {
        if (! preg_match('/^\d{9}$/', $nif)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 8; $i++) {
            $sum += ((int) $nif[$i]) * (9 - $i);
        }

        $mod = $sum % 11;
        $check = $mod < 2 ? 0 : 11 - $mod;

        return $check === (int) $nif[8];
    }

    private function checkViaSoapExtension(string $vatCountry, string $vatNumber): bool
    {
        $client = new \SoapClient(
            'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl',
            [
                'trace' => false,
                'exceptions' => true,
                'connection_timeout' => 10,
                'cache_wsdl' => WSDL_CACHE_MEMORY,
            ]
        );

        $response = $client->checkVat([
            'countryCode' => $vatCountry,
            'vatNumber' => $vatNumber,
        ]);

        return (bool) ($response->valid ?? false);
    }

    private function checkViaHttpSoap(string $vatCountry, string $vatNumber): bool
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:ec.europa.eu:taxud:vies:services:checkVat:types">
  <soap:Body>
    <urn:checkVat>
      <urn:countryCode>{$vatCountry}</urn:countryCode>
      <urn:vatNumber>{$vatNumber}</urn:vatNumber>
    </urn:checkVat>
  </soap:Body>
</soap:Envelope>
XML;

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => '',
        ])->timeout(12)->send('POST', 'https://ec.europa.eu/taxation_customs/vies/services/checkVatService', [
            'body' => $xml,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('VIES indisponivel (HTTP '.$response->status().').');
        }

        $parsed = @simplexml_load_string((string) $response->body());
        if ($parsed === false) {
            throw new \RuntimeException('Resposta VIES invalida.');
        }

        $bodyNodes = $parsed->xpath('//*[local-name()="Body"]');
        if (! is_array($bodyNodes) || count($bodyNodes) === 0) {
            throw new \RuntimeException('Body SOAP vazio.');
        }

        $validNode = $bodyNodes[0]->xpath('.//*[local-name()="valid"]');
        if (! is_array($validNode) || count($validNode) === 0) {
            $faultNode = $bodyNodes[0]->xpath('.//*[local-name()="faultstring"]');
            $fault = is_array($faultNode) && isset($faultNode[0]) ? trim((string) $faultNode[0]) : '';
            throw new \RuntimeException($fault !== '' ? $fault : 'Campo valid ausente na resposta VIES.');
        }

        $raw = mb_strtolower(trim((string) $validNode[0]), 'UTF-8');

        return $raw === 'true' || $raw === '1';
    }
}
