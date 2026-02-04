<?php

namespace App\Services\Catalog;

use Illuminate\Pagination\LengthAwarePaginator;

interface CatalogProvider
{
    /**
     * @return list<array{slug: string, name: string, count: int|null}>
     */
    public function categories(): array;

    public function totalProducts(): int;

    /**
     * @return array{categoryName: string, paginator: LengthAwarePaginator, meta?: array<string, mixed>}
     */
    public function productsByCategory(string $categorySlug, int $page = 1, int $perPage = 24): array;

    /**
     * @return array<string, mixed>|null
     */
    public function product(string $idOrReference): ?array;

    public function search(string $query, int $page = 1, int $perPage = 24): LengthAwarePaginator;

    /**
     * @return list<array<string, mixed>>
     */
    public function randomProducts(int $count = 24): array;

    /**
     * @return list<array{slug: string, name: string}>
     */
    public function modelsForMakeSlug(string $makeSlug): array;

    /**
     * @return array<string, mixed>|null
     */
    public function totalProductsBreakdown(): ?array;
}
