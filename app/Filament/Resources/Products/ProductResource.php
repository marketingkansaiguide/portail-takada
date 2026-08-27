<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages;
use App\Models\Product;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    public static function getNavigationLabel(): string
    {
        return __('Catalogue Produits');
    }

    public static function getModelLabel(): string
    {
        return __('Produit');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Catalogue Produits');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'description', 'category.name', 'supplier.name'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return array_filter([
            __('Catégorie') => $record->category?->name,
            __('Fournisseur') => $record->supplier?->name,
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where(function ($query) {
            $query->whereNull('product_type')
                  ->orWhere('product_type', 'standard');
        });
    }

    public static function form(Schema $schema): Schema
    {
        return \App\Filament\Resources\Products\Schemas\ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\Products\Tables\ProductsTable::configure($table);
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
