<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\OrderStatusHistory;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected ?string $previousStatus = null;

    protected ?string $statusNote = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->previousStatus = (string) $this->record->status;
        $this->statusNote = trim((string) ($data['status_note'] ?? ''));
        unset($data['status_note']);

        return $data;
    }

    protected function afterSave(): void
    {
        $statusChanged = $this->previousStatus !== (string) $this->record->status;
        $note = $this->statusNote ?? '';

        if (! $statusChanged && $note === '') {
            return;
        }

        OrderStatusHistory::query()->create([
            'order_id' => $this->record->id,
            'status' => (string) $this->record->status,
            'note' => $note !== '' ? $note : 'Estado atualizado no admin.',
            'created_by_user_id' => auth()->id(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Voltar a lista')
                ->url(OrderResource::getUrl('index')),
        ];
    }
}
