<?php

namespace App\Services\TpSoftware;

use App\Services\Catalog\CatalogProvider;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TpSoftwareCatalogService implements CatalogProvider
{
    public function __construct(
        private readonly TpSoftwareClient $client,
        private readonly TpSoftwareIndexStore $indexStore,
    )
    {
    }

    /**
     * Constroi um indice local (cache) com todos os produtos necessarios para
     * filtrar/paginar por marca/modelo sem depender de parametros desconhecidos na API.
     *
     * @return array{total: int, indexed: int, pages: int}
     */
    public function buildIndex(bool $force = false): array
    {
        if (! $force && $this->indexStore->exists() && $this->indexStore->isFreshForCurrentConfig()) {
            $existing = $this->indexStore->load() ?? [];

            return [
                'total' => $this->totalProducts(),
                'indexed' => count($existing),
                'pages' => 0,
            ];
        }

        $pageSize = (int) config('tpsoftware.catalog.index_page_size', 200);
        $pageSize = max(1, min(500, $pageSize));

        $total = $this->totalProducts();
        $pages = $total > 0 ? (int) ceil($total / $pageSize) : 0;

        $products = [];

        for ($page = 1; $page <= max(1, $pages); $page++) {
            $result = $this->inventoryList([
                'limit' => $pageSize,
                'page' => $page,
                'search' => '',
            ], 0, false);

            if (! ($result['ok'] ?? false)) {
                break;
            }

            $items = $this->extractList($result['data'] ?? null);

            if (count($items) === 0) {
                break;
            }

            foreach ($items as $raw) {
                $products[] = $this->normalizeProduct($raw, includeRaw: false);
            }

            if (count($items) < $pageSize) {
                break;
            }
        }

        $ttl = (int) config('tpsoftware.catalog.index_ttl_seconds', 1800);
        $ttl = max(60, $ttl);

        $this->indexStore->save($products, $total);

        return [
            'total' => $total,
            'indexed' => count($products),
            'pages' => $pages,
        ];
    }

    /**
     * @return list<array{slug: string, name: string, count: int|null}>
     */
    public function categories(): array
    {
        if ((bool) config('tpsoftware.catalog.index_enabled', true)) {
            $indexed = $this->indexedProducts();

            if ($indexed !== null) {
                /** @var array<string, string> $names */
                $names = [];

                foreach ($indexed as $p) {
                    $make = (string) ($p['make_name'] ?? $p['category'] ?? '');

                    if ($make !== '') {
                        $make = trim($make);
                        $key = mb_strtolower($make, 'UTF-8');
                        if ($key === '') {
                            continue;
                        }

                        if (! array_key_exists($key, $names)) {
                            $names[$key] = $this->formatMakeName($make);
                        }
                    }
                }

                $cats = [];

                foreach (array_values($names) as $name) {
                    $slug = Str::slug($name);

                    if ($slug === '') {
                        continue;
                    }

                    $cats[] = [
                        'slug' => $slug,
                        'name' => $name,
                        'count' => null,
                    ];
                }

                usort($cats, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

                return $cats;
            }
        }

        $cache = Cache::store((string) config('tpsoftware.cache_store', 'file'));

        return $cache->remember('tpsoftware:catalog:makes', (int) config('tpsoftware.cache_ttl_seconds', 600), function () {
            $limit = (int) config('tpsoftware.catalog.category_scan_limit', 200);
            $maxPages = (int) config('tpsoftware.catalog.category_scan_max_pages', 50);
            $maxSeconds = (int) config('tpsoftware.catalog.category_scan_max_seconds', 20);
            $makeField = (string) config('tpsoftware.catalog.category_field', 'vehicle_make_name');

            $limit = max(1, min(500, $limit));
            $maxPages = max(1, min(500, $maxPages));
            $maxSeconds = max(1, min(120, $maxSeconds));

            $startedAt = microtime(true);

            /** @var array<string, string> $names */
            $names = [];

            for ($page = 1; $page <= $maxPages; $page++) {
                if ((microtime(true) - $startedAt) > $maxSeconds) {
                    break;
                }

                $result = $this->inventoryList([
                    'limit' => $limit,
                    'page' => $page,
                    'search' => '',
                ], 0, false);

                if (! ($result['ok'] ?? false)) {
                    break;
                }

                $items = $this->extractList($result['data'] ?? null);

                if (count($items) === 0) {
                    break;
                }

                foreach ($items as $item) {
                    $value = data_get($item, $makeField);

                    if (is_string($value) && trim($value) !== '') {
                        $value = trim($value);
                        $key = mb_strtolower($value, 'UTF-8');
                        if ($key === '') {
                            continue;
                        }

                        if (! array_key_exists($key, $names)) {
                            $names[$key] = $this->formatMakeName($value);
                        }
                    }
                }

                if (count($items) < $limit) {
                    break;
                }
            }

            $cats = [];

            foreach (array_values($names) as $name) {
                $slug = Str::slug($name);

                if ($slug === '') {
                    continue;
                }

                $cats[] = [
                    'slug' => $slug,
                    'name' => $name,
                    'count' => null,
                ];
            }

            usort($cats, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

            return $cats;
        });
    }

    private function formatMakeName(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $letters = preg_replace('/[^\\pL]+/u', '', $value) ?? '';
        $isAllCaps = $letters !== '' && mb_strtoupper($letters, 'UTF-8') === $letters;

        if ($isAllCaps && mb_strlen($letters, 'UTF-8') <= 6) {
            return $value;
        }

        return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    public function totalProducts(): int
    {
        $result = $this->inventoryList([
            'limit' => 1,
            'page' => 1,
            'search' => '',
        ], 60, true);

        return $this->extractTotalCount($result['data'] ?? null);
    }

    /**
     * @return array{categoryName: string, paginator: LengthAwarePaginator, meta?: array<string, mixed>}
     */
    public function productsByCategory(string $categorySlug, int $page = 1, int $perPage = 24): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $makeName = $this->categoryNameFromSlug($categorySlug);
        $modelSlug = (string) request()->query('model', '');
        $modelName = $modelSlug !== '' ? $this->modelNameFromSlug($categorySlug, $modelSlug) : '';
        $stateSlug = (string) request()->query('state', '');
        $conditionSlug = (string) request()->query('condition', '');
        $priceKey = (string) request()->query('price', '');
        $pieceSlug = (string) request()->query('piece', '');

        $indexed = (bool) config('tpsoftware.catalog.index_enabled', true)
            ? $this->indexedProducts()
            : null;

        if ($indexed === null) {
            throw new \RuntimeException('TP Software: indice de produtos ainda nao foi gerado. Corre: php artisan tpsoftware:index');
        }

        $base = collect($indexed)
            ->filter(function (array $p) use ($makeName, $modelName): bool {
                $make = (string) ($p['make_name'] ?? $p['category'] ?? '');
                $model = (string) ($p['model_name'] ?? '');

                if ($makeName !== '' && strcasecmp($make, $makeName) !== 0) {
                    return false;
                }

                if ($modelName !== '' && strcasecmp($model, $modelName) !== 0) {
                    return false;
                }

                return true;
            })
            ->values();

        $buildUrl = function (array $overrides = []) use ($categorySlug): string {
            $basePath = url('/loja/categorias/'.$categorySlug);
            $query = array_merge(request()->query(), $overrides);

            unset($query['page']);

            foreach (['model', 'state', 'condition', 'price', 'piece', 'perPage'] as $key) {
                if (! array_key_exists($key, $query)) {
                    continue;
                }

                if ($query[$key] === null || $query[$key] === '') {
                    unset($query[$key]);
                }
            }

            return $basePath.(count($query) > 0 ? ('?'.http_build_query($query)) : '');
        };

        $facets = $this->facetsFromProducts($base, $buildUrl);

        $filtered = $base
            ->filter(function (array $p) use ($stateSlug, $conditionSlug, $priceKey, $pieceSlug, $facets): bool {
                if ($stateSlug !== '') {
                    $wanted = $this->facetNameFromSlug($facets['states'] ?? [], $stateSlug);
                    if ($wanted !== '' && strcasecmp((string) ($p['state_name'] ?? ''), $wanted) !== 0) {
                        return false;
                    }
                }

                if ($conditionSlug !== '') {
                    $wanted = $this->facetNameFromSlug($facets['conditions'] ?? [], $conditionSlug);
                    if ($wanted !== '' && strcasecmp((string) ($p['condition_name'] ?? ''), $wanted) !== 0) {
                        return false;
                    }
                }

                if ($priceKey !== '') {
                    $bucket = $this->priceBucketKey($p['price'] ?? null);
                    if ($bucket !== $priceKey) {
                        return false;
                    }
                }

                if ($pieceSlug !== '') {
                    $wanted = $this->facetNameFromSlug($facets['piece_categories'] ?? [], $pieceSlug);
                    if ($wanted !== '' && strcasecmp($this->pieceCategoryName((string) ($p['title'] ?? '')), $wanted) !== 0) {
                        return false;
                    }
                }

                return true;
            })
            ->values();

        $total = $filtered->count();
        $items = $filtered
            ->slice(($page - 1) * $perPage, $perPage)
            ->values()
            ->map(fn (array $p): array => $this->withCoverImage($p))
            ->all();

        return [
            'categoryName' => $makeName,
            'paginator' => new LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $page,
                [
                    'path' => url('/loja/categorias/'.$categorySlug),
                    'query' => request()->query(),
                ],
            ),
            'meta' => [
                'make' => $makeName,
                'model' => $modelName,
                'facets' => $facets,
            ],
        ];
    }

    public function product(string $idOrReference): ?array
    {
        $idOrReference = trim($idOrReference);

        if ($idOrReference === '') {
            return null;
        }

        $indexed = (bool) config('tpsoftware.catalog.index_enabled', true)
            ? $this->indexedProducts()
            : null;

        if ($indexed !== null) {
            foreach ($indexed as $idx => $p) {
                $id = (string) (($p['id'] ?? '') ?: '');
                $ref = (string) (($p['reference'] ?? '') ?: '');

                if ($id === $idOrReference || ($ref !== '' && strcasecmp($ref, $idOrReference) === 0)) {
                    $raw = $this->fetchProductRawByIdOrReference($idOrReference, is_int($idx) ? $idx : null);

                    if (is_array($raw)) {
                        return $this->normalizeProduct($raw, includeRaw: true);
                    }

                    return $this->withCoverImage($p);
                }
            }
        }

        return null;
    }

    public function search(string $query, int $page = 1, int $perPage = 24): LengthAwarePaginator
    {
        $query = trim($query);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        if ($query === '') {
            return new LengthAwarePaginator([], 0, $perPage, $page, ['path' => url('/loja/pesquisa')]);
        }

        $indexed = (bool) config('tpsoftware.catalog.index_enabled', true)
            ? $this->indexedProducts()
            : null;

        if ($indexed === null) {
            throw new \RuntimeException('TP Software: índice de produtos ainda não foi gerado. Corre: php artisan tpsoftware:index');
        }

        if ($this->isLikelyReferenceQuery($query)) {
            $exactIndexed = collect($indexed)
                ->map(fn (array $p, int $idx): array => ['product' => $p, 'index' => $idx])
                ->filter(function (array $row) use ($query): bool {
                    $p = $row['product'] ?? [];
                    $id = trim((string) ($p['id'] ?? ''));
                    $ref = trim((string) ($p['reference'] ?? ''));

                    return $id === $query || ($ref !== '' && strcasecmp($ref, $query) === 0);
                })
                ->values()
                ->all();

            // So valida em "modo exato" quando a query coincide com algum id/ref do indice.
            // Para query parcial (autocomplete, >=3 chars), cai para o filtro substring abaixo.
            if (count($exactIndexed) > 0) {
                $liveMatches = $this->liveSearchExactReference($query);
                $validatedMatches = $this->validateIndexedExactMatches($exactIndexed);

                if (is_array($liveMatches)) {
                    $effective = count($liveMatches) > 0 ? $liveMatches : $validatedMatches;
                    $liveTotal = count($effective);
                    $liveItems = array_slice($effective, ($page - 1) * $perPage, $perPage);

                    return new LengthAwarePaginator($liveItems, $liveTotal, $perPage, $page, [
                        'path' => url('/loja/pesquisa'),
                        'query' => request()->query(),
                    ]);
                }

                if (count($validatedMatches) > 0) {
                    $liveTotal = count($validatedMatches);
                    $liveItems = array_slice($validatedMatches, ($page - 1) * $perPage, $perPage);

                    return new LengthAwarePaginator($liveItems, $liveTotal, $perPage, $page, [
                        'path' => url('/loja/pesquisa'),
                        'query' => request()->query(),
                    ]);
                }
            }
        }

        $q = mb_strtolower($query);

        $matching = collect($indexed)
            ->filter(function (array $p) use ($q): bool {
                $haystacks = [
                    (string) ($p['reference'] ?? ''),
                    (string) ($p['title'] ?? ''),
                    (string) ($p['make_name'] ?? ''),
                    (string) ($p['model_name'] ?? ''),
                ];

                foreach ($haystacks as $haystack) {
                    if ($haystack !== '' && str_contains(mb_strtolower($haystack), $q)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();

        $total = $matching->count();
        $items = $matching
            ->slice(($page - 1) * $perPage, $perPage)
            ->values()
            ->map(fn (array $p): array => $this->withCoverImage($p))
            ->all();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => url('/loja/pesquisa'),
            'query' => request()->query(),
        ]);
    }

    private function isLikelyReferenceQuery(string $query): bool
    {
        $query = trim($query);

        if (mb_strlen($query, 'UTF-8') < 3 || mb_strlen($query, 'UTF-8') > 80) {
            return false;
        }

        // Referencias normalmente nao contem espacos e usam alfanumerico/pontuacao basica.
        return preg_match('/^[A-Za-z0-9._\\/-]+$/', $query) === 1;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function liveSearchExactReference(string $query): ?array
    {
        try {
            $result = $this->inventoryList([
                'limit' => 100,
                'page' => 1,
                'search' => $query,
            ], 0, false);
        } catch (\Throwable) {
            return null;
        }

        if (! ($result['ok'] ?? false)) {
            return null;
        }

        $items = $this->extractList($result['data'] ?? null);
        $normalized = [];
        $seen = [];

        foreach ($items as $raw) {
            if (! is_array($raw) || ! $this->matchesIdOrReference($raw, $query)) {
                continue;
            }

            $product = $this->withCoverImage($this->normalizeProduct($raw, includeRaw: false));
            $id = trim((string) ($product['id'] ?? ''));
            $ref = mb_strtolower(trim((string) ($product['reference'] ?? '')), 'UTF-8');
            $key = $id !== '' ? 'id:'.$id : 'ref:'.$ref;

            if ($key === 'ref:' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $product;
        }

        return $normalized;
    }

    /**
     * @param  list<array{product: array<string, mixed>, index: int}>  $exactIndexed
     * @return list<array<string, mixed>>
     */
    private function validateIndexedExactMatches(array $exactIndexed): array
    {
        if (count($exactIndexed) === 0) {
            return [];
        }

        $validated = [];
        $seen = [];

        foreach ($exactIndexed as $row) {
            $product = $row['product'] ?? [];
            $index = is_int($row['index'] ?? null) ? (int) $row['index'] : null;

            $id = trim((string) ($product['id'] ?? ''));
            $ref = trim((string) ($product['reference'] ?? ''));
            $lookupKey = $id !== '' ? $id : $ref;

            if ($lookupKey === '') {
                continue;
            }

            $raw = $this->fetchProductRawByIdOrReference($lookupKey, $index);
            if (! is_array($raw)) {
                continue;
            }

            $normalized = $this->withCoverImage($this->normalizeProduct($raw, includeRaw: false));
            $key = trim((string) ($normalized['id'] ?? ''));
            if ($key === '') {
                $key = mb_strtolower(trim((string) ($normalized['reference'] ?? '')), 'UTF-8');
            }
            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $validated[] = $normalized;
        }

        return $validated;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function randomProducts(int $count = 24): array
    {
        $count = max(1, min(200, $count));

        $indexed = (bool) config('tpsoftware.catalog.index_enabled', true)
            ? $this->indexedProducts()
            : null;

        if ($indexed === null) {
            throw new \RuntimeException('TP Software: índice de produtos ainda não foi gerado. Corre: php artisan tpsoftware:index');
        }

        return collect($indexed)
            ->shuffle()
            ->take($count)
            ->values()
            ->map(fn (array $p): array => $this->withCoverImage($p))
            ->all();
    }

    public function modelsForMakeSlug(string $makeSlug): array
    {
        $makeName = $this->categoryNameFromSlug($makeSlug);

        if ($makeName === '') {
            return [];
        }

        $indexed = (bool) config('tpsoftware.catalog.index_enabled', true)
            ? $this->indexedProducts()
            : null;

        if ($indexed !== null) {
            $names = [];

            foreach ($indexed as $p) {
                $make = (string) ($p['make_name'] ?? $p['category'] ?? '');
                $model = (string) ($p['model_name'] ?? '');

                if ($make !== '' && strcasecmp($make, $makeName) === 0 && $model !== '') {
                    $names[$model] = true;
                }
            }

            $models = [];

            foreach (array_keys($names) as $name) {
                $slug = Str::slug($name);

                if ($slug === '') {
                    continue;
                }

                $models[] = [
                    'slug' => $slug,
                    'name' => $name,
                ];
            }

            usort($models, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

            return $models;
        }

        $cache = Cache::store((string) config('tpsoftware.cache_store', 'file'));
        $cacheKey = 'tpsoftware:catalog:models:'.sha1($makeName);

        return $cache->remember($cacheKey, (int) config('tpsoftware.cache_ttl_seconds', 600), function () use ($makeName) {
            $limit = (int) config('tpsoftware.catalog.category_scan_limit', 200);
            $maxPages = (int) config('tpsoftware.catalog.category_scan_max_pages', 50);
            $maxSeconds = (int) config('tpsoftware.catalog.category_scan_max_seconds', 20);
            $makeField = (string) config('tpsoftware.catalog.category_field', 'vehicle_make_name');
            $modelField = (string) config('tpsoftware.catalog.model_field', 'vehicle_model_name');

            $limit = max(1, min(500, $limit));
            $maxPages = max(1, min(500, $maxPages));
            $maxSeconds = max(1, min(120, $maxSeconds));

            $startedAt = microtime(true);
            $names = [];

            for ($page = 1; $page <= $maxPages; $page++) {
                if ((microtime(true) - $startedAt) > $maxSeconds) {
                    break;
                }

                $result = $this->inventoryList([
                    'limit' => $limit,
                    'page' => $page,
                    'search' => $makeName,
                ], 0, false);

                if (! ($result['ok'] ?? false)) {
                    break;
                }

                $items = $this->extractList($result['data'] ?? null);

                if (count($items) === 0) {
                    break;
                }

                foreach ($items as $item) {
                    $itemMake = data_get($item, $makeField);
                    $itemModel = data_get($item, $modelField);

                    if (! is_string($itemMake) || strcasecmp(trim($itemMake), $makeName) !== 0) {
                        continue;
                    }

                    if (is_string($itemModel) && trim($itemModel) !== '') {
                        $names[trim($itemModel)] = true;
                    }
                }

                if (count($items) < $limit) {
                    break;
                }
            }

            $models = [];

            foreach (array_keys($names) as $name) {
                $slug = Str::slug($name);

                if ($slug === '') {
                    continue;
                }

                $models[] = [
                    'slug' => $slug,
                    'name' => $name,
                ];
            }

            usort($models, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

            return $models;
        });
    }

    public function totalProductsBreakdown(): ?array
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{ok: bool, status: int|null, data: mixed, raw: mixed}
     */
    private function inventoryList(array $params, ?int $cacheTtlSeconds, bool $useCache): array
    {
        $language = (string) config('tpsoftware.catalog.language', 'en');
        $orderField = (string) config('tpsoftware.catalog.order_field', 'created_at');
        $orderType = (string) config('tpsoftware.catalog.order_type', 'asc');

        return $this->client->get('ecommerce-get-product-inventory', [
            'search' => '',
            'order_field' => $orderField,
            'order_type' => $orderType,
            'language' => $language,
            ...$params,
        ], $cacheTtlSeconds, $useCache);
    }

    private function categoryNameFromSlug(string $slug): string
    {
        $slug = trim($slug);

        if ($slug === '') {
            return $slug;
        }

        $cats = $this->categories();

        foreach ($cats as $cat) {
            if (($cat['slug'] ?? '') === $slug) {
                return (string) ($cat['name'] ?? $slug);
            }
        }

        // fallback: "des-slug"
        return str_replace('-', ' ', $slug);
    }

    private function modelNameFromSlug(string $makeSlug, string $modelSlug): string
    {
        $modelSlug = trim($modelSlug);

        if ($modelSlug === '') {
            return '';
        }

        $models = $this->modelsForMakeSlug($makeSlug);

        foreach ($models as $model) {
            if (($model['slug'] ?? '') === $modelSlug) {
                return (string) ($model['name'] ?? '');
            }
        }

        return str_replace('-', ' ', $modelSlug);
    }

    /**
     * @param  mixed  $data
     * @return list<array<string, mixed>>
     */
    private function extractList(mixed $data): array
    {
        if (is_array($data) && array_is_list($data)) {
            /** @var list<array<string, mixed>> */
            return array_values(array_filter($data, fn ($item) => is_array($item)));
        }

        if (is_array($data)) {
            foreach (['data', 'items', 'results', 'products', 'companies', 'company_list'] as $key) {
                if (isset($data[$key]) && is_array($data[$key]) && array_is_list($data[$key])) {
                    /** @var list<array<string, mixed>> */
                    return array_values(array_filter($data[$key], fn ($item) => is_array($item)));
                }
            }
        }

        return [];
    }

    /**
     * @param  mixed  $data
     */
    private function extractTotalCount(mixed $data): int
    {
        if (! is_array($data)) {
            return 0;
        }

        foreach (['total', 'count', 'total_count', 'totalRecords', 'total_records'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                return (int) $data[$key];
            }
        }

        return 0;
    }


    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeProduct(array $raw, bool $includeRaw = false): array
    {
        $id = data_get($raw, 'id');
        $reference = $this->extractPieceReference($raw)
            ?? data_get($raw, 'part_code')
            ?? data_get($raw, 'parts_internal_id');
        $title = data_get($raw, 'product_name') ?? data_get($raw, 'part_description') ?? $reference ?? (string) $id;
        $category = (string) (data_get($raw, (string) config('tpsoftware.catalog.category_field', 'vehicle_make_name')) ?? 'Outros');
        $modelName = (string) (data_get($raw, (string) config('tpsoftware.catalog.model_field', 'vehicle_model_name')) ?? '');
        $stateName = (string) (data_get($raw, 'state_name') ?? '');
        $conditionName = (string) (data_get($raw, 'condition_name') ?? '');
        $fuelType = trim((string) (data_get($raw, 'fuel_type_name') ?? ''));
        $engineCode = trim((string) (data_get($raw, 'motor_code') ?? ''));
        $vehicleYear = trim((string) (data_get($raw, 'vehicle_year') ?? ''));
        $tpReference = trim((string) (data_get($raw, 'parts_internal_id') ?? ''));

        $images = data_get($raw, 'image_list');
        $imageUrls = is_array($images) ? $this->normalizeImageUrlsFromImageList($images) : [];
        $coverUrl = is_array($images) ? $this->pickCoverUrlFromImageList($images) : ($imageUrls[0] ?? null);

        $normalized = [
            'id' => $id,
            'reference' => $reference,
            'title' => $title,
            'category' => $category,
            'make_id' => null,
            'make_name' => $category,
            'model_id' => null,
            'model_name' => $modelName,
            'price' => data_get($raw, 'price_1') ?? data_get($raw, 'price_2'),
            'vat' => data_get($raw, 'vat_included'),
            'stock' => data_get($raw, 'quantity'),
            'images' => array_values(array_unique(array_filter($imageUrls))),
            'cover_image' => is_string($coverUrl) && $coverUrl !== '' ? $coverUrl : null,
            'state_name' => $stateName,
            'condition_name' => $conditionName,
            'fuel_type' => $fuelType !== '' ? $fuelType : null,
            'engine_code' => $engineCode !== '' ? $engineCode : null,
            'engine_label' => $this->buildEngineLabel($raw),
            'vehicle_year' => $vehicleYear !== '' ? $vehicleYear : null,
            'tp_reference' => $tpReference !== '' ? $tpReference : null,
        ];

        if ($includeRaw) {
            $normalized['raw'] = $raw;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function buildEngineLabel(array $raw): ?string
    {
        $parts = [];

        $engineCode = trim((string) (data_get($raw, 'motor_code') ?? ''));
        if ($engineCode !== '') {
            $parts[] = $engineCode;
        }

        $cc = trim((string) (data_get($raw, 'cc') ?? ''));
        if ($cc !== '' && $cc !== '0') {
            $parts[] = $cc.'cc';
        }

        $kw = trim((string) (data_get($raw, 'kw') ?? ''));
        if ($kw !== '' && $kw !== '0') {
            $parts[] = $kw.'kW';
        }

        $cv = trim((string) (data_get($raw, 'cv') ?? ''));
        if ($cv !== '' && $cv !== '0') {
            $parts[] = $cv.'cv';
        }

        if (count($parts) === 0) {
            return null;
        }

        return implode(' | ', $parts);
    }

    /**
     * Garante que um item do índice tem `cover_image` coerente, sem reordenar `images`.
     *
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function withCoverImage(array $product): array
    {
        $existing = $product['cover_image'] ?? null;

        if (is_string($existing) && trim($existing) !== '') {
            return $product;
        }

        $images = $product['images'] ?? null;

        if (! is_array($images) || count($images) === 0) {
            return [
                ...$product,
                'cover_image' => null,
            ];
        }

        $urls = array_values(array_filter($images, fn ($u): bool => is_string($u) && trim($u) !== ''));

        if (count($urls) === 0) {
            return [
                ...$product,
                'cover_image' => null,
            ];
        }

        return [
            ...$product,
            'cover_image' => $this->pickCoverUrlFromUrls($urls) ?? $urls[0],
        ];
    }

    /**
     * @param  list<string>  $urls
     */
    private function pickCoverUrlFromUrls(array $urls): ?string
    {
        $best = null;
        $bestId = null;
        $bestTs = null;

        foreach ($urls as $url) {
            $u = trim($url);
            if ($u === '') {
                continue;
            }

            if (preg_match('~/(\\d{10,})-~', $u, $m) === 1) {
                $ts = (int) $m[1];
                if ($bestTs === null || $ts < $bestTs) {
                    $bestTs = $ts;
                    $best = $u;
                }
            }
        }

        return $best;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProductRawByIdOrReference(string $idOrReference, ?int $indexPosition = null): ?array
    {
        $idOrReference = trim($idOrReference);

        if ($idOrReference === '') {
            return null;
        }

        $cache = Cache::store((string) config('tpsoftware.cache_store', 'file'));
        $ttl = (int) config('tpsoftware.cache_ttl_seconds', 600);
        $ttl = max(60, min(3600, $ttl));

        $key = 'tpsoftware:product:raw:'.sha1(mb_strtolower($idOrReference, 'UTF-8'));

        $existing = $cache->get($key, '__missing__');

        if (is_array($existing)) {
            /** @var array<string, mixed> */
            return $existing;
        }

        if ($existing !== '__missing__') {
            return null;
        }

        $found = $this->scanInventoryForProduct($idOrReference, $indexPosition);

        if (is_array($found)) {
            $cache->put($key, $found, $ttl);

            return $found;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function matchesIdOrReference(array $raw, string $idOrReference): bool
    {
        $needle = trim($idOrReference);

        if ($needle === '') {
            return false;
        }

        $id = data_get($raw, 'id');
        if (is_scalar($id) && (string) $id !== '' && (string) $id === $needle) {
            return true;
        }

        $ref = $this->extractPieceReference($raw)
            ?? data_get($raw, 'part_code')
            ?? data_get($raw, 'parts_internal_id');

        if (is_scalar($ref) && (string) $ref !== '' && strcasecmp((string) $ref, $needle) === 0) {
            return true;
        }

        return false;
    }

    /**
     * Fallback: como o endpoint de inventário nem sempre suporta pesquisa por referência,
     * faz scan paginado até encontrar o produto (com limite de tempo/páginas).
     *
     * @return array<string, mixed>|null
     */
    private function scanInventoryForProduct(string $idOrReference, ?int $indexPosition = null): ?array
    {
        $pageSize = (int) config('tpsoftware.catalog.index_page_size', 200);
        $pageSize = max(1, min(500, $pageSize));

        if (is_int($indexPosition) && $indexPosition >= 0) {
            $hintPage = intdiv($indexPosition, $pageSize) + 1;

            foreach ([$hintPage, $hintPage - 1, $hintPage + 1] as $page) {
                if ($page < 1) {
                    continue;
                }

                $result = $this->inventoryList([
                    'limit' => $pageSize,
                    'page' => $page,
                    'search' => '',
                ], 60, true);

                if (! ($result['ok'] ?? false)) {
                    break;
                }

                $items = $this->extractList($result['data'] ?? null);

                foreach ($items as $raw) {
                    if (! is_array($raw)) {
                        continue;
                    }

                    if ($this->matchesIdOrReference($raw, $idOrReference)) {
                        return $raw;
                    }
                }

                if (count($items) < $pageSize) {
                    break;
                }
            }
        }

        $maxPages = (int) env('TPSOFTWARE_PRODUCT_LOOKUP_MAX_PAGES', 50);
        $maxPages = max(1, min(500, $maxPages));

        $maxSeconds = (int) env('TPSOFTWARE_PRODUCT_LOOKUP_MAX_SECONDS', 12);
        $maxSeconds = max(1, min(60, $maxSeconds));

        $startedAt = microtime(true);

        for ($page = 1; $page <= $maxPages; $page++) {
            if ((microtime(true) - $startedAt) > $maxSeconds) {
                break;
            }

            $result = $this->inventoryList([
                'limit' => $pageSize,
                'page' => $page,
                'search' => $idOrReference,
            ], 60, true);

            if (! ($result['ok'] ?? false)) {
                break;
            }

            $items = $this->extractList($result['data'] ?? null);

            if (count($items) === 0) {
                break;
            }

            foreach ($items as $raw) {
                if (! is_array($raw)) {
                    continue;
                }

                if ($this->matchesIdOrReference($raw, $idOrReference)) {
                    return $raw;
                }
            }

            if (count($items) < $pageSize) {
                break;
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $images
     * @return list<string>
     */
    private function normalizeImageUrlsFromImageList(array $images): array
    {
        $out = [];
        $seen = [];

        foreach ($images as $image) {
            if (! is_array($image)) {
                continue;
            }

            $url = data_get($image, 'image_url')
                ?? data_get($image, 'url')
                ?? data_get($image, 'urlImage')
                ?? data_get($image, 'url_image');

            if (! is_string($url) || trim($url) === '') {
                continue;
            }

            $url = trim($url);
            $key = mb_strtolower($url, 'UTF-8');

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = $url;
            }
        }

        return $out;
    }

    /**
     * Decide a imagem de capa sem reordenar o array (a ordem original mantém-se em `images`).
     *
     * Regras:
     *  1) Se houver flag explícita de cover/main → primeira com flag.
     *  2) Senão → menor `id`.
     *  3) Senão → menor timestamp no filename (número antes do "-").
     *  4) Senão → primeira da API.
     *
     * @param  list<mixed>  $images
     */
    private function pickCoverUrlFromImageList(array $images): ?string
    {
        $items = [];

        foreach ($images as $idx => $image) {
            if (! is_array($image)) {
                continue;
            }

            $url = data_get($image, 'image_url')
                ?? data_get($image, 'url')
                ?? data_get($image, 'urlImage')
                ?? data_get($image, 'url_image');

            if (! is_string($url) || trim($url) === '') {
                continue;
            }

            $url = trim($url);

            $id = data_get($image, 'id');
            $id = is_numeric($id) ? (int) $id : null;

            $ts = null;
            if (preg_match('~/(\\d{10,})-~', $url, $m) === 1) {
                $ts = (int) $m[1];
            }

            $items[] = [
                'idx' => $idx,
                'url' => $url,
                'is_cover' => $this->isCoverImage($image),
                'id' => $id,
                'ts' => $ts,
            ];
        }

        if (count($items) === 0) {
            return null;
        }

        foreach ($items as $it) {
            if (($it['is_cover'] ?? false) === true) {
                return (string) $it['url'];
            }
        }

        $withId = array_values(array_filter($items, static fn (array $it): bool => is_int($it['id'] ?? null)));
        if (count($withId) > 0) {
            usort($withId, static fn (array $a, array $b): int => ((int) $a['id']) <=> ((int) $b['id']));

            return (string) $withId[0]['url'];
        }

        $withTs = array_values(array_filter($items, static fn (array $it): bool => is_int($it['ts'] ?? null)));
        if (count($withTs) > 0) {
            usort($withTs, static fn (array $a, array $b): int => ((int) $a['ts']) <=> ((int) $b['ts']));

            return (string) $withTs[0]['url'];
        }

        return (string) $items[0]['url'];
    }

    /**
     * @param  array<string, mixed>  $image
     */
    private function isCoverImage(array $image): bool
    {
        foreach (['isCover', 'is_cover', 'cover', 'isMain', 'is_main', 'main', 'isPrimary', 'is_primary', 'primary', 'default', 'isDefault', 'is_default'] as $key) {
            $value = data_get($image, $key);
            if ($this->isTruthy($value)) {
                return true;
            }
        }

        foreach (['type', 'role', 'kind', 'imageType', 'image_type', 'tag', 'label'] as $key) {
            $value = data_get($image, $key);
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $v = mb_strtolower(trim($value), 'UTF-8');
            if (str_contains($v, 'cover') || str_contains($v, 'capa') || str_contains($v, 'principal') || str_contains($v, 'main') || str_contains($v, 'front')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $image
     */
    private function explicitImageOrder(array $image): ?int
    {
        foreach (['order', 'position', 'sort', 'sortOrder', 'sort_order', 'index'] as $key) {
            $value = data_get($image, $key);
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    private function isTruthy(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        $v = mb_strtolower(trim($value), 'UTF-8');

        return in_array($v, ['true', 'yes', 'y', 'sim', 's'], true);
    }

    private function extractPieceReference(array $raw): ?string
    {
        $refs = data_get($raw, 'parts_reference');

        if (! is_array($refs) || ! array_is_list($refs)) {
            return null;
        }

        $first = null;

        foreach ($refs as $item) {
            if (! is_array($item)) {
                continue;
            }

            $code = data_get($item, 'reference_code');
            if (! is_scalar($code) || trim((string) $code) === '') {
                continue;
            }

            $first ??= trim((string) $code);

            $isMain = data_get($item, 'is_main');
            if ($isMain === 1 || $isMain === '1' || $isMain === true) {
                return trim((string) $code);
            }
        }

        return $first;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function indexedProducts(): ?array
    {
        if (! (bool) config('tpsoftware.catalog.index_enabled', true)) {
            return null;
        }

        return $this->indexStore->load();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $products
     * @param  callable(array<string, mixed>): string  $buildUrl
     * @return array<string, mixed>
     */
    private function facetsFromProducts(\Illuminate\Support\Collection $products, callable $buildUrl): array
    {
        $states = [];
        $conditions = [];
        $prices = [];
        $pieceCategories = [];

        foreach ($products as $p) {
            $state = trim((string) ($p['state_name'] ?? ''));
            if ($state === '') {
                $state = 'N/A';
            }
            $states[$state] = ($states[$state] ?? 0) + 1;

            $condition = trim((string) ($p['condition_name'] ?? ''));
            if ($condition === '') {
                $condition = 'N/A';
            }
            $conditions[$condition] = ($conditions[$condition] ?? 0) + 1;

            $bucketKey = $this->priceBucketKey($p['price'] ?? null);
            $prices[$bucketKey] = ($prices[$bucketKey] ?? 0) + 1;

            $piece = $this->pieceCategoryName((string) ($p['title'] ?? ''));
            if ($piece !== '') {
                $pieceCategories[$piece] = ($pieceCategories[$piece] ?? 0) + 1;
            }
        }

        if (count($states) === 1 && isset($states['N/A'])) {
            $states = [];
        }

        if (count($conditions) === 1 && isset($conditions['N/A'])) {
            $conditions = [];
        }

        $stateOpts = [];
        foreach ($states as $name => $count) {
            $slug = Str::slug($name);
            if ($slug === '') {
                continue;
            }

            $stateOpts[] = [
                'slug' => $slug,
                'name' => $name,
                'count' => $count,
                'url' => $buildUrl(['state' => $slug, 'page' => 1]),
            ];
        }
        usort($stateOpts, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        $conditionOpts = [];
        foreach ($conditions as $name => $count) {
            $slug = Str::slug($name);
            if ($slug === '') {
                continue;
            }

            $conditionOpts[] = [
                'slug' => $slug,
                'name' => $name,
                'count' => $count,
                'url' => $buildUrl(['condition' => $slug, 'page' => 1]),
            ];
        }
        usort($conditionOpts, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        $priceLabels = $this->priceBuckets();
        $priceOpts = [];
        foreach ($priceLabels as $key => $label) {
            $count = (int) ($prices[$key] ?? 0);
            if ($count === 0) {
                continue;
            }

            $priceOpts[] = [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'url' => $buildUrl(['price' => $key, 'page' => 1]),
            ];
        }

        $pieceOpts = [];
        foreach ($pieceCategories as $name => $count) {
            $slug = Str::slug($name);
            if ($slug === '') {
                continue;
            }

            $pieceOpts[] = [
                'slug' => $slug,
                'name' => $name,
                'count' => $count,
                'url' => $buildUrl(['piece' => $slug, 'page' => 1]),
            ];
        }
        usort($pieceOpts, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return [
            'states' => $stateOpts,
            'states_all_url' => $buildUrl(['state' => null, 'page' => 1]),
            'conditions' => $conditionOpts,
            'conditions_all_url' => $buildUrl(['condition' => null, 'page' => 1]),
            'prices' => $priceOpts,
            'prices_all_url' => $buildUrl(['price' => null, 'page' => 1]),
            'piece_categories' => $pieceOpts,
            'piece_categories_all_url' => $buildUrl(['piece' => null, 'page' => 1]),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $facetOptions
     */
    private function facetNameFromSlug(array $facetOptions, string $slug): string
    {
        foreach ($facetOptions as $opt) {
            if (($opt['slug'] ?? null) === $slug) {
                return (string) ($opt['name'] ?? '');
            }
        }

        return '';
    }

    private function pieceCategoryName(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            return '';
        }

        $title = preg_replace('/\\s+/', ' ', $title) ?? $title;
        $title = preg_replace('/\\s*\\(.*?\\)\\s*/', ' ', $title) ?? $title;
        $title = preg_replace('/\\s*,\\s*.*$/', '', $title) ?? $title;

        $firstChunk = preg_split('/\\s*[-–—|\\/]+\\s*/u', $title)[0] ?? $title;
        $firstChunk = trim($firstChunk);

        if ($firstChunk === '') {
            return '';
        }

        $words = preg_split('/\\s+/u', $firstChunk) ?: [];
        $words = array_values(array_filter($words, fn ($w) => is_string($w) && trim($w) !== ''));

        if (count($words) === 0) {
            return '';
        }

        $w1 = mb_strtolower($words[0], 'UTF-8');
        $multi = in_array($w1, ['kit', 'jogo', 'conjunto'], true);

        $take = $multi ? 2 : 1;
        if (! $multi && mb_strlen($words[0], 'UTF-8') <= 3) {
            $take = 2;
        }

        $picked = array_slice($words, 0, min($take, count($words)));

        return trim(implode(' ', $picked));
    }

    private function priceBucketKey(mixed $price): string
    {
        if (! is_numeric($price)) {
            return 'sob-consulta';
        }

        $p = (float) $price;

        if ($p < 0) {
            return 'sob-consulta';
        }

        if ($p < 20) {
            return '0-20';
        }
        if ($p < 50) {
            return '20-50';
        }
        if ($p < 100) {
            return '50-100';
        }
        if ($p < 150) {
            return '100-150';
        }
        if ($p < 200) {
            return '150-200';
        }
        if ($p < 500) {
            return '200-500';
        }
        if ($p < 1000) {
            return '500-1000';
        }
        if ($p < 2000) {
            return '1000-2000';
        }

        return '2000+';
    }

    /**
     * @return array<string, string>
     */
    private function priceBuckets(): array
    {
        return [
            'sob-consulta' => 'Sob consulta',
            '0-20' => '0€ - 20€',
            '20-50' => '20€ - 50€',
            '50-100' => '50€ - 100€',
            '100-150' => '100€ - 150€',
            '150-200' => '150€ - 200€',
            '200-500' => '200€ - 500€',
            '500-1000' => '500€ - 1000€',
            '1000-2000' => '1000€ - 2000€',
            '2000+' => '> 2000€',
        ];
    }
}
