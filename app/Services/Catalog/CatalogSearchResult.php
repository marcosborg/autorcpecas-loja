<?php

namespace App\Services\Catalog;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class CatalogSearchResult
{
    /** @param array<string, mixed> $facets */
    public function __construct(
        public LengthAwarePaginator $paginator,
        public array $facets = [],
        public ?string $correctedQuery = null,
        public bool $usedFuzzyFallback = false,
    ) {
    }
}
