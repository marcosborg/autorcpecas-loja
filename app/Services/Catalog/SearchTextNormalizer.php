<?php

namespace App\Services\Catalog;

use Illuminate\Support\Str;

final class SearchTextNormalizer
{
    /** @var array<string, string> */
    private const SEARCH_ALIASES = [
        'parachoques' => 'para choques',
    ];

    /** @var array<string, string> */
    private const MAKE_ALIASES = [
        'vw' => 'volkswagen',
        'volkswagen' => 'volkswagen',
        'mercedes' => 'mercedes benz',
        'mercedesbenz' => 'mercedes benz',
        'mercedes benz' => 'mercedes benz',
        'mercedes benz bbdc' => 'mercedes benz',
        'citroen' => 'citroen',
    ];

    public function normalize(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = Str::ascii(mb_strtolower($value, 'UTF-8'));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        $value = preg_replace_callback(
            '/\b('.implode('|', array_map(static fn (string $alias): string => preg_quote($alias, '/'), array_keys(self::SEARCH_ALIASES))).')\b/',
            static fn (array $match): string => self::SEARCH_ALIASES[$match[1]],
            $value,
        ) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    public function compact(?string $value): string
    {
        return str_replace(' ', '', $this->normalize($value));
    }

    public function makeKey(?string $value): string
    {
        $normalized = $this->normalize($value);
        $compact = str_replace(' ', '', $normalized);

        return self::MAKE_ALIASES[$normalized]
            ?? self::MAKE_ALIASES[$compact]
            ?? $normalized;
    }

    public function canonicalMake(?string $value): string
    {
        return match ($this->makeKey($value)) {
            'volkswagen' => 'Volkswagen',
            'mercedes benz' => 'Mercedes-Benz',
            'citroen' => 'Citroën',
            default => $this->displayName((string) $value),
        };
    }

    public function displayName(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $letters = preg_replace('/[^\pL]+/u', '', $value) ?? '';
        if ($letters !== '' && mb_strtoupper($letters, 'UTF-8') === $letters && mb_strlen($letters, 'UTF-8') <= 6) {
            return $value;
        }

        return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    /** @return list<string> */
    public function terms(?string $value): array
    {
        $normalized = $this->normalize($value);
        if ($normalized === '') {
            return [];
        }

        return array_values(array_unique(array_filter(explode(' ', $normalized))));
    }

    public function ftsQuery(?string $value): string
    {
        return implode(' AND ', array_map(
            static fn (string $term): string => '"'.str_replace('"', '""', $term).'"'.(strlen($term) > 3 ? '*' : ''),
            $this->terms($value),
        ));
    }
}
