<?php

namespace App\Filament\Resources\ShippingCarriers\Pages;

use App\Filament\Resources\ShippingCarriers\ShippingCarrierResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShippingCarrier extends EditRecord
{
    protected static string $resource = ShippingCarrierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

