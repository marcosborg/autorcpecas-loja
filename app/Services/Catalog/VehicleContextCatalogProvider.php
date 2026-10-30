<?php

namespace App\Services\Catalog;

interface VehicleContextCatalogProvider extends CatalogProvider
{
    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>|null
     */
    public function vehicleContext(array $product): ?array;
}
