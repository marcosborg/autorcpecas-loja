<?php

namespace App\Services\TpSoftware;

use App\Services\Catalog\CatalogSearchCriteria;
use App\Services\Catalog\CatalogSearchResult;
use App\Services\Catalog\SearchTextNormalizer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use RuntimeException;
use SQLite3;

final class TpSoftwareSearchIndex
{
    public function __construct(private readonly SearchTextNormalizer $normalizer)
    {
    }

    private function path(): string
    {
        return (string) config('tpsoftware.catalog.search_index_path', storage_path('app/tpsoftware/search.sqlite'));
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    /** @param list<array<string, mixed>> $products */
    public function build(array $products, int $expectedTotal): array
    {
        $path = $this->path();
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Não foi possível criar a pasta do índice de pesquisa.');
        }

        $temporary = $path.'.tmp-'.bin2hex(random_bytes(6));
        $db = new SQLite3($temporary);
        $db->busyTimeout(5000);

        try {
            $this->createSchema($db);
            $db->exec('BEGIN IMMEDIATE');

            $insert = $db->prepare('INSERT INTO products (
                lookup_id, reference, reference_norm, title, title_norm, description, make_name, make_key,
                model_name, model_key, piece_name, piece_key, codes, codes_norm, engine, price, created_at, payload
            ) VALUES (
                :lookup_id, :reference, :reference_norm, :title, :title_norm, :description, :make_name, :make_key,
                :model_name, :model_key, :piece_name, :piece_key, :codes, :codes_norm, :engine, :price, :created_at, :payload
            )');
            $insertFts = $db->prepare('INSERT INTO products_fts (
                rowid, title, reference, make, model, description, codes, engine, piece, aliases
            ) VALUES (:rowid, :title, :reference, :make, :model, :description, :codes, :engine, :piece, :aliases)');

            $seen = [];
            $sourceDuplicates = 0;
            $makeCounts = [];
            $vocabulary = [];

            foreach ($products as $product) {
                $lookupId = trim((string) ($product['id'] ?? ''));
                if ($lookupId === '') {
                    $lookupId = trim((string) ($product['reference'] ?? ''));
                }
                if ($lookupId === '') {
                    throw new RuntimeException('Produto sem ID único no índice: '.$lookupId);
                }
                $sourceHash = hash('sha256', json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                if (isset($seen[$lookupId])) {
                    if ($seen[$lookupId] !== $sourceHash) {
                        throw new RuntimeException('ID repetido com dados divergentes no índice: '.$lookupId);
                    }
                    $sourceDuplicates++;
                    continue;
                }
                $seen[$lookupId] = $sourceHash;

                $make = trim((string) ($product['make_name'] ?? $product['category'] ?? ''));
                if ($make === '') {
                    throw new RuntimeException('Produto sem marca no índice: '.$lookupId);
                }

                $reference = trim((string) ($product['reference'] ?? ''));
                $title = trim((string) ($product['title'] ?? ''));
                $description = trim((string) ($product['description'] ?? ''));
                $model = trim((string) ($product['model_name'] ?? ''));
                $piece = trim((string) ($product['piece_name'] ?? $this->pieceName($title)));
                $codes = implode(' ', array_filter([
                    $product['part_code'] ?? null,
                    $product['tp_reference'] ?? null,
                    $product['barcode'] ?? null,
                    ...((array) ($product['additional_references'] ?? [])),
                ], static fn ($value): bool => is_scalar($value) && trim((string) $value) !== ''));
                $engine = trim(implode(' ', array_filter([
                    $product['engine_code'] ?? null,
                    $product['engine_label'] ?? null,
                    $product['fuel_type'] ?? null,
                ], static fn ($value): bool => is_scalar($value) && trim((string) $value) !== '')));
                $makeKey = $this->normalizer->makeKey($make);
                $canonicalMake = $this->normalizer->canonicalMake($make);
                $modelKey = $this->normalizer->normalize($model);
                $pieceKey = $this->normalizer->normalize($piece);
                $aliases = $makeKey === 'volkswagen' ? 'vw volkswagen' : ($makeKey === 'mercedes benz' ? 'mercedes mercedesbenz mercedes benz' : $makeKey);

                $storedProduct = $product;
                $storedProduct['make_name'] = $canonicalMake;
                $storedProduct['category'] = $canonicalMake;

                $values = [
                    ':lookup_id' => $lookupId,
                    ':reference' => $reference,
                    ':reference_norm' => $this->normalizer->compact($reference),
                    ':title' => $title,
                    ':title_norm' => $this->normalizer->normalize($title),
                    ':description' => $description,
                    ':make_name' => $canonicalMake,
                    ':make_key' => $makeKey,
                    ':model_name' => $model,
                    ':model_key' => $modelKey,
                    ':piece_name' => $piece,
                    ':piece_key' => $pieceKey,
                    ':codes' => $codes,
                    ':codes_norm' => $this->normalizer->compact($codes),
                    ':engine' => $engine,
                    ':price' => is_numeric($product['price_ex_vat'] ?? null) ? (float) $product['price_ex_vat'] : null,
                    ':created_at' => (string) ($product['created_at'] ?? ''),
                    ':payload' => json_encode($storedProduct, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];
                $this->bindAndExecute($insert, $values);
                $rowId = $db->lastInsertRowID();

                $this->bindAndExecute($insertFts, [
                    ':rowid' => $rowId,
                    ':title' => $title,
                    ':reference' => $reference,
                    ':make' => $canonicalMake.' '.$makeKey,
                    ':model' => $model,
                    ':description' => $description,
                    ':codes' => $codes,
                    ':engine' => $engine,
                    ':piece' => $piece,
                    ':aliases' => $aliases,
                ]);

                $makeCounts[$makeKey] = ($makeCounts[$makeKey] ?? 0) + 1;
                foreach ($this->normalizer->terms(implode(' ', [$title, $canonicalMake, $model, $piece])) as $term) {
                    if (mb_strlen($term, 'UTF-8') >= 4) {
                        $vocabulary[$term] = ($vocabulary[$term] ?? 0) + 1;
                    }
                }
            }

            $termInsert = $db->prepare('INSERT INTO vocabulary (term, frequency) VALUES (:term, :frequency)');
            foreach ($vocabulary as $term => $frequency) {
                $this->bindAndExecute($termInsert, [':term' => $term, ':frequency' => $frequency]);
            }

            $metaInsert = $db->prepare('INSERT INTO metadata (key, value) VALUES (:key, :value)');
            foreach ([
                'generated_at' => now()->toISOString(),
                'source_total' => (string) $expectedTotal,
                'expected_total' => (string) count($seen),
                'indexed_total' => (string) count($seen),
                'source_duplicate_rows' => (string) $sourceDuplicates,
                'schema_version' => '1',
                'make_counts' => json_encode($makeCounts, JSON_UNESCAPED_UNICODE),
            ] as $key => $value) {
                $this->bindAndExecute($metaInsert, [':key' => $key, ':value' => $value]);
            }

            $db->exec('COMMIT');
            $audit = $this->auditDatabase($db, count($seen));
            $audit['sourceTotal'] = $expectedTotal;
            $audit['sourceDuplicateRows'] = $sourceDuplicates;
            $db->close();

            $backup = $path.'.previous';
            if (is_file($backup)) {
                @unlink($backup);
            }
            if (is_file($path) && ! rename($path, $backup)) {
                throw new RuntimeException('Não foi possível preservar o índice anterior.');
            }
            if (! rename($temporary, $path)) {
                if (is_file($backup)) {
                    @rename($backup, $path);
                }
                throw new RuntimeException('Não foi possível promover o novo índice.');
            }

            return $audit;
        } catch (\Throwable $e) {
            @$db->exec('ROLLBACK');
            @$db->close();
            @unlink($temporary);
            throw $e;
        }
    }

    public function audit(): array
    {
        $db = $this->open();
        try {
            $expected = (int) ($db->querySingle("SELECT value FROM metadata WHERE key = 'expected_total'") ?: 0);

            return $this->auditDatabase($db, $expected);
        } finally {
            $db->close();
        }
    }

    public function search(CatalogSearchCriteria $criteria): CatalogSearchResult
    {
        $result = $this->runSearch($criteria, $criteria->query, false);
        if ($result->paginator->total() >= 5 || trim($criteria->query) === '') {
            return $result;
        }

        $corrected = $this->correctQuery($criteria->query);
        if ($corrected === null || $corrected === $this->normalizer->normalize($criteria->query)) {
            return $result;
        }

        $fallback = $this->runSearch($criteria, $corrected, true);
        if ($fallback->paginator->total() <= $result->paginator->total()) {
            return $result;
        }

        if ($result->paginator->total() === 0 || $criteria->page > 1) {
            return $fallback;
        }

        $items = [];
        $seen = [];
        foreach ([...$result->paginator->items(), ...$fallback->paginator->items()] as $product) {
            $key = trim((string) ($product['id'] ?? $product['reference'] ?? ''));
            if ($key !== '' && isset($seen[$key])) {
                continue;
            }
            if ($key !== '') {
                $seen[$key] = true;
            }
            $items[] = $product;
            if (count($items) >= $criteria->perPage) {
                break;
            }
        }

        return new CatalogSearchResult(
            paginator: new LengthAwarePaginator(
                $items,
                $result->paginator->total() + $fallback->paginator->total(),
                $criteria->perPage,
                $criteria->page,
                ['path' => url('/loja/pesquisa'), 'query' => request()->query()],
            ),
            facets: $fallback->facets,
            correctedQuery: $corrected,
            usedFuzzyFallback: true,
        );
    }

    /** @return list<array{slug: string, name: string, count: int}> */
    public function makes(): array
    {
        return $this->groupOptions('make_key', 'make_name');
    }

    /** @return list<array{slug: string, name: string, count: int}> */
    public function models(string $make): array
    {
        $makeKey = $this->normalizer->makeKey($make);
        if ($makeKey === '') {
            return [];
        }

        return $this->groupOptions('model_key', 'model_name', 'make_key = :make', [':make' => $makeKey]);
    }

    /** @return list<array{slug: string, name: string, count: int}> */
    public function pieces(): array
    {
        return $this->groupOptions('piece_key', 'piece_name');
    }

    public function product(string $lookup): ?array
    {
        $db = $this->open();
        try {
            $stmt = $db->prepare('SELECT payload FROM products WHERE lookup_id = :lookup OR reference_norm = :normalized OR codes_norm = :normalized LIMIT 1');
            $this->bind($stmt, ':lookup', $lookup);
            $this->bind($stmt, ':normalized', $this->normalizer->compact($lookup));
            $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

            return $row ? $this->decodeProduct((string) $row['payload']) : null;
        } finally {
            $db->close();
        }
    }

    /** @return list<array<string, mixed>> */
    public function random(int $count): array
    {
        $db = $this->open();
        try {
            $result = $db->query('SELECT payload FROM products ORDER BY RANDOM() LIMIT '.max(1, min(200, $count)));
            $items = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $items[] = $this->decodeProduct((string) $row['payload']);
            }

            return $items;
        } finally {
            $db->close();
        }
    }

    private function runSearch(CatalogSearchCriteria $criteria, string $query, bool $fuzzy): CatalogSearchResult
    {
        $db = $this->open();
        try {
            $normalizedQuery = $this->normalizer->normalize($query);
            $whereBindings = [];
            $rankBindings = [];
            $where = [];
            $join = '';
            $rank = '0';

            if ($normalizedQuery !== '') {
                $fts = $this->normalizer->ftsQuery($normalizedQuery);
                $join = 'JOIN products_fts ON products_fts.rowid = p.product_pk';
                $where[] = 'products_fts MATCH :fts';
                $whereBindings[':fts'] = $fts;
                $compact = $this->normalizer->compact($normalizedQuery);
                $rankBindings[':query'] = $normalizedQuery;
                $rankBindings[':compact'] = $compact;
                $rank = '(CASE
                    WHEN p.reference_norm = :compact OR p.codes_norm = :compact THEN 0
                    WHEN p.title_norm = :query THEN 1
                    WHEN p.title_norm LIKE :query_prefix OR p.reference_norm LIKE :compact_prefix THEN 2
                    WHEN p.make_key = :query OR p.model_key = :query THEN 3
                    ELSE 10 + bm25(products_fts, 2.0, 5.0, 1.3, 1.2, 0.8, 3.5, 0.6, 1.0, 1.0)
                END)';
                $rankBindings[':query_prefix'] = $normalizedQuery.'%';
                $rankBindings[':compact_prefix'] = $compact.'%';
            }

            if ($criteria->make !== '') {
                $where[] = 'p.make_key = :make';
                $whereBindings[':make'] = $this->normalizer->makeKey($criteria->make);
            }
            if ($criteria->model !== '') {
                $where[] = 'p.model_key = :model';
                $whereBindings[':model'] = $this->normalizer->normalize($criteria->model);
            }
            if ($criteria->piece !== '') {
                $where[] = 'p.piece_key = :piece';
                $whereBindings[':piece'] = $this->normalizer->normalize($criteria->piece);
            }

            $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';
            $count = $this->scalar($db, "SELECT COUNT(*) FROM products p {$join} {$whereSql}", $whereBindings);
            $order = match ($criteria->sort) {
                'price_asc' => 'p.price IS NULL, p.price ASC, p.title COLLATE NOCASE ASC',
                'price_desc' => 'p.price IS NULL, p.price DESC, p.title COLLATE NOCASE ASC',
                'newest' => "p.created_at = '', p.created_at DESC, p.product_pk DESC",
                'oldest' => "p.created_at = '', p.created_at ASC, p.product_pk ASC",
                'name_asc' => 'p.title COLLATE NOCASE ASC',
                'name_desc' => 'p.title COLLATE NOCASE DESC',
                default => $normalizedQuery !== '' ? $rank.', p.title COLLATE NOCASE ASC' : 'p.created_at DESC, p.product_pk DESC',
            };
            $offset = ($criteria->page - 1) * $criteria->perPage;
            $sql = "SELECT p.payload FROM products p {$join} {$whereSql} ORDER BY {$order} LIMIT :limit OFFSET :offset";
            $stmt = $db->prepare($sql);
            foreach ([...$whereBindings, ...$rankBindings] as $key => $value) {
                $this->bind($stmt, $key, $value);
            }
            $stmt->bindValue(':limit', $criteria->perPage, SQLITE3_INTEGER);
            $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
            $rows = $stmt->execute();
            $items = [];
            while ($row = $rows->fetchArray(SQLITE3_ASSOC)) {
                $items[] = $this->decodeProduct((string) $row['payload']);
            }

            $path = url('/loja/pesquisa');
            $paginator = new LengthAwarePaginator($items, $count, $criteria->perPage, $criteria->page, [
                'path' => $path,
                'query' => request()->query(),
            ]);

            return new CatalogSearchResult(
                paginator: $paginator,
                facets: $criteria->includeFacets ? [
                    'makes' => $this->makes(),
                    'models' => $criteria->make !== '' ? $this->models($criteria->make) : [],
                    'pieces' => $this->piecesForCriteria($criteria, $query),
                ] : [],
                correctedQuery: $fuzzy ? $query : null,
                usedFuzzyFallback: $fuzzy,
            );
        } finally {
            $db->close();
        }
    }

    private function correctQuery(string $query): ?string
    {
        $terms = $this->normalizer->terms($query);
        if ($terms === []) {
            return null;
        }

        $db = $this->open();
        try {
            $vocabulary = [];
            $result = $db->query('SELECT term, frequency FROM vocabulary');
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $vocabulary[(string) $row['term']] = (int) $row['frequency'];
            }
        } finally {
            $db->close();
        }

        $changed = false;
        foreach ($terms as &$term) {
            $length = strlen($term);
            if ($length < 4 || preg_match('/\d/', $term) === 1 || isset($vocabulary[$term])) {
                continue;
            }
            $maxDistance = $length >= 8 ? 2 : 1;
            $best = null;
            $bestDistance = $maxDistance + 1;
            $bestFrequency = -1;
            foreach ($vocabulary as $candidate => $frequency) {
                if (abs(strlen($candidate) - $length) > $maxDistance) {
                    continue;
                }
                $distance = levenshtein($term, $candidate);
                if ($distance < $bestDistance || ($distance === $bestDistance && $frequency > $bestFrequency)) {
                    $best = $candidate;
                    $bestDistance = $distance;
                    $bestFrequency = $frequency;
                }
            }
            if ($best !== null && $bestDistance <= $maxDistance) {
                $term = $best;
                $changed = true;
            }
        }
        unset($term);

        return $changed ? implode(' ', $terms) : null;
    }

    /** @return list<array{slug: string, name: string, count: int}> */
    private function piecesForCriteria(CatalogSearchCriteria $criteria, string $query): array
    {
        $db = $this->open();
        try {
            $where = ["p.piece_key != ''"];
            $bindings = [];
            $join = '';
            $normalizedQuery = $this->normalizer->normalize($query);

            if ($normalizedQuery !== '') {
                $join = 'JOIN products_fts ON products_fts.rowid = p.product_pk';
                $where[] = 'products_fts MATCH :fts';
                $bindings[':fts'] = $this->normalizer->ftsQuery($normalizedQuery);
            }
            if ($criteria->make !== '') {
                $where[] = 'p.make_key = :make';
                $bindings[':make'] = $this->normalizer->makeKey($criteria->make);
            }
            if ($criteria->model !== '') {
                $where[] = 'p.model_key = :model';
                $bindings[':model'] = $this->normalizer->normalize($criteria->model);
            }

            $stmt = $db->prepare('SELECT p.piece_key AS option_key, MIN(p.piece_name) AS option_name, COUNT(*) AS total
                FROM products p '.$join.' WHERE '.implode(' AND ', $where).'
                GROUP BY p.piece_key ORDER BY option_name COLLATE NOCASE');
            foreach ($bindings as $key => $value) {
                $this->bind($stmt, $key, $value);
            }

            $result = $stmt->execute();
            $options = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $options[] = [
                    'slug' => Str::slug((string) $row['option_key']),
                    'name' => (string) $row['option_name'],
                    'count' => (int) $row['total'],
                ];
            }

            return $options;
        } finally {
            $db->close();
        }
    }

    private function createSchema(SQLite3 $db): void
    {
        $db->exec('PRAGMA journal_mode = DELETE');
        $db->exec('PRAGMA synchronous = FULL');
        $db->exec('CREATE TABLE products (
            product_pk INTEGER PRIMARY KEY AUTOINCREMENT,
            lookup_id TEXT NOT NULL UNIQUE,
            reference TEXT NOT NULL DEFAULT "",
            reference_norm TEXT NOT NULL DEFAULT "",
            title TEXT NOT NULL,
            title_norm TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT "",
            make_name TEXT NOT NULL,
            make_key TEXT NOT NULL,
            model_name TEXT NOT NULL DEFAULT "",
            model_key TEXT NOT NULL DEFAULT "",
            piece_name TEXT NOT NULL DEFAULT "",
            piece_key TEXT NOT NULL DEFAULT "",
            codes TEXT NOT NULL DEFAULT "",
            codes_norm TEXT NOT NULL DEFAULT "",
            engine TEXT NOT NULL DEFAULT "",
            price REAL NULL,
            created_at TEXT NOT NULL DEFAULT "",
            payload TEXT NOT NULL
        )');
        $db->exec("CREATE VIRTUAL TABLE products_fts USING fts5(
            title, reference, make, model, description, codes, engine, piece, aliases,
            tokenize = 'unicode61 remove_diacritics 2'
        )");
        $db->exec('CREATE TABLE vocabulary (term TEXT PRIMARY KEY, frequency INTEGER NOT NULL)');
        $db->exec('CREATE TABLE metadata (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
        $db->exec('CREATE INDEX products_make_model ON products(make_key, model_key)');
        $db->exec('CREATE INDEX products_piece ON products(piece_key)');
        $db->exec('CREATE INDEX products_reference ON products(reference_norm)');
        $db->exec('CREATE INDEX products_price ON products(price)');
    }

    private function auditDatabase(SQLite3 $db, int $expectedTotal): array
    {
        $products = (int) $db->querySingle('SELECT COUNT(*) FROM products');
        $fts = (int) $db->querySingle('SELECT COUNT(*) FROM products_fts');
        $emptyMakes = (int) $db->querySingle("SELECT COUNT(*) FROM products WHERE make_key = ''");
        $duplicates = (int) $db->querySingle('SELECT COUNT(*) FROM (SELECT lookup_id FROM products GROUP BY lookup_id HAVING COUNT(*) > 1)');
        $integrity = (string) $db->querySingle('PRAGMA integrity_check');
        $citroen = (int) $db->querySingle("SELECT COUNT(*) FROM products WHERE make_key = 'citroen'");

        if ($integrity !== 'ok' || $products !== $expectedTotal || $fts !== $products || $emptyMakes > 0 || $duplicates > 0) {
            throw new RuntimeException('Índice inválido: '.json_encode(compact('integrity', 'expectedTotal', 'products', 'fts', 'emptyMakes', 'duplicates')));
        }

        return compact('integrity', 'expectedTotal', 'products', 'fts', 'emptyMakes', 'duplicates', 'citroen');
    }

    /** @return list<array{slug: string, name: string, count: int}> */
    private function groupOptions(string $keyColumn, string $nameColumn, string $where = '1=1', array $bindings = []): array
    {
        $allowed = ['make_key', 'make_name', 'model_key', 'model_name', 'piece_key', 'piece_name'];
        if (! in_array($keyColumn, $allowed, true) || ! in_array($nameColumn, $allowed, true)) {
            throw new RuntimeException('Coluna de facet inválida.');
        }

        $db = $this->open();
        try {
            $stmt = $db->prepare("SELECT {$keyColumn} AS option_key, MIN({$nameColumn}) AS option_name, COUNT(*) AS total
                FROM products WHERE {$where} AND {$keyColumn} != '' GROUP BY {$keyColumn} ORDER BY option_name COLLATE NOCASE");
            foreach ($bindings as $key => $value) {
                $this->bind($stmt, $key, $value);
            }
            $result = $stmt->execute();
            $options = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $options[] = [
                    'slug' => Str::slug((string) $row['option_key']),
                    'name' => (string) $row['option_name'],
                    'count' => (int) $row['total'],
                ];
            }

            return $options;
        } finally {
            $db->close();
        }
    }

    private function open(): SQLite3
    {
        if (! $this->exists()) {
            throw new RuntimeException('Índice de pesquisa ainda não foi gerado. Corre: php artisan tpsoftware:index');
        }
        $db = new SQLite3($this->path(), SQLITE3_OPEN_READONLY);
        $db->busyTimeout(5000);

        return $db;
    }

    private function scalar(SQLite3 $db, string $sql, array $bindings): int
    {
        $stmt = $db->prepare($sql);
        foreach ($bindings as $key => $value) {
            $this->bind($stmt, $key, $value);
        }

        return (int) $stmt->execute()->fetchArray(SQLITE3_NUM)[0];
    }

    private function bindAndExecute(\SQLite3Stmt $stmt, array $values): void
    {
        foreach ($values as $key => $value) {
            $this->bind($stmt, $key, $value);
        }
        if ($stmt->execute() === false) {
            throw new RuntimeException('Falha a escrever no índice SQLite.');
        }
        $stmt->reset();
        $stmt->clear();
    }

    private function bind(\SQLite3Stmt $stmt, string $key, mixed $value): void
    {
        $type = match (true) {
            is_int($value) => SQLITE3_INTEGER,
            is_float($value) => SQLITE3_FLOAT,
            $value === null => SQLITE3_NULL,
            default => SQLITE3_TEXT,
        };
        $stmt->bindValue($key, $value, $type);
    }

    /** @return array<string, mixed> */
    private function decodeProduct(string $payload): array
    {
        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function pieceName(string $title): string
    {
        $title = trim(preg_replace('/\s*\(.*?\)\s*/', ' ', $title) ?? $title);
        $chunk = preg_split('/\s*(?:\p{Pd}|\||\/)+\s*/u', $title)[0] ?? $title;
        $words = array_values(array_filter(preg_split('/\s+/u', trim($chunk)) ?: []));
        if ($words === []) {
            return '';
        }
        $take = in_array($this->normalizer->normalize($words[0]), ['kit', 'jogo', 'conjunto'], true) ? 2 : 1;

        return mb_convert_case(mb_strtolower(implode(' ', array_slice($words, 0, $take)), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }
}
