<?php

namespace App\Filament\Resources\ProductCategoryWeights\Pages;

use App\Filament\Resources\ProductCategoryWeights\ProductCategoryWeightResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductCategoryWeight extends EditRecord
{
    protected static string $resource = ProductCategoryWeightResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
