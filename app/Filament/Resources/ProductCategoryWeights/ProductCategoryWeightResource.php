<?php

namespace App\Filament\Resources\ProductCategoryWeights;

use App\Filament\Resources\ProductCategoryWeights\Pages\CreateProductCategoryWeight;
use App\Filament\Resources\ProductCategoryWeights\Pages\EditProductCategoryWeight;
use App\Filament\Resources\ProductCategoryWeights\Pages\ListProductCategoryWeights;
use App\Models\ProductCategoryWeight;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ProductCategoryWeightResource extends Resource
{
    protected static ?string $model = ProductCategoryWeight::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|UnitEnum|null $navigationGroup = 'Checkout';

    protected static ?string $navigationLabel = 'Pesos estimados';

    protected static ?string $modelLabel = 'peso estimado';

    protected static ?string $pluralModelLabel = 'pesos estimados';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('category')
                ->label('Categoria da peça')
                ->helperText('Nome exato de piece_name recebido da TelePeças. Só é usado quando parts_weight é zero.')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('weight_kg')
                ->label('Peso estimado')
                ->numeric()
                ->minValue(0.001)
                ->suffix('kg')
                ->required(),
            Toggle::make('active')->label('Ativo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('category')->label('Categoria')->searchable()->sortable(),
            TextColumn::make('weight_kg')->label('Peso estimado')->suffix(' kg')->sortable(),
            IconColumn::make('active')->label('Ativo')->boolean(),
        ])->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductCategoryWeights::route('/'),
            'create' => CreateProductCategoryWeight::route('/create'),
            'edit' => EditProductCategoryWeight::route('/{record}/edit'),
        ];
    }
}
