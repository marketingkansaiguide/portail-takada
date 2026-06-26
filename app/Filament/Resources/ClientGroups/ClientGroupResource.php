<?php

namespace App\Filament\Resources\ClientGroups;

use App\Filament\Resources\ClientGroups\Pages\CreateClientGroup;
use App\Filament\Resources\ClientGroups\Pages\EditClientGroup;
use App\Filament\Resources\ClientGroups\Pages\ListClientGroups;
use App\Filament\Resources\ClientGroups\Schemas\ClientGroupForm;
use App\Filament\Resources\ClientGroups\Tables\ClientGroupsTable;
use App\Models\ClientGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClientGroupResource extends Resource
{
    protected static ?string $model = ClientGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ClientGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClientGroups::route('/'),
            'create' => CreateClientGroup::route('/create'),
            'edit' => EditClientGroup::route('/{record}/edit'),
        ];
    }
}
