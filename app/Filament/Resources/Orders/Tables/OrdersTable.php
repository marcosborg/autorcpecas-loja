<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->label('Numero')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => OrderResource::statusOptions()[$state ?? ''] ?? (string) $state)
                    ->sortable(),
                TextColumn::make('payment_method_snapshot.name')
                    ->label('Pagamento')
                    ->toggleable(),
                TextColumn::make('shipping_method_snapshot.name')
                    ->label('Transportadora')
                    ->toggleable(),
                TextColumn::make('total_inc_vat')
                    ->label('Total c/ IVA')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('placed_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(OrderResource::statusOptions()),
            ])
            ->recordActions([
                Action::make('changeStatus')
                    ->label('Mudar estado')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->form([
                        Select::make('status')
                            ->label('Novo estado')
                            ->options(OrderResource::statusOptions())
                            ->required(),
                        Textarea::make('note')
                            ->label('Nota')
                            ->rows(3)
                            ->maxLength(255),
                    ])
                    ->fillForm(fn (Order $record): array => [
                        'status' => $record->status,
                    ])
                    ->action(function (Order $record, array $data): void {
                        $newStatus = (string) ($data['status'] ?? $record->status);
                        $note = trim((string) ($data['note'] ?? ''));

                        $changed = $record->status !== $newStatus;
                        if ($changed) {
                            $record->status = $newStatus;
                            $record->save();
                        }

                        if ($changed || $note !== '') {
                            OrderStatusHistory::query()->create([
                                'order_id' => $record->id,
                                'status' => $record->status,
                                'note' => $note !== '' ? $note : 'Estado atualizado no admin.',
                                'created_by_user_id' => auth()->id(),
                            ]);
                        }
                    }),
                EditAction::make(),
            ]);
    }
}
