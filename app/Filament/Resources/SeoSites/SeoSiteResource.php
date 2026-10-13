<?php

namespace App\Filament\Resources\SeoSites;

use App\Filament\Resources\SeoSites\Pages;
use App\Filament\Resources\SeoSites\Schemas\SeoSiteForm;
use App\Filament\Resources\SeoSites\Tables\SeoSitesTable;
use App\Models\SeoSite;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SeoSiteResource extends Resource
{
    protected static ?string $model = SeoSite::class;
    protected static ?string $recordTitleAttribute = 'name';
    protected static bool $isGloballySearchable = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static UnitEnum|string|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('Sites suivis');
    }

    public static function getModelLabel(): string
    {
        return __('Site suivi');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Sites suivis');
    }

    public static function form(Schema $schema): Schema
    {
        return SeoSiteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeoSitesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSeoSites::route('/'),
            'create' => Pages\CreateSeoSite::route('/create'),
            'edit' => Pages\EditSeoSite::route('/{record}/edit'),
        ];
    }
}
