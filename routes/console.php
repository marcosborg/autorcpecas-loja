<?php

use App\Jobs\ReindexTpSoftware;
use App\Models\User;
use App\Services\Seo\SitemapGenerator;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('user:admin {email} {--demote : Remove permissoes de admin}', function (string $email) {
    $user = User::query()->where('email', $email)->first();

    if (! $user) {
        $this->error("Utilizador nao encontrado: {$email}");

        return 1;
    }

    $user->is_admin = ! $this->option('demote');
    $user->save();

    $this->info($user->is_admin ? 'Utilizador promovido a admin.' : 'Utilizador removido de admin.');

    return 0;
})->purpose('Promove (ou remove) um utilizador como admin do Filament');

Artisan::command('tpsoftware:index {--force : Recria o indice}', function () {
    /** @var \App\Services\TpSoftware\TpSoftwareCatalogService $catalog */
    $catalog = app(\App\Services\TpSoftware\TpSoftwareCatalogService::class);

    $force = (bool) $this->option('force');

    $this->info('A construir indice TP Software (pode demorar alguns minutos)...');

    $result = $catalog->buildIndex($force);

    $this->info("Total (API): {$result['total']}");
    $this->info("Indexados: {$result['indexed']}");
    $this->info("Pages (estimado): {$result['pages']}");

    return 0;
})->purpose('Constroi indice local (cache) do inventario TP Software para a vitrine');

Artisan::command('tpsoftware:search-index {--repair : Reconstroi o indice FTS a partir do JSON quando a auditoria falhar}', function () {
    /** @var \App\Services\TpSoftware\TpSoftwareCatalogService $catalog */
    $catalog = app(\App\Services\TpSoftware\TpSoftwareCatalogService::class);

    try {
        $audit = $catalog->auditSearchIndex();
        $this->info('Indice FTS valido.');
        $this->line(json_encode($audit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return 0;
    } catch (Throwable $e) {
        $this->warn('Auditoria falhou: '.$e->getMessage());
        if (! $this->option('repair')) {
            return 1;
        }
    }

    $audit = $catalog->rebuildSearchIndexFromStoredIndex();
    $this->info('Indice FTS reconstruido.');
    $this->line(json_encode($audit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    return 0;
})->purpose('Audita e opcionalmente repara o indice SQLite FTS5');

Artisan::command('tpsoftware:index:queue {--force : Recria o indice}', function () {
    if ((string) config('storefront.catalog_provider', 'telepecas') !== 'tpsoftware') {
        $this->info('Ignorado: STOREFRONT_CATALOG_PROVIDER != tpsoftware.');

        return 0;
    }

    $lockTtlSeconds = (int) env('TPSOFTWARE_INDEX_QUEUE_LOCK_SECONDS', 1800);
    $lockTtlSeconds = max(60, min(86400, $lockTtlSeconds));

    if (! Cache::add(ReindexTpSoftware::QUEUE_LOCK_KEY, now()->toIso8601String(), $lockTtlSeconds)) {
        $this->info('Reindex ja em curso (ou recentemente enfileirado).');

        return 0;
    }

    $force = (bool) $this->option('force');

    Storage::disk('local')->put(
        'maintenance/tpsoftware-index.json',
        json_encode([
            'status' => 'queued',
            'force' => $force,
            'queued_at' => now()->toIso8601String(),
            'source' => 'scheduler',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    ReindexTpSoftware::dispatch($force);
    $this->info('Reindex TP Software enfileirado.');

    return 0;
})->purpose('Enfileira reindex TP Software (com lock para evitar duplicados)');

Artisan::command('seo:sitemap:generate {--max-products=50000 : Maximo de produtos no sitemap}', function () {
    $startedAt = now()->toIso8601String();

    Storage::disk('local')->put(
        'maintenance/sitemap.json',
        json_encode([
            'status' => 'running',
            'started_at' => $startedAt,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    try {
        /** @var SitemapGenerator $generator */
        $generator = app(SitemapGenerator::class);
        $maxProducts = (int) $this->option('max-products');
        $result = $generator->generate($maxProducts);

        Storage::disk('local')->put(
            'maintenance/sitemap.json',
            json_encode([
                'status' => 'ok',
                'started_at' => $startedAt,
                'finished_at' => now()->toIso8601String(),
                'result' => $result,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->info('Sitemap gerado com sucesso.');
        $this->line('Ficheiro: '.$result['path']);
        $this->line('Total URLs: '.$result['total_urls']);
        $this->line('Produtos: '.$result['product_urls']);
        $this->line('Categorias: '.$result['category_urls']);
        $this->line('CMS: '.$result['cms_urls']);

        return 0;
    } catch (Throwable $e) {
        Storage::disk('local')->put(
            'maintenance/sitemap.json',
            json_encode([
                'status' => 'error',
                'started_at' => $startedAt,
                'finished_at' => now()->toIso8601String(),
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->error($e->getMessage());

        return 1;
    }
})->purpose('Gera sitemap.xml para SEO');

$scheduleEnabled = filter_var(env('TPSOFTWARE_INDEX_SCHEDULE_ENABLED', true), FILTER_VALIDATE_BOOL);
$scheduleEveryMinutes = (int) env('TPSOFTWARE_INDEX_SCHEDULE_EVERY_MINUTES', 15);
$scheduleEveryMinutes = max(5, min(60, $scheduleEveryMinutes));

if ($scheduleEnabled) {
    $event = Schedule::command('tpsoftware:index --force')->withoutOverlapping(90);

    match ($scheduleEveryMinutes) {
        5 => $event->everyFiveMinutes(),
        10 => $event->everyTenMinutes(),
        15 => $event->everyFifteenMinutes(),
        30 => $event->everyThirtyMinutes(),
        60 => $event->hourly(),
        default => $event->everyFifteenMinutes(),
    };
}
