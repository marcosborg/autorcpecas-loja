<?php

namespace App\Filament\Resources\ProductCategoryWeights\Pages;

use App\Filament\Resources\ProductCategoryWeights\ProductCategoryWeightResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductCategoryWeights extends ListRecords
{
    protected static string $resource = ProductCategoryWeightResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
