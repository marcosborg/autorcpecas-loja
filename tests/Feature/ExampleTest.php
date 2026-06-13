<?php

use App\Services\Catalog\CatalogProvider;
use App\Services\Catalog\AdvancedCatalogProvider;
use App\Services\Catalog\CatalogSearchResult;
use Illuminate\Pagination\LengthAwarePaginator;

test('the application returns a successful response', function () {
    $catalog = $this->mock(CatalogProvider::class);
    $catalog->shouldReceive('randomProducts')->once()->with(16)->andReturn([]);
    $catalog->shouldReceive('categories')->once()->andReturn([]);

    $response = $this->get('/');

    $response->assertOk();
});

test('piece filters are available from every storefront page', function () {
    $catalog = $this->mock(AdvancedCatalogProvider::class);
    $this->app->instance(CatalogProvider::class, $catalog);

    $catalog->shouldReceive('randomProducts')->once()->with(16)->andReturn([]);
    $catalog->shouldReceive('categories')->once()->andReturn([]);
    $catalog->shouldReceive('searchAdvanced')->once()->andReturn(new CatalogSearchResult(
        paginator: new LengthAwarePaginator([], 0, 1),
        facets: [
            'makes' => [],
            'models' => [],
            'pieces' => [['slug' => 'botao', 'name' => 'Botão', 'count' => 714]],
        ],
    ));

    $this->get('/')
        ->assertOk()
        ->assertSee('value="botao"', false)
        ->assertSeeText('Botão (714)');
});
