<?php

namespace App\Filament\Resources;

use App\Models\ItemStatus;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ItemStatusResource extends Resource
{
    protected static ?string $model = ItemStatus::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    
    protected static ?string $navigationGroup = 'Configuration'; // Aligné dans un groupe d'admin

    public static function getNavigationLabel(): string
    {
        return __('Statuts des Prestations');
    }

    public static function getModelLabel(): string
    {
        return __('Statut de prestation');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Statuts des Prestations');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make(__('Créer un statut opérationnel'))
                    ->description(__('Ce statut pourra être appliqué individuellement sur chaque ligne de prestation dans les dossiers.'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Nom du statut'))
                            ->placeholder('Ex: En attente fournisseur, Confirmé, Voucher envoyé...')
                            ->required()
                            ->unique(ignoreRecord: true),

                        Select::make('color')
                            ->label(__('Couleur d\'affichage (Badge)'))
                            ->options([
                                'gray' => __('Gris (Neutre / Brouillon)'),
                                'warning' => __('Orange (En attente / Warning)'),
                                'info' => __('Bleu (En cours / Info)'),
                                'success' => __('Vert (Validé / Confirmé)'),
                                'danger' => __('Rouge (Annulé / Problème)'),
                            ])
                            ->default('gray')
                            ->required(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nom du statut'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('color')
                    ->label(__('Badge Visuel'))
                    ->badge()
                    ->color(fn (string $state): string => $state)
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'gray' => __('Gris'),
                        'warning' => __('Orange'),
                        'info' => __('Bleu'),
                        'success' => __('Vert'),
                        'danger' => __('Rouge'),
                    }),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ItemStatusResource\Pages\ListItemStatuses::route('/'),
            'create' => ItemStatusResource\Pages\CreateItemStatus::route('/create'),
            'edit' => ItemStatusResource\Pages\EditItemStatus::route('/{record}/edit'),
        ];
    }
}