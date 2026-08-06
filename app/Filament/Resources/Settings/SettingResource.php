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
                    Section::make(__('Notifications & Alertes'))
                        ->description(__('Gérez les e-mails de l\'administration et les délais de relance.'))
                        ->schema([
                            TagsInput::make('admin_email_notifications')
                                ->label(__('E-mails de réception des alertes Admin'))
                                ->placeholder(__('Tapez un e-mail puis appuyez sur Entrée'))
                                ->helperText(__('Vous pouvez ajouter plusieurs adresses (validez chaque adresse avec la touche Entrée).'))
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