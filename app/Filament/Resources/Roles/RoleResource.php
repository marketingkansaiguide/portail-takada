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
                            'folder.viewAny' => __('Voir la liste des dossiers'),
                            'folder.view' => __('Voir le détail d\'un dossier'),
                            'folder.create' => __('Créer un dossier'),
                            'folder.update' => __('Modifier un dossier'),
                            'folder.delete' => __('Supprimer un dossier'),
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
                            'product.viewAny' => __('Voir le catalogue de produits'),
                            'product.view' => __('Voir la fiche d\'un produit'),
                            'product.create' => __('Créer/Ajouter un produit'),
                            'product.update' => __('Modifier un produit'),
                            'product.delete' => __('Supprimer un produit'),
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
                            'agency.viewAny' => __('Voir la liste des agences'),
                            'agency.view' => __('Voir la fiche d\'une agence'),
                            'agency.create' => __('Inscrire une agence'),
                            'agency.update' => __('Modifier une agence'),
                            'agency.delete' => __('Supprimer une agence'),
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
                            'user.viewAny' => __('Gérer les comptes utilisateurs (Backoffice)'),
                            'user.view' => __('Voir un compte utilisateur'),
                            'user.create' => __('Créer un compte utilisateur'),
                            'user.update' => __('Modifier un compte utilisateur'),
                            'user.delete' => __('Supprimer un compte utilisateur'),
                            'setting.viewAny' => __('Accéder aux paramètres globaux'),
                            'setting.view' => __('Voir un paramètre global'),
                            'setting.create' => __('Créer un paramètre global'),
                            'setting.update' => __('Modifier un paramètre global'),
                            'setting.delete' => __('Supprimer un paramètre global'),
                            'client_group.viewAny' => __('Gérer les groupes clients & prix'),
                            'client_group.view' => __('Voir un groupe client'),
                            'client_group.create' => __('Créer un groupe client'),
                            'client_group.update' => __('Modifier un groupe client'),
                            'client_group.delete' => __('Supprimer un groupe client'),
                        ])
                        ->columns(2)
                        ->gridDirection('vertical')
                        ->bulkToggleable(),
                ]),

            Section::make(__('Référentiels'))
                ->compact()
                ->schema([
                    CheckboxList::make('permissions')
                        ->hiddenLabel()
                        ->options([
                            'category.viewAny' => __('Voir les catégories'),
                            'category.view' => __('Voir une catégorie'),
                            'category.create' => __('Créer une catégorie'),
                            'category.update' => __('Modifier une catégorie'),
                            'category.delete' => __('Supprimer une catégorie'),
                            'supplier.viewAny' => __('Voir les fournisseurs'),
                            'supplier.view' => __('Voir un fournisseur'),
                            'supplier.create' => __('Créer un fournisseur'),
                            'supplier.update' => __('Modifier un fournisseur'),
                            'supplier.delete' => __('Supprimer un fournisseur'),
                            'hotel.viewAny' => __('Voir les hébergements'),
                            'hotel.view' => __('Voir un hébergement'),
                            'hotel.create' => __('Créer un hébergement'),
                            'hotel.update' => __('Modifier un hébergement'),
                            'hotel.delete' => __('Supprimer un hébergement'),
                            'item_status.viewAny' => __('Voir les statuts de prestation'),
                            'item_status.view' => __('Voir un statut de prestation'),
                            'item_status.create' => __('Créer un statut de prestation'),
                            'item_status.update' => __('Modifier un statut de prestation'),
                            'item_status.delete' => __('Supprimer un statut de prestation'),
                        ])
                        ->columns(2)
                        ->gridDirection('vertical')
                        ->bulkToggleable(),
                ]),

            Section::make(__('Gares & Stations'))
                ->compact()
                ->schema([
                    CheckboxList::make('permissions')
                        ->hiddenLabel()
                        ->options([
                            'train_station.viewAny' => __('Voir les gares ferroviaires'),
                            'train_station.view' => __('Voir une gare ferroviaire'),
                            'train_station.create' => __('Créer une gare ferroviaire'),
                            'train_station.update' => __('Modifier une gare ferroviaire'),
                            'train_station.delete' => __('Supprimer une gare ferroviaire'),
                            'bus_station.viewAny' => __('Voir les stations de bus'),
                            'bus_station.view' => __('Voir une station de bus'),
                            'bus_station.create' => __('Créer une station de bus'),
                            'bus_station.update' => __('Modifier une station de bus'),
                            'bus_station.delete' => __('Supprimer une station de bus'),
                        ])
                        ->columns(2)
                        ->gridDirection('vertical')
                        ->bulkToggleable(),
                ]),

            Section::make(__('Opérations & Rapports'))
                ->compact()
                ->schema([
                    CheckboxList::make('permissions')
                        ->hiddenLabel()
                        ->options([
                            'dashboard.view' => __('Accéder au tableau de bord'),
                            'operations.checkins' => __('Gérer les check-ins'),
                            'operations.train_tickets' => __('Gérer les billets de train'),
                            'operations.iwa_orders' => __('Gérer les commandes IWA'),
                            'operations.ic_cards' => __('Gérer les cartes IC'),
                            'operations.seven_tickets' => __('Gérer les commandes Seven Tickets'),
                            'operations.statistics' => __('Consulter les statistiques financières'),
                        ])
                        ->columns(2)
                        ->gridDirection('vertical')
                        ->bulkToggleable(),
                ]),

            Section::make(__('SEO'))
                ->compact()
                ->schema([
                    CheckboxList::make('permissions')
                        ->hiddenLabel()
                        ->options([
                            'seo.dashboard' => __('Consulter le tableau de bord SEO'),
                            'seo_site.viewAny' => __('Voir les sites suivis'),
                            'seo_site.view' => __('Voir un site suivi'),
                            'seo_site.create' => __('Ajouter un site suivi'),
                            'seo_site.update' => __('Modifier un site suivi et sa clé'),
                            'seo_site.delete' => __('Supprimer un site suivi'),
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
