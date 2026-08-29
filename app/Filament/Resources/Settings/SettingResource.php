<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;
    
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    public static function getNavigationGroup(): ?string
    {
        return __('Configuration');
    }
    
    public static function getNavigationLabel(): string
    {
        return __('Paramètres Généraux');
    }

    /** Le paramétrage est un singleton : le menu ouvre directement son formulaire. */
    public static function getNavigationUrl(): string
    {
        $settings = Setting::query()->first();

        return $settings
            ? static::getUrl('edit', ['record' => $settings])
            : static::getUrl('create');
    }

    public static function getModelLabel(): string
    {
        return __('Paramètre');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Paramètres Généraux');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()->schema([
                    Section::make(__('Identité visuelle'))
                        ->description(__('Ces fichiers sont utilisés dans l’administration, le portail agence et les documents générés.'))
                        ->schema([
                            FileUpload::make('site_logo_path')
                                ->label(__('Logo du site'))
                                ->disk('public')
                                ->directory('site-branding')
                                ->visibility('public')
                                ->image()
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'])
                                ->storeFiles()
                                ->maxSize(2048)
                                ->helperText(__('Format recommandé : SVG, PNG ou WebP horizontal. Ce logo remplace le logo par défaut sur l’ensemble du portail.'))
                                ->columnSpanFull(),

                            FileUpload::make('site_favicon_path')
                                ->label(__('Favicon'))
                                ->disk('public')
                                ->directory('site-branding')
                                ->visibility('public')
                                ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml', 'image/webp', 'image/jpeg'])
                                ->storeFiles()
                                ->maxSize(512)
                                ->helperText(__('Format recommandé : PNG carré, ICO ou SVG.')),
                        ]),

                    Section::make(__('Disponibilité du site'))
                        ->description(__('Contrôlez temporairement l’accès public et l’indexation du portail.'))
                        ->schema([
                            Toggle::make('maintenance_mode')
                                ->label(__('Activer le mode maintenance'))
                                ->helperText(__('Le portail agence et les pages publiques seront remplacés par une page de maintenance. L’administration reste accessible.'))
                                ->default(false),

                            Toggle::make('prevent_search_indexing')
                                ->label(__('Empêcher l’indexation par les moteurs de recherche'))
                                ->helperText(__('Ajoute noindex/nofollow aux pages publiques et bloque l’exploration via robots.txt.'))
                                ->default(false),
                        ]),

                    Section::make(__('Paramètres de la page d’accueil'))
                        ->description(__('Personnalisez la présentation du catalogue visible par les agences et les visiteurs.'))
                        ->schema([
                            Toggle::make('catalogue_categories_separate')
                                ->label(__('Séparer les produits par catégorie'))
                                ->helperText(__('Affiche une section distincte pour chaque catégorie principale.'))
                                ->default(true)
                                ->live(),

                            Toggle::make('catalogue_use_carousel')
                                ->label(__('Afficher les produits en carrousel'))
                                ->helperText(__('Présente les produits sur une seule ligne défilante dans chaque catégorie.'))
                                ->default(true),

                            TextInput::make('catalogue_products_per_category')
                                ->label(__('Nombre de produits affichés par catégorie'))
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(24)
                                ->default(4)
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => (bool) $get('catalogue_categories_separate'))
                                ->helperText(__('Les autres prestations restent accessibles via le bouton « Voir plus ».')),
                        ]),

                    Section::make(__('Notifications & Alertes'))
                        ->description(__('Distinguez les alertes opérationnelles de la supervision technique et définissez les délais de relance.'))
                        ->schema([
                            TagsInput::make('admin_email_notifications')
                                ->label(__('E-mails des alertes opérationnelles'))
                                ->placeholder(__('Tapez un e-mail puis appuyez sur Entrée'))
                                ->helperText(__('Messages des agences, dossiers validés, prestations et relances. Vous pouvez ajouter plusieurs adresses.'))
                                ->separator(',')
                                ->columnSpanFull(),

                            TagsInput::make('technical_alert_emails')
                                ->label(__('E-mails des alertes techniques'))
                                ->placeholder(__('Tapez un e-mail puis appuyez sur Entrée'))
                                ->helperText(__('Sauvegardes, file d’attente, espace disque et erreurs du serveur. Si ce champ est vide, l’adresse technique configurée sur le serveur est utilisée.'))
                                ->separator(',')
                                ->columnSpanFull(),

                            TextInput::make('chat_reminder_hours')
                                ->label(__('Délai avant relance automatique (en heures)'))
                                ->helperText(__('Si l\'agence ne répond pas à un message marqué "Action requise" après ce délai, un e-mail de relance lui sera envoyé.'))
                                ->numeric()
                                ->default(48)
                                ->minValue(1)
                                ->suffix('heures')
                                ->columnSpanFull(),
                        ]),
                        
                    Section::make(__('Configuration des transports'))
                        ->description(__('Définissez les paramètres liés aux réservations de transport.'))
                        ->schema([
                            CheckboxList::make('train_ticket_suppliers')
                                ->label(__('Fournisseurs de billets de train'))
                                ->options(\App\Models\Supplier::pluck('name', 'id'))
                                ->columns(3)
                                ->gridDirection('row')
                                ->columnSpanFull()
                                ->helperText(__('Sélectionnez les fournisseurs (ex: JR, Willer Express) qui gèrent la billetterie de train.')),
                        ]),
                        
                    Section::make(__('Politique d\'annulation globale'))
                        ->schema([
                            RichEditor::make('general_cancellation_policy')
                                ->label(__('Conditions Générales (Texte global)'))
                                ->columnSpanFull(),
                        ]),
                ])->columnSpanFull()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('Configuration'))
                    ->formatStateUsing(fn () => __('Paramètres globaux du site')),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Dernière modification'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
            'create' => Pages\CreateSetting::route('/create'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return Setting::count() === 0;
    }
}
