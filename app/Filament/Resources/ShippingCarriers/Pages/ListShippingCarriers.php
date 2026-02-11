<?php

namespace App\Filament\Resources\ShippingCarriers\Pages;

use App\Filament\Resources\ShippingCarriers\ShippingCarrierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShippingCarriers extends ListRecords
{
    protected static string $resource = ShippingCarrierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

