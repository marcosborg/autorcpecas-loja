<?php

namespace Tests\Unit;

use App\Services\TpSoftware\TpSoftwareCatalogService;
use App\Services\TpSoftware\TpSoftwareClient;
use App\Services\TpSoftware\TpSoftwareIndexStore;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TpSoftwareCatalogServiceTest extends TestCase
{
    public function test_exact_reference_search_prefers_live_tp_results_over_stale_index(): void
    {
        config()->set('tpsoftware.catalog.index_enabled', true);
        config()->set('tpsoftware.cache_store', 'array');
        Cache::store('array')->flush();

        $client = $this->createMock(TpSoftwareClient::class);
        $indexStore = $this->createMock(TpSoftwareIndexStore::class);

        $indexStore->method('load')->willReturn([
            ['id' => 2304, 'reference' => 'E30176', 'title' => 'Peca 1', 'make_name' => 'A', 'model_name' => 'B', 'images' => []],
            ['id' => 2306, 'reference' => 'E30176', 'title' => 'Peca 2', 'make_name' => 'A', 'model_name' => 'B', 'images' => []],
        ]);

        $client->method('get')
            ->willReturn([
                'ok' => true,
                'status' => 200,
                'data' => [],
                'raw' => [],
            ]);

        $service = new TpSoftwareCatalogService($client, $indexStore);
        $result = $service->search('E30176');

        $this->assertSame(0, $result->total());
        $this->assertCount(0, $result->items());
    }

    public function test_exact_reference_search_falls_back_to_index_when_tp_unavailable(): void
    {
        config()->set('tpsoftware.catalog.index_enabled', true);
        config()->set('tpsoftware.cache_store', 'array');
        Cache::store('array')->flush();

        $client = $this->createMock(TpSoftwareClient::class);
        $indexStore = $this->createMock(TpSoftwareIndexStore::class);

        $indexStore->method('load')->willReturn([
            ['id' => 2304, 'reference' => 'E30176', 'title' => 'Peca 1', 'make_name' => 'A', 'model_name' => 'B', 'images' => []],
            ['id' => 2306, 'reference' => 'E30176', 'title' => 'Peca 2', 'make_name' => 'A', 'model_name' => 'B', 'images' => []],
        ]);

        $client->method('get')
            ->willReturn([
                'ok' => false,
                'status' => 503,
                'data' => null,
                'raw' => null,
            ]);

        $service = new TpSoftwareCatalogService($client, $indexStore);
        $result = $service->search('E30176');

        $this->assertSame(2, $result->total());
        $this->assertSame([2304, 2306], array_values(array_map(fn (array $p) => (int) $p['id'], $result->items())));
    }

    public function test_non_reference_search_uses_index_only(): void
    {
        config()->set('tpsoftware.catalog.index_enabled', true);
        config()->set('tpsoftware.cache_store', 'array');
        Cache::store('array')->flush();

        $client = $this->createMock(TpSoftwareClient::class);
        $indexStore = $this->createMock(TpSoftwareIndexStore::class);

        $indexStore->method('load')->willReturn([
            ['id' => 100, 'reference' => 'ABC123', 'title' => 'Botao vidro', 'make_name' => 'A', 'model_name' => 'B', 'images' => []],
            ['id' => 101, 'reference' => 'XYZ999', 'title' => 'Alternador', 'make_name' => 'A', 'model_name' => 'B', 'images' => []],
        ]);

        $client->expects($this->never())->method('get');

        $service = new TpSoftwareCatalogService($client, $indexStore);
        $result = $service->search('botao vidro');

        $this->assertSame(1, $result->total());
        $this->assertSame(100, (int) $result->items()[0]['id']);
    }

    public function test_partial_reference_query_keeps_index_substring_results(): void
    {
        config()->set('tpsoftware.catalog.index_enabled', true);
        config()->set('tpsoftware.cache_store', 'array');
        Cache::store('array')->flush();

        $client = $this->createMock(TpSoftwareClient::class);
        $indexStore = $this->createMock(TpSoftwareIndexStore::class);

        $indexStore->method('load')->willReturn([
            ['id' => 2306, 'reference' => 'E30176', 'title' => 'Peca 2', 'make_name' => 'A', 'model_name' => 'B', 'images' => []],
            ['id' => 2400, 'reference' => 'ZZZ999', 'title' => 'Outra', 'make_name' => 'A', 'model_name' => 'B', 'images' => []],
        ]);

        // Pesquisa exata live vazia (caso comum para query parcial), deve cair no filtro de substring do indice.
        $client->method('get')->willReturn([
            'ok' => true,
            'status' => 200,
            'data' => [],
            'raw' => [],
        ]);

        $service = new TpSoftwareCatalogService($client, $indexStore);
        $result = $service->search('e30');

        $this->assertSame(1, $result->total());
        $this->assertSame(2306, (int) $result->items()[0]['id']);
    }

    public function test_exact_reference_uses_part_price_when_price_fields_are_missing(): void
    {
        config()->set('tpsoftware.catalog.index_enabled', true);
        config()->set('tpsoftware.cache_store', 'array');
        Cache::store('array')->flush();

        $client = $this->createMock(TpSoftwareClient::class);
        $indexStore = $this->createMock(TpSoftwareIndexStore::class);

        $indexStore->method('load')->willReturn([
            ['id' => 7561, 'reference' => '96FG15K237AA', 'title' => 'Botao', 'make_name' => 'FORD', 'model_name' => 'FIESTA', 'images' => []],
        ]);

        $client->method('get')->willReturn([
            'ok' => true,
            'status' => 200,
            'data' => [[
                'id' => 7561,
                'part_price' => '10.00',
                'vat_included' => 0,
                'part_code' => '96FG15K237AA',
                'product_name' => 'Botao',
                'vehicle_make_name' => 'FORD',
                'vehicle_model_name' => 'FIESTA',
                'image_list' => [],
            ]],
            'raw' => null,
        ]);

        $service = new TpSoftwareCatalogService($client, $indexStore);
        $result = $service->search('96FG15K237AA');
        $item = $result->items()[0] ?? null;

        $this->assertNotNull($item);
        $this->assertSame(10.0, (float) ($item['price'] ?? 0));
        $this->assertSame(10.0, (float) ($item['price_ex_vat'] ?? 0));
    }
}
