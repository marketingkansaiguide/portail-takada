<?php

namespace App\Filament\Resources\Agencies;

use App\Filament\Resources\Agencies\Pages\CreateAgency;
use App\Filament\Resources\Agencies\Pages\EditAgency;
use App\Filament\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Resources\Agencies\Schemas\AgencyForm;
use App\Filament\Resources\Agencies\Tables\AgenciesTable;
use App\Models\Agency;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AgencyResource extends Resource
{
    protected static ?string $model = Agency::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AgencyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AgenciesTable::configure($table);
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
            'index' => ListAgencies::route('/'),
            'create' => CreateAgency::route('/create'),
            'edit' => EditAgency::route('/{record}/edit'),
        ];
    }
}
