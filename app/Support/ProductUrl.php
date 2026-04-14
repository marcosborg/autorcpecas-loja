<?php

namespace App\Support;

use Illuminate\Support\Str;

class ProductUrl
{
    /**
     * @param  array<string, mixed>  $product
     */
    public static function lookupKey(array $product): string
    {
        $id = trim((string) ($product['id'] ?? ''));
        if ($id !== '') {
            return $id;
        }

        return trim((string) ($product['reference'] ?? ''));
    }

    public static function lookupKeyFromSegment(string $segment): string
    {
        return self::lookupCandidatesFromSegment($segment)[0] ?? '';
    }

    /**
     * @return list<string>
     */
    public static function lookupCandidatesFromSegment(string $segment): array
    {
        $segment = trim($segment);

        if ($segment === '') {
            return [];
        }

        $candidates = [];

        if (preg_match('/^(\d+)(?:-|$)/', $segment, $matches) === 1) {
            $candidates[] = $matches[1];
        }

        if (preg_match('/^\d+-(.+)$/', $segment, $matches) === 1) {
            $suffix = trim((string) $matches[1]);
            if ($suffix !== '') {
                $candidates[] = $suffix;
            }
        }

        $candidates[] = $segment;

        return array_values(array_unique(array_filter($candidates, static fn ($value): bool => $value !== '')));
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public static function pathSegment(array $product): string
    {
        $id = trim((string) ($product['id'] ?? ''));
        $reference = trim((string) ($product['reference'] ?? ''));

        if ($id === '') {
            return $reference;
        }

        if ($reference === '') {
            return $id;
        }

        $slug = Str::slug($reference, '-');

        return $slug !== '' ? "{$id}-{$slug}" : $id;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public static function url(array $product): string
    {
        return url('/loja/produtos/'.rawurlencode(self::pathSegment($product)));
    }
}
