<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumo')
                    ->columns(4)
                    ->schema([
                        TextInput::make('order_number')
                            ->label('Numero')
                            ->disabled(),
                        Select::make('status')
                            ->label('Estado')
                            ->options(OrderResource::statusOptions())
                            ->required(),
                        TextInput::make('currency')
                            ->label('Moeda')
                            ->disabled(),
                        TextInput::make('placed_at')
                            ->label('Data')
                            ->disabled()
                            ->formatStateUsing(fn ($state): string => $state ? (string) $state : ''),
                        Placeholder::make('customer_name')
                            ->label('Cliente')
                            ->content(fn (?Order $record): string => (string) ($record?->user?->name ?? '-')),
                        Placeholder::make('customer_email')
                            ->label('Email cliente')
                            ->content(fn (?Order $record): string => (string) ($record?->user?->email ?? '-')),
                        TextInput::make('shipping_method_snapshot.name')
                            ->label('Transportadora')
                            ->disabled(),
                        TextInput::make('payment_method_snapshot.name')
                            ->label('Pagamento')
                            ->disabled(),
                    ]),

                Section::make('Totais')
                    ->columns(5)
                    ->schema([
                        TextInput::make('subtotal_ex_vat')
                            ->label('Subtotal s/ IVA')
                            ->numeric()
                            ->disabled(),
                        TextInput::make('shipping_ex_vat')
                            ->label('Portes s/ IVA')
                            ->numeric()
                            ->disabled(),
                        TextInput::make('payment_fee_ex_vat')
                            ->label('Taxa pagamento s/ IVA')
                            ->numeric()
                            ->disabled(),
                        TextInput::make('total_ex_vat')
                            ->label('Total s/ IVA')
                            ->numeric()
                            ->disabled(),
                        TextInput::make('total_inc_vat')
                            ->label('Total c/ IVA')
                            ->numeric()
                            ->disabled(),
                    ]),

                Section::make('Morada de envio')
                    ->schema([
                        Placeholder::make('shipping_address')
                            ->hiddenLabel()
                            ->content(fn (?Order $record): HtmlString => new HtmlString(self::formatAddress($record?->shipping_address_snapshot))),
                    ]),

                Section::make('Morada de faturacao')
                    ->schema([
                        Placeholder::make('billing_address')
                            ->hiddenLabel()
                            ->content(fn (?Order $record): HtmlString => new HtmlString(self::formatAddress($record?->billing_address_snapshot))),
                    ]),

                Section::make('Itens da encomenda')
                    ->schema([
                        Placeholder::make('items_table')
                            ->hiddenLabel()
                            ->content(fn (?Order $record): HtmlString => new HtmlString(self::formatItemsTable($record))),
                    ]),

                Section::make('Historico de estados')
                    ->schema([
                        Placeholder::make('status_history')
                            ->hiddenLabel()
                            ->content(fn (?Order $record): HtmlString => new HtmlString(self::formatStatusHistory($record))),
                    ]),

                Section::make('Notas')
                    ->schema([
                        Textarea::make('customer_note')
                            ->label('Nota do cliente')
                            ->disabled()
                            ->rows(3),
                        Textarea::make('status_note')
                            ->label('Nota interna desta alteracao')
                            ->rows(3)
                            ->helperText('Ao gravar, esta nota fica no historico de estados.'),
                    ]),
            ]);
    }

    /**
     * @param array<string, mixed>|null $snapshot
     */
    private static function formatAddress(?array $snapshot): string
    {
        if (! is_array($snapshot) || $snapshot === []) {
            return '<span class="text-gray-500">Sem dados.</span>';
        }

        $lines = array_filter([
            trim((string) (($snapshot['first_name'] ?? '').' '.($snapshot['last_name'] ?? ''))),
            (string) ($snapshot['company'] ?? ''),
            (string) ($snapshot['vat_number'] ?? ''),
            (string) ($snapshot['address_line1'] ?? ''),
            (string) ($snapshot['address_line2'] ?? ''),
            trim((string) (($snapshot['postal_code'] ?? '').' '.($snapshot['city'] ?? ''))),
            (string) ($snapshot['state'] ?? ''),
            (string) ($snapshot['country_iso2'] ?? ''),
            (string) ($snapshot['phone'] ?? ''),
        ], fn ($line): bool => trim((string) $line) !== '');

        if ($lines === []) {
            return '<span class="text-gray-500">Sem dados.</span>';
        }

        $escaped = array_map(fn ($line): string => e((string) $line), $lines);

        return implode('<br>', $escaped);
    }

    private static function formatItemsTable(?Order $record): string
    {
        if (! $record) {
            return '<span class="text-gray-500">Sem dados.</span>';
        }

        $items = $record->items()->orderBy('id')->get();
        if ($items->isEmpty()) {
            return '<span class="text-gray-500">Sem itens.</span>';
        }

        $rows = $items->map(function ($item): string {
            $title = e((string) $item->title);
            $ref = e((string) ($item->reference ?? ''));
            $qty = (int) $item->quantity;
            $unit = number_format((float) $item->unit_price_ex_vat, 2, ',', ' ');
            $line = number_format((float) $item->line_total_ex_vat, 2, ',', ' ');

            return '<tr>'
                .'<td style="padding:6px 8px;border-bottom:1px solid #eee;">'.$title.'</td>'
                .'<td style="padding:6px 8px;border-bottom:1px solid #eee;">'.$ref.'</td>'
                .'<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right;">'.$qty.'</td>'
                .'<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right;">'.$unit.'</td>'
                .'<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right;">'.$line.'</td>'
                .'</tr>';
        })->implode('');

        return '<div style="overflow:auto;">'
            .'<table style="width:100%;border-collapse:collapse;">'
            .'<thead><tr>'
            .'<th style="padding:6px 8px;text-align:left;border-bottom:1px solid #ddd;">Produto</th>'
            .'<th style="padding:6px 8px;text-align:left;border-bottom:1px solid #ddd;">Referencia</th>'
            .'<th style="padding:6px 8px;text-align:right;border-bottom:1px solid #ddd;">Qtd</th>'
            .'<th style="padding:6px 8px;text-align:right;border-bottom:1px solid #ddd;">Unit s/ IVA</th>'
            .'<th style="padding:6px 8px;text-align:right;border-bottom:1px solid #ddd;">Total s/ IVA</th>'
            .'</tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'</table>'
            .'</div>';
    }

    private static function formatStatusHistory(?Order $record): string
    {
        if (! $record) {
            return '<span class="text-gray-500">Sem dados.</span>';
        }

        $history = $record->statusHistory()->with('createdBy')->latest('id')->get();
        if ($history->isEmpty()) {
            return '<span class="text-gray-500">Sem historico.</span>';
        }

        return $history->map(function ($entry): string {
            $status = e((string) $entry->status);
            $note = trim((string) ($entry->note ?? ''));
            $noteHtml = $note !== '' ? '<div style="color:#374151;">'.e($note).'</div>' : '';
            $by = e((string) ($entry->createdBy?->name ?? 'sistema'));
            $at = e($entry->created_at?->format('d/m/Y H:i') ?? '');

            return '<div style="padding:8px 0;border-bottom:1px solid #eee;">'
                .'<div style="font-weight:600;">'.$status.'</div>'
                .$noteHtml
                .'<div style="font-size:12px;color:#6b7280;">'.$at.' - '.$by.'</div>'
                .'</div>';
        })->implode('');
    }
}
