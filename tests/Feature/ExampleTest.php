<?php

use App\Services\Catalog\CatalogProvider;

test('the application returns a successful response', function () {
    $catalog = $this->mock(CatalogProvider::class);
    $catalog->shouldReceive('randomProducts')->once()->with(16)->andReturn([]);
    $catalog->shouldReceive('categories')->once()->andReturn([]);

    $response = $this->get('/');

    $response->assertOk();
});
