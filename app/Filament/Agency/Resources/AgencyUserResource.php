<?php

namespace App\Filament\Agency\Resources;

use App\Filament\Agency\Resources\AgencyUserResource\Pages;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

// Actions unifiées Filament
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;
use BackedEnum; // 💡 IMPORT NÉCESSAIRE POUR LE TYPAGE

class AgencyUserResource extends Resource
{
    protected static ?string $model = User::class;

    // 💡 CORRECTION DU TYPAGE STRICT ICI
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function getNavigationLabel(): string
    {
        return __('Mon Équipe');
    }

    public static function getModelLabel(): string
    {
        return __('Vendeur');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Mon Équipe');
    }

    public static function getEloquentQuery(): Builder
    {
        // L'agence ne gère que ses propres comptes externes, jamais un compte
        // interne auquel une agence aurait été associée par erreur.
        return parent::getEloquentQuery()
            ->where('agency_id', Filament::auth()->user()->agency_id)
            ->where('role', User::ROLE_AGENCY);
    }

    public static function canViewAny(): bool
    {
        return Filament::auth()->user()?->role === User::ROLE_AGENCY;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::belongsToCurrentAgency($record);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::belongsToCurrentAgency($record);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::belongsToCurrentAgency($record) && Filament::auth()->id() !== $record->id;
    }

    private static function belongsToCurrentAgency(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = Filament::auth()->user();

        return $user?->role === User::ROLE_AGENCY
            && $record->role === User::ROLE_AGENCY
            && (int) $record->agency_id === (int) $user->agency_id;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Group::make()->schema([
                    Section::make(__('Informations du compte'))
                    ->description(__('Créez un accès pour un vendeur de votre agence. Il pourra se connecter au portail avec son email et gérer ses propres dossiers.'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Nom complet'))
                            ->required()
                            ->maxLength(255),
                        
                        TextInput::make('email')
                            ->label(__('Adresse E-mail'))
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->maxLength(255),
                        
                        TextInput::make('password')
                            ->label(__('Mot de passe'))
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255),

                        // VERROUILLAGE DES RÔLES : Forcés en arrière-plan
                        Hidden::make('role')->default(User::ROLE_AGENCY),
                        Hidden::make('agency_id')->default(fn () => Filament::auth()->user()->agency_id),
                    ])
            ])->columnSpanFull()
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Nom complet'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                TextColumn::make('email')
                    ->label(__('Adresse E-mail'))
                    ->searchable(),
                    
                TextColumn::make('created_at')
                    ->label(__('Date de création'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, User $record) {
                        // SÉCURITÉ : Empêcher l'utilisateur de se supprimer lui-même
                        if (Filament::auth()->id() === $record->id) {
                            Notification::make()
                    ->title(__('Action impossible'))
                    ->body(__('Vous ne pouvez pas supprimer le compte avec lequel vous êtes actuellement connecté.'))
                                ->danger()
                                ->send();
                            
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgencyUsers::route('/'),
            'create' => Pages\CreateAgencyUser::route('/create'),
            'edit' => Pages\EditAgencyUser::route('/{record}/edit'),
        ];
    }
}
