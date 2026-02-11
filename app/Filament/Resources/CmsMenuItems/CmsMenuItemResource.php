<?php

namespace App\Filament\Resources\CmsMenuItems;

use App\Filament\Resources\CmsMenuItems\Pages\CreateCmsMenuItem;
use App\Filament\Resources\CmsMenuItems\Pages\EditCmsMenuItem;
use App\Filament\Resources\CmsMenuItems\Pages\ListCmsMenuItems;
use App\Filament\Resources\CmsMenuItems\Schemas\CmsMenuItemForm;
use App\Filament\Resources\CmsMenuItems\Tables\CmsMenuItemsTable;
use App\Models\CmsMenuItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class CmsMenuItemResource extends Resource
{
    protected static ?string $model = CmsMenuItem::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3-bottom-left';

    protected static string|UnitEnum|null $navigationGroup = 'CMS';

    protected static ?string $navigationLabel = 'Menu Header';

    protected static ?string $pluralModelLabel = 'itens do menu';

    protected static ?string $modelLabel = 'item do menu';

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return CmsMenuItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CmsMenuItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCmsMenuItems::route('/'),
            'create' => CreateCmsMenuItem::route('/create'),
            'edit' => EditCmsMenuItem::route('/{record}/edit'),
        ];
    }
}

