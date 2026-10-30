<?php

use App\Services\Catalog\CatalogProvider;
use App\Services\Catalog\VehicleContextCatalogProvider;
use Illuminate\Pagination\LengthAwarePaginator;

test('product page shows vehicle details and related parts without a fake vehicle gallery', function () {
    $this->app->instance(CatalogProvider::class, new ProductPageVehicleCatalogFake);

    $response = $this->get('/loja/produtos/999-ref-999');

    $response
        ->assertOk()
        ->assertSeeText('Viatura de origem')
        ->assertSeeText('MG ZS SUV')
        ->assertSeeText('1.0 T-GDi')
        ->assertSeeText('82 kW / 111 cv')
        ->assertSeeText('ID da viatura')
        ->assertSeeText('10627')
        ->assertSeeText('Outras peças desta viatura')
        ->assertSeeText('Volante de Direção')
        ->assertSee('/loja/produtos/1000-vol-1000', false)
        ->assertDontSee('Fotografias da viatura');
});

test('product page remains compatible with a catalog without vehicle context', function () {
    $this->app->instance(CatalogProvider::class, new ProductPageCatalogFake);

    $this->get('/loja/produtos/999-ref-999')
        ->assertOk()
        ->assertDontSeeText('Viatura de origem')
        ->assertDontSeeText('Outras peças desta viatura');
});

class ProductPageCatalogFake implements CatalogProvider
{
    public function categories(): array
    {
        return [];
    }

    public function totalProducts(): int
    {
        return 1;
    }

    public function products(int $page = 1, int $perPage = 24): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, $page);
    }

    public function productsByCategory(string $categorySlug, int $page = 1, int $perPage = 24): array
    {
        return ['categoryName' => 'MG', 'paginator' => new LengthAwarePaginator([], 0, $perPage, $page)];
    }

    public function product(string $idOrReference): ?array
    {
        return [
            'id' => 999,
            'reference' => 'REF-999',
            'title' => 'Conjunto de Bancos',
            'make_name' => 'MG',
            'model_name' => 'MG ZS SUV',
            'price_ex_vat' => 0,
            'stock' => 1,
            'images' => [],
        ];
    }

    public function search(string $query, int $page = 1, int $perPage = 24): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, $page);
    }

    public function randomProducts(int $count = 24): array
    {
        return [];
    }

    public function modelsForMakeSlug(string $makeSlug): array
    {
        return [];
    }

    public function totalProductsBreakdown(): ?array
    {
        return null;
    }
}

class ProductPageVehicleCatalogFake extends ProductPageCatalogFake implements VehicleContextCatalogProvider
{
    public function vehicleContext(array $product): ?array
    {
        return [
            'id' => '10627',
            'make_name' => 'MG',
            'model_name' => 'MG ZS SUV',
            'variant_name' => '1.0 T-GDi',
            'type_name' => 'Ligeiro',
            'fuel_type' => 'Gasolina',
            'year' => '2017',
            'engine_code' => '10E4E',
            'power_kw' => '82',
            'power_cv' => '111',
            'images' => [],
            'products' => [[
                'id' => 1000,
                'reference' => 'VOL-1000',
                'title' => 'Volante de Direção',
                'price_ex_vat' => 0,
                'stock' => 1,
                'images' => [],
            ]],
        ];
    }
}
