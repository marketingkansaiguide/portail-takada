<?php

namespace App\Filament\Resources\ClientGroups;

use App\Filament\Resources\ClientGroups\Pages;
use App\Filament\Resources\ClientGroups\Schemas\ClientGroupForm;
use App\Filament\Resources\ClientGroups\Tables\ClientGroupsTable;
use App\Models\ClientGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ClientGroupResource extends Resource
{
    protected static ?string $model = ClientGroup::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    public static function getNavigationLabel(): string
    {
        return __('Groupes Clients');
    }

    public static function getModelLabel(): string
    {
        return __('Groupe Client');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Groupes Clients');
    }

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
            'index' => Pages\ListClientGroups::route('/'),
            'create' => Pages\CreateClientGroup::route('/create'),
            'edit' => Pages\EditClientGroup::route('/{record}/edit'),
        ];
    }
}