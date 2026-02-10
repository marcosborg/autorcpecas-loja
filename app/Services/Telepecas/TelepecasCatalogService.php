<?php

namespace App\Services\Telepecas;

use App\Services\Catalog\CatalogProvider;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TelepecasCatalogService implements CatalogProvider
{
    public function __construct(private readonly TelepecasClient $client)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function randomProducts(int $count = 24): array
    {
        $count = max(1, min(200, $count));

        /** @var list<array<string, mixed>> $products */
        $products = $this->indexProducts();

        return collect($products)
            ->shuffle()
            ->take($count)
            ->values()
            ->all();
    }

    /**
     * @return list<array{slug: string, name: string, count: int|null}>
     */
    public function categories(): array
    {
        $source = (string) config('telepecas.catalog.categories_source', 'stock');

        if ($source === 'makes') {
            return $this->categoriesFromMakes();
        }

        /** @var list<array<string, mixed>> $products */
        $products = $this->indexProducts();

        $groups = collect($products)
            ->map(fn (array $product) => $product['category'] ?? null)
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->countBy()
            ->map(function (int $count, string $name) {
                return [
                    'slug' => Str::slug($name),
                    'name' => $name,
                    'count' => $count,
                ];
            })
            ->values()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return $groups->all();
    }

    public function totalProducts(): int
    {
        $result = $this->stockGet([
            'limit' => 1,
            'offset' => 0,
        ], 60, true);

        if (! ($result['ok'] ?? false)) {
            /** @var list<array<string, mixed>> $products */
            $products = $this->indexProducts();

            return count($products);
        }

        $data = $result['data'] ?? null;

        if (is_array($data) && isset($data['count'])) {
            $count = $data['count'];

            if (is_numeric($count)) {
                return (int) $count;
            }
        }

        /** @var list<array<string, mixed>> $products */
        $products = $this->indexProducts();

        return count($products);
    }

    /**
     * @return array{total: int, byRepo: list<array{id: string, description: string, count: int}>}|null
     */
    public function totalProductsBreakdown(): ?array
    {
        $driver = (string) config('telepecas.auth_driver', 'basic_token_body');

        if ($driver !== 'oauth2') {
            return null;
        }

        $repos = $this->repositories();

        if (count($repos) === 0) {
            return null;
        }

        $byRepo = [];
        $total = 0;

        foreach ($repos as $repo) {
            $count = $this->stockCount(['repo' => $repo['id']]);

            $byRepo[] = [
                'id' => $repo['id'],
                'description' => $repo['description'],
                'count' => $count,
            ];

            $total += $count;
        }

        return [
            'total' => $total,
            'byRepo' => $byRepo,
        ];
    }

    /**
     * @return list<array{slug: string, name: string}>
     */
    public function modelsForMakeSlug(string $makeSlug): array
    {
        [$makeId, $makeSlugNormalized] = $this->parseCategorySlug($makeSlug);

        $pageSize = (int) config('telepecas.catalog.page_size', 100);
        $pageSize = max(1, min(500, $pageSize));

        $offset = 0;
        $models = [];

        while (true) {
            $params = [
                'limit' => $pageSize,
                'offset' => $offset,
            ];

            if ($makeId !== null) {
                $param = (string) config('telepecas.catalog.models_make_filter_param', 'externalMakeId');
                $params[$param] = $makeId;
            }

            $result = $this->client->get('catalog/models/getModels', $params, 900, true);

            if (! ($result['ok'] ?? false)) {
                break;
            }

            $data = $result['data'] ?? null;
            $pageItems = $this->extractList($data);

            if (count($pageItems) === 0) {
                break;
            }

            foreach ($pageItems as $raw) {
                $id = data_get($raw, 'externalId')
                    ?? data_get($raw, 'externalModelId')
                    ?? data_get($raw, 'modelId')
                    ?? data_get($raw, 'id');

                $name = data_get($raw, 'description')
                    ?? data_get($raw, 'modelName')
                    ?? data_get($raw, 'name')
                    ?? data_get($raw, 'title');

                if (! is_string($name) || trim($name) === '') {
                    continue;
                }

                $name = trim($name);
                $slugBase = Str::slug($name);

                if ($slugBase === '') {
                    continue;
                }

                $slug = is_scalar($id) && (string) $id !== ''
                    ? ((string) $id).'-'.$slugBase
                    : $slugBase;

                $models[] = [
                    'slug' => $slug,
                    'name' => $name,
                ];
            }

            $offset += count($pageItems);

            if (count($pageItems) < $pageSize) {
                break;
            }
        }

        return collect($models)
            ->unique('slug')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @return list<array{slug: string, name: string, count: int|null}>
     */
    private function categoriesFromMakes(): array
    {
        $pageSize = (int) config('telepecas.catalog.page_size', 100);
        $pageSize = max(1, min(500, $pageSize));

        $offset = 0;
        $makes = [];

        while (true) {
            $result = $this->client->get('catalog/makes/getMakes', [
                'limit' => $pageSize,
                'offset' => $offset,
            ], 900, true);

            if (! ($result['ok'] ?? false)) {
                break;
            }

            $data = $result['data'] ?? null;
            $pageItems = $this->extractList($data);

            if (count($pageItems) === 0) {
                break;
            }

            foreach ($pageItems as $raw) {
                $id = data_get($raw, 'externalId')
                    ?? data_get($raw, 'externalMakeId')
                    ?? data_get($raw, 'makeId')
                    ?? data_get($raw, 'id');

                $name = data_get($raw, 'description')
                    ?? data_get($raw, 'makeName')
                    ?? data_get($raw, 'name')
                    ?? data_get($raw, 'title');

                if (! is_string($name) || trim($name) === '') {
                    continue;
                }

                $name = trim($name);
                $slugBase = Str::slug($name);

                if ($slugBase === '') {
                    continue;
                }

                $slug = is_scalar($id) && (string) $id !== ''
                    ? ((string) $id).'-'.$slugBase
                    : $slugBase;

                $makes[] = [
                    'slug' => $slug,
                    'name' => $name,
                ];
            }

            $offset += count($pageItems);

            if (count($pageItems) < $pageSize) {
                break;
            }
        }

        return collect($makes)
            ->unique('slug')
            ->map(function (array $make) {
                $slug = (string) $make['slug'];

                return [
                    'slug' => $slug,
                    'name' => (string) $make['name'],
                    'count' => null,
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    public function productsByCategory(string $categorySlug, int $page = 1, int $perPage = 24): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $modelFilter = (string) request()->query('model', '');
        $pieceFilter = (string) request()->query('piece', '');

        /** @var list<array<string, mixed>> $products */
        $products = $this->indexProducts();

        $source = (string) config('telepecas.catalog.categories_source', 'stock');
        [$categoryId, $categorySlugNormalized] = $this->parseCategorySlug($categorySlug);

        $matchingBase = collect($products)
            ->filter(function (array $product) use ($categorySlug): bool {
                $category = $product['category'] ?? null;

                return is_string($category) && Str::slug($category) === $categorySlug;
            })
            ->values();

        if ($source === 'makes') {
            $matchingBase = collect($products)
                ->filter(function (array $product) use ($categoryId, $categorySlugNormalized): bool {
                    if ($categoryId !== null) {
                        $makeId = $product['make_id'] ?? null;

                        return is_scalar($makeId) && (string) $makeId === (string) $categoryId;
                    }

                    $makeName = $product['make_name'] ?? null;

                    return is_string($makeName) && Str::slug($makeName) === $categorySlugNormalized;
                })
                ->values();

            if ($modelFilter !== '') {
                [$modelId, $modelSlugNormalized] = $this->parseCategorySlug($modelFilter);

                $matchingBase = $matchingBase
                    ->filter(function (array $product) use ($modelId, $modelSlugNormalized): bool {
                        if ($modelId !== null) {
                            $productModelId = $product['model_id'] ?? null;

                            return is_scalar($productModelId) && (string) $productModelId === (string) $modelId;
                        }

                        $modelName = $product['model_name'] ?? null;

                        return is_string($modelName) && Str::slug($modelName) === $modelSlugNormalized;
                    })
                    ->values();
            }
        }

        $buildUrl = function (array $overrides = []) use ($categorySlug): string {
            $basePath = url('/loja/categorias/'.$categorySlug);
            $query = array_merge(request()->query(), $overrides);

            unset($query['page']);

            foreach (['model', 'piece', 'perPage'] as $key) {
                if (! array_key_exists($key, $query)) {
                    continue;
                }

                if ($query[$key] === null || $query[$key] === '') {
                    unset($query[$key]);
                }
            }

            return $basePath.(count($query) > 0 ? ('?'.http_build_query($query)) : '');
        };

        $facets = $this->pieceCategoryFacetsFromProducts($matchingBase, $buildUrl);

        $matching = $matchingBase;
        if ($pieceFilter !== '') {
            $wanted = $this->facetNameFromSlug($facets['piece_categories'] ?? [], $pieceFilter);

            if ($wanted !== '') {
                $matching = $matching
                    ->filter(fn (array $p): bool => strcasecmp($this->pieceCategoryName((string) ($p['title'] ?? '')), $wanted) === 0)
                    ->values();
            }
        }

        $categoryName = (string) (
            ($source === 'makes')
                ? ($matchingBase->first()['make_name'] ?? $categorySlug)
                : ($matchingBase->first()['category'] ?? $categorySlug)
        );

        $total = $matching->count();
        $items = $matching->slice(($page - 1) * $perPage, $perPage)->all();

        return [
            'categoryName' => $categoryName,
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
                'facets' => $facets,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function product(string $idOrReference): ?array
    {
        $idOrReference = trim($idOrReference);

        if ($idOrReference === '') {
            return null;
        }

        // Se for numérico, tenta primeiro o endpoint de detalhe (quando suportado).
        if (ctype_digit($idOrReference)) {
            try {
                $result = $this->stockGet(['id' => (int) $idOrReference], 300, true);

                if (($result['ok'] ?? false) && is_array($result['data'] ?? null)) {
                    /** @var array<string, mixed> $raw */
                    $raw = $result['data'];

                    return $this->normalizeProduct($raw, includeRaw: true);
                }
            } catch (\RuntimeException) {
                // fallback abaixo
            }
        }

        /** @var list<array<string, mixed>> $products */
        $products = $this->indexProducts();

        $found = collect($products)->first(function (array $product) use ($idOrReference): bool {
            $id = (string) ($product['id'] ?? '');
            $reference = (string) ($product['reference'] ?? '');

            return $id === $idOrReference || strcasecmp($reference, $idOrReference) === 0;
        });

        return $found ?: null;
    }

    /**
     * @return LengthAwarePaginator
     */
    public function search(string $query, int $page = 1, int $perPage = 24): LengthAwarePaginator
    {
        $query = trim($query);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        if ($query === '') {
            return new LengthAwarePaginator([], 0, $perPage, $page, ['path' => url('/loja/pesquisa')]);
        }

        /** @var list<array<string, mixed>> $products */
        $products = $this->indexProducts();

        $q = mb_strtolower($query);

        $matching = collect($products)
            ->filter(function (array $product) use ($q): bool {
                $haystacks = [
                    (string) ($product['reference'] ?? ''),
                    (string) ($product['title'] ?? ''),
                    (string) ($product['category'] ?? ''),
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
        $items = $matching->slice(($page - 1) * $perPage, $perPage)->all();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => url('/loja/pesquisa'),
                'query' => request()->query(),
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function indexProducts(): array
    {
        $store = (string) config('telepecas.catalog.cache_store', '');
        $ttl = (int) config('telepecas.catalog.index_cache_ttl_seconds', 600);
        $ttl = max(0, $ttl);

        $cache = $store !== '' ? Cache::store($store) : Cache::store();

        return $cache->remember('telepecas:catalog:index', $ttl, function () {
            $pageSize = (int) config('telepecas.catalog.page_size', 100);
            $maxItems = (int) config('telepecas.catalog.max_items', 2000);
            $pageSize = max(1, min(500, $pageSize));
            $maxItems = max(0, $maxItems);

            $items = [];
            $offset = 0;

            while (count($items) < $maxItems) {
                $limit = min($pageSize, $maxItems - count($items));

                $result = $this->stockGet([
                    'limit' => $limit,
                    'offset' => $offset,
                ], 0, false);

                if (! ($result['ok'] ?? false)) {
                    break;
                }

                $data = $result['data'] ?? null;
                $pageItems = $this->extractList($data);

                if (count($pageItems) === 0) {
                    break;
                }

                foreach ($pageItems as $raw) {
                    $items[] = $this->normalizeProduct($raw, includeRaw: false);
                }

                $offset += count($pageItems);

                if (count($pageItems) < $limit) {
                    break;
                }
            }

            return $items;
        });
    }

    /**
     * @param  array<string, mixed>  $paramsOrPayload
     * @return array{ok: bool, status: int|null, data: mixed, raw: mixed}
     */
    private function stockGet(array $paramsOrPayload, ?int $cacheTtlSeconds, bool $useCache): array
    {
        $driver = (string) config('telepecas.auth_driver', 'basic_token_body');

        if ($driver === 'oauth2') {
            return $this->client->get('stock/getStock', $paramsOrPayload, $cacheTtlSeconds, $useCache);
        }

        return $this->client->post('stock/getStock', $paramsOrPayload, $cacheTtlSeconds, $useCache);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function stockCount(array $extra = []): int
    {
        $result = $this->stockGet([
            'limit' => 1,
            'offset' => 0,
            ...$extra,
        ], 120, true);

        if (! ($result['ok'] ?? false)) {
            return 0;
        }

        $data = $result['data'] ?? null;

        if (is_array($data) && isset($data['count']) && is_numeric($data['count'])) {
            return (int) $data['count'];
        }

        return 0;
    }

    /**
     * @return list<array{id: string, description: string}>
     */
    private function repositories(): array
    {
        $result = $this->client->get('catalog/repositories/getRepositories', [], 3600, true);

        if (! ($result['ok'] ?? false)) {
            return [];
        }

        $data = $result['data'] ?? null;

        if (! is_array($data)) {
            return [];
        }

        $items = $this->extractList($data);

        $repos = [];

        foreach ($items as $item) {
            $id = data_get($item, 'id');
            $description = data_get($item, 'description');

            if (! is_scalar($id) || ! is_string($description) || trim($description) === '') {
                continue;
            }

            $repos[] = [
                'id' => (string) $id,
                'description' => trim($description),
            ];
        }

        return $repos;
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
            foreach (['data', 'makes', 'models', 'repositories', 'stock', 'stocks', 'parts', 'items', 'results'] as $key) {
                if (isset($data[$key]) && is_array($data[$key]) && array_is_list($data[$key])) {
                    /** @var list<array<string, mixed>> */
                    return array_values(array_filter($data[$key], fn ($item) => is_array($item)));
                }
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeProduct(array $raw, bool $includeRaw): array
    {
        $id = data_get($raw, 'id')
            ?? data_get($raw, 'externalId')
            ?? data_get($raw, 'externalReference')
            ?? data_get($raw, 'reference');

        $reference = data_get($raw, 'partInfo.reference')
            ?? data_get($raw, 'reference')
            ?? data_get($raw, 'externalReference');

        $title = data_get($raw, 'partInfo.partDescriptionPT')
            ?? data_get($raw, 'partInfo.partDescriptionEN')
            ?? data_get($raw, 'description')
            ?? data_get($raw, 'title')
            ?? $reference
            ?? (string) $id;

        $category = $this->extractCategory($raw);

        $makeId = $this->extractFirstScalar($raw, (array) config('telepecas.catalog.make_id_paths', []));
        $makeName = $this->extractFirstString($raw, (array) config('telepecas.catalog.make_name_paths', []));
        $modelId = $this->extractFirstScalar($raw, (array) config('telepecas.catalog.model_id_paths', []));
        $modelName = $this->extractFirstString($raw, (array) config('telepecas.catalog.model_name_paths', []));

        $price = data_get($raw, 'price1')
            ?? data_get($raw, 'price')
            ?? data_get($raw, 'price2');

        $vat = data_get($raw, 'vat')
            ?? data_get($raw, 'vatIncluded');

        $stock = data_get($raw, 'stock')
            ?? data_get($raw, 'qty')
            ?? data_get($raw, 'quantity');

        $images = data_get($raw, 'images');

        if (! is_array($images)) {
            $images = [];
        }

        $imageUrls = $this->normalizeImageUrls($images);
        $coverUrl = $this->pickCoverUrlFromTelepecasImages($images) ?? ($imageUrls[0] ?? null);

        $normalized = [
            'id' => $id,
            'reference' => $reference,
            'title' => $title,
            'category' => $category,
            'make_id' => $makeId,
            'make_name' => $makeName,
            'model_id' => $modelId,
            'model_name' => $modelName,
            'price' => $price,
            'vat' => $vat,
            'price_ex_vat' => $this->priceWithoutVat($price, $vat),
            'stock' => $stock,
            'images' => array_values(array_unique($imageUrls)),
            'cover_image' => is_string($coverUrl) && $coverUrl !== '' ? $coverUrl : null,
        ];

        if ($includeRaw) {
            $normalized['raw'] = $raw;
        }

        return $normalized;
    }

    private function priceWithoutVat(mixed $price, mixed $vat): ?float
    {
        if (! is_numeric($price)) {
            return null;
        }

        $gross = (float) $price;
        if ($gross < 0) {
            return null;
        }

        $vatRate = $this->vatRateFromValue($vat);
        if ($vatRate <= 0) {
            return round($gross, 2);
        }

        return round($gross / (1 + ($vatRate / 100)), 2);
    }

    private function vatRateFromValue(mixed $vat): float
    {
        if (is_numeric($vat)) {
            $n = (float) $vat;
            if ($n <= 0) {
                return 0.0;
            }
            if ($n > 1 && $n <= 100) {
                return $n;
            }
        }

        if ($this->isTruthy($vat)) {
            return (float) env('STOREFRONT_DEFAULT_VAT_RATE', 23);
        }

        return 0.0;
    }

    /**
     * @param  list<mixed>  $images
     */
    private function pickCoverUrlFromTelepecasImages(array $images): ?string
    {
        foreach ($images as $image) {
            if (! is_array($image)) {
                continue;
            }

            if (! $this->isCoverImage($image)) {
                continue;
            }

            $url = data_get($image, 'urlImage')
                ?? data_get($image, 'url')
                ?? data_get($image, 'urlImageThumbnail');

            if (is_string($url) && trim($url) !== '') {
                return trim($url);
            }
        }

        foreach ($images as $image) {
            if (is_string($image) && trim($image) !== '') {
                return trim($image);
            }

            if (is_array($image)) {
                $url = data_get($image, 'urlImage')
                    ?? data_get($image, 'url')
                    ?? data_get($image, 'urlImageThumbnail');

                if (is_string($url) && trim($url) !== '') {
                    return trim($url);
                }
            }
        }

        return null;
    }

    /**
     * Ordena imagens garantindo que a "capa" (cover) vem primeiro quando a TelePeças a sinaliza no payload.
     *
     * @param  list<mixed>  $images
     * @return list<string>
     */
    private function normalizeImageUrls(array $images): array
    {
        $out = [];
        $seen = [];

        foreach ($images as $image) {
            if (is_string($image)) {
                $url = trim($image);
                if ($url === '') {
                    continue;
                }

                $key = mb_strtolower($url, 'UTF-8');
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $out[] = $url;
                }

                continue;
            }

            if (! is_array($image)) {
                continue;
            }

            $url = data_get($image, 'urlImage')
                ?? data_get($image, 'url')
                ?? data_get($image, 'urlImageThumbnail');

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

    /**
     * @param  array<string, mixed>  $raw
     */
    private function extractCategory(array $raw): string
    {
        $source = (string) config('telepecas.catalog.categories_source', 'stock');

        if ($source === 'makes') {
            $makeName = $this->extractFirstString($raw, (array) config('telepecas.catalog.make_name_paths', []));

            return $makeName !== '' ? $makeName : 'Outros';
        }

        $paths = (array) config('telepecas.catalog.category_paths', []);

        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $value = data_get($raw, $path);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return 'Outros';
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<mixed>  $paths
     */
    private function extractFirstString(array $raw, array $paths): string
    {
        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $value = data_get($raw, $path);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<mixed>  $paths
     */
    private function extractFirstScalar(array $raw, array $paths): mixed
    {
        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $value = data_get($raw, $path);

            if (is_scalar($value) && (string) $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $products
     * @param  callable(array<string, mixed>): string  $buildUrl
     * @return array<string, mixed>
     */
    private function pieceCategoryFacetsFromProducts(Collection $products, callable $buildUrl): array
    {
        $counts = [];

        foreach ($products as $p) {
            $name = $this->pieceCategoryName((string) ($p['title'] ?? ''));
            if ($name === '') {
                continue;
            }
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        $opts = [];

        foreach ($counts as $name => $count) {
            $slug = Str::slug($name);
            if ($slug === '') {
                continue;
            }

            $opts[] = [
                'slug' => $slug,
                'name' => $name,
                'count' => $count,
                'url' => $buildUrl(['piece' => $slug, 'page' => 1]),
            ];
        }

        usort($opts, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return [
            'piece_categories' => $opts,
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

    /**
     * @return array{0: string|null, 1: string}
     */
    private function parseCategorySlug(string $slug): array
    {
        $slug = trim($slug);

        if (preg_match('/^([0-9]+)-(.+)$/', $slug, $m) === 1) {
            return [$m[1], (string) $m[2]];
        }

        return [null, $slug];
    }
}
