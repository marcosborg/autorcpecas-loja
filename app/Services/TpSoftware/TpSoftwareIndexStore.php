<?php

namespace App\Services\TpSoftware;

use Illuminate\Filesystem\Filesystem;

class TpSoftwareIndexStore
{
    public function __construct(private readonly Filesystem $files)
    {
    }

    private const SCHEMA_VERSION = 2;

    private function indexPath(): string
    {
        return (string) config('tpsoftware.catalog.index_path', storage_path('app/tpsoftware/index.json'));
    }

    private function metaPath(): string
    {
        return (string) config('tpsoftware.catalog.index_meta_path', storage_path('app/tpsoftware/index.meta.json'));
    }

    public function exists(): bool
    {
        return $this->files->exists($this->indexPath());
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function load(): ?array
    {
        $path = $this->indexPath();

        if (! $this->files->exists($path)) {
            return null;
        }

        $json = $this->files->get($path);
        $data = json_decode($json, true);

        if (! is_array($data) || ! array_is_list($data)) {
            return null;
        }

        /** @var list<array<string, mixed>> */
        return array_values(array_filter($data, fn ($item) => is_array($item)));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function meta(): ?array
    {
        $path = $this->metaPath();

        if (! $this->files->exists($path)) {
            return null;
        }

        $json = $this->files->get($path);
        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    public function isFreshForCurrentConfig(): bool
    {
        $meta = $this->meta() ?? [];

        $schemaOk = ((int) ($meta['schema_version'] ?? 0)) === self::SCHEMA_VERSION;

        $currentLanguage = (string) config('tpsoftware.catalog.language', 'en');
        $metaLanguage = (string) ($meta['language'] ?? '');
        $languageOk = $metaLanguage === '' || $metaLanguage === $currentLanguage;

        return $schemaOk && $languageOk;
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    public function save(array $products, int $total): void
    {
        $path = $this->indexPath();
        $dir = dirname($path);

        if (! $this->files->isDirectory($dir)) {
            $this->files->makeDirectory($dir, 0755, true);
        }

        $this->files->put($path, json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $meta = [
            'generated_at' => now()->toISOString(),
            'total' => $total,
            'indexed' => count($products),
            'schema_version' => self::SCHEMA_VERSION,
            'language' => (string) config('tpsoftware.catalog.language', 'en'),
        ];

        $this->files->put($this->metaPath(), json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
