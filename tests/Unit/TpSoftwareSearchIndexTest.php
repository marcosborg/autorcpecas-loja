<?php

namespace Tests\Unit;

use App\Services\Catalog\CatalogSearchCriteria;
use App\Services\TpSoftware\TpSoftwareSearchIndex;
use Tests\TestCase;

class TpSoftwareSearchIndexTest extends TestCase
{
    private string $indexPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->indexPath = storage_path('framework/testing/search-'.bin2hex(random_bytes(5)).'.sqlite');
        config()->set('tpsoftware.catalog.search_index_path', $this->indexPath);
    }

    protected function tearDown(): void
    {
        @unlink($this->indexPath);
        @unlink($this->indexPath.'.previous');

        parent::tearDown();
    }

    public function test_search_is_accent_case_and_alias_insensitive(): void
    {
        $index = app(TpSoftwareSearchIndex::class);
        $index->build($this->products(), count($this->products()));

        foreach (['citroen', 'Citroën', 'CITROEN', 'cItRoEn'] as $query) {
            $result = $index->search(new CatalogSearchCriteria(query: $query));
            $this->assertSame(2, $result->paginator->total(), $query);
        }

        $this->assertSame(1, $index->search(new CatalogSearchCriteria(query: 'botao vidro'))->paginator->total());
        $this->assertSame(1, $index->search(new CatalogSearchCriteria(query: 'Volkswagen'))->paginator->total());
        $this->assertSame(1, $index->search(new CatalogSearchCriteria(query: 'VW'))->paginator->total());
    }

    public function test_parachoques_alias_matches_spaced_and_hyphenated_names(): void
    {
        $index = app(TpSoftwareSearchIndex::class);
        $products = [
            ['id' => 10, 'reference' => 'PC-001', 'title' => 'Para-choques dianteiro', 'description' => '', 'make_name' => 'Peugeot', 'model_name' => '208', 'piece_name' => 'Para choques', 'part_code' => '', 'price_ex_vat' => 120, 'created_at' => '2026-01-01'],
        ];
        $index->build($products, count($products));

        foreach (['parachoques', 'para choques', 'para-choques'] as $query) {
            $result = $index->search(new CatalogSearchCriteria(query: $query));
            $this->assertSame(1, $result->paginator->total(), $query);
        }
    }

    public function test_filters_relevance_fuzzy_and_integrity(): void
    {
        $index = app(TpSoftwareSearchIndex::class);
        $products = $this->products();
        $products[] = $products[0];
        $audit = $index->build($products, count($products));

        $this->assertSame('ok', $audit['integrity']);
        $this->assertSame(4, $audit['products']);
        $this->assertSame(1, $audit['sourceDuplicateRows']);
        $this->assertSame(2, $audit['citroen']);

        $filtered = $index->search(new CatalogSearchCriteria(make: 'citroen', model: 'c4', piece: 'botao'));
        $this->assertSame(1, $filtered->paginator->total());

        $modelFacets = $index->search(new CatalogSearchCriteria(make: 'citroen', model: 'c4'));
        $this->assertSame(
            [['slug' => 'botao', 'name' => 'Botão', 'count' => 1]],
            $modelFacets->facets['pieces'],
        );

        $fuzzy = $index->search(new CatalogSearchCriteria(query: 'citroem'));
        $this->assertTrue($fuzzy->usedFuzzyFallback);
        $this->assertSame('citroen', $fuzzy->correctedQuery);
        $this->assertSame(2, $fuzzy->paginator->total());

        $reference = $index->search(new CatalogSearchCriteria(query: 'REF-C4'));
        $this->assertSame('REF-C4', $reference->paginator->items()[0]['reference']);
        $this->assertSame('Citroën', $reference->paginator->items()[0]['make_name']);
    }

    /** @return list<array<string, mixed>> */
    private function products(): array
    {
        return [
            ['id' => 1, 'reference' => 'REF-C4', 'title' => 'Botão Vidro', 'description' => 'Comando elétrico', 'make_name' => 'CITROËN', 'model_name' => 'C4', 'piece_name' => 'Botão', 'part_code' => 'INT-001', 'price_ex_vat' => 20, 'created_at' => '2026-01-03'],
            ['id' => 2, 'reference' => 'REF-C3', 'title' => 'Alternador Citroën', 'description' => 'Alternador completo', 'make_name' => 'Citroën', 'model_name' => 'C3', 'piece_name' => 'Alternador', 'part_code' => 'ALT-002', 'price_ex_vat' => 80, 'created_at' => '2026-01-02'],
            ['id' => 3, 'reference' => 'VW-001', 'title' => 'Farolim traseiro', 'description' => 'Farolim Volkswagen Golf', 'make_name' => 'VW', 'model_name' => 'Golf VII', 'piece_name' => 'Farolim', 'part_code' => 'LGT-003', 'price_ex_vat' => 50, 'created_at' => '2026-01-01'],
            ['id' => 4, 'reference' => 'PEU-001', 'title' => 'Motor de arranque', 'description' => 'Peça Peugeot 208', 'make_name' => 'PEUGEOT', 'model_name' => '208', 'piece_name' => 'Motor', 'part_code' => 'MOT-004', 'price_ex_vat' => 100, 'created_at' => '2025-12-31'],
        ];
    }
}
