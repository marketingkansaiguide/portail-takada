<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages;
use App\Models\Role;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Filament\Actions\EditAction;
use BackedEnum;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    public static function getModelLabel(): string
    {
        return __('Matrice de Permissions');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Permissions des Rôles');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Configuration du Rôle'))
                ->schema([
                    TextInput::make('display_name')
                        ->label(__('Nom du profil'))
                        ->disabled() // Verrouillé pour éviter de dénaturer vos profils Takada
                        ->required(),
                ]),

            // Avec Filament v5, l'approche la plus stable pour les groupements
            // consiste à créer un CheckboxList distinct par section métier.
            Section::make(__('Dossiers de Voyage'))
                ->compact()
                ->schema([
                    CheckboxList::make('permissions') // Le même nom de champ permet au JSON de tout rassembler !
                        ->hiddenLabel() // On cache le label redondant puisque la section donne déjà le titre
                        ->options([
                            'folder.viewAny' => 'Voir la liste des dossiers',
                            'folder.view' => 'Voir le détail d\'un dossier',
                            'folder.create' => 'Créer un dossier',
                            'folder.update' => 'Modifier un dossier',
                            'folder.delete' => 'Supprimer un dossier',
                        ])
                        ->columns(2)
                        ->gridDirection('vertical')
                        ->bulkToggleable(),
                ]),

            Section::make(__('Catalogue Produits'))
                ->compact()
                ->schema([
                    CheckboxList::make('permissions')
                        ->hiddenLabel()
                        ->options([
                            'product.viewAny' => 'Voir le catalogue de produits',
                            'product.view' => 'Voir la fiche d\'un produit',
                            'product.create' => 'Créer/Ajouter un produit',
                            'product.update' => 'Modifier un produit',
                            'product.delete' => 'Supprimer un produit',
                        ])
                        ->columns(2)
                        ->gridDirection('vertical')
                        ->bulkToggleable(),
                ]),

            Section::make(__('Gestion des Agences'))
                ->compact()
                ->schema([
                    CheckboxList::make('permissions')
                        ->hiddenLabel()
                        ->options([
                            'agency.viewAny' => 'Voir la liste des agences',
                            'agency.view' => 'Voir la fiche d\'une agence',
                            'agency.create' => 'Inscrire une agence',
                            'agency.update' => 'Modifier une agence',
                            'agency.delete' => 'Supprimer une agence',
                        ])
                        ->columns(2)
                        ->gridDirection('vertical')
                        ->bulkToggleable(),
                ]),

            Section::make(__('Utilisateurs & Système'))
                ->compact()
                ->schema([
                    CheckboxList::make('permissions')
                        ->hiddenLabel()
                        ->options([
                            'user.viewAny' => 'Gérer les comptes utilisateurs (Backoffice)',
                            'setting.viewAny' => 'Accéder aux paramètres globaux',
                            'client_group.viewAny' => 'Gérer les groupes clients & prix',
                        ])
                        ->columns(2)
                        ->gridDirection('vertical')
                        ->bulkToggleable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label(__('Profil métier'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('permissions')
                    ->label(__('Nombre de privilèges actifs'))
                    ->badge()
                    ->color('success')
                    ->state(fn (Role $record): int => count($record->permissions ?? [])),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Dernière mise à jour'))
                    ->dateTime('d/m/Y H:i'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'edit' => Pages\EditRoles::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}