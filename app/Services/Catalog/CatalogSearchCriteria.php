<?php

namespace App\Services\Catalog;

final readonly class CatalogSearchCriteria
{
    public function __construct(
        public string $query = '',
        public string $make = '',
        public string $model = '',
        public string $piece = '',
        public string $sort = 'relevance',
        public int $page = 1,
        public int $perPage = 24,
        public bool $includeFacets = true,
    ) {
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $sort = (string) ($input['sort'] ?? 'relevance');
        if (! in_array($sort, ['relevance', 'price_asc', 'price_desc', 'newest', 'oldest', 'name_asc', 'name_desc'], true)) {
            $sort = 'relevance';
        }

        return new self(
            query: trim((string) ($input['q'] ?? $input['query'] ?? '')),
            make: trim((string) ($input['make'] ?? '')),
            model: trim((string) ($input['model'] ?? '')),
            piece: trim((string) ($input['piece'] ?? '')),
            sort: $sort,
            page: max(1, (int) ($input['page'] ?? 1)),
            perPage: max(1, min(100, (int) ($input['perPage'] ?? 24))),
        );
    }
}
