<?php

namespace App\Services\Catalog;

interface AdvancedCatalogProvider extends CatalogProvider
{
    public function searchAdvanced(CatalogSearchCriteria $criteria): CatalogSearchResult;
}
