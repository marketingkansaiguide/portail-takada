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

class AgencyUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

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
        // 💡 SÉCURITÉ : L'agence ne voit que les comptes liés à son ID
        return parent::getEloquentQuery()->where('agency_id', Filament::auth()->user()->agency_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Group::make()->schema([
                Section::make('Informations du compte')
                    ->description('Créez un accès pour un vendeur de votre agence. Il pourra se connecter au portail avec son email et gérer ses propres dossiers.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom complet')
                            ->required()
                            ->maxLength(255),
                        
                        TextInput::make('email')
                            ->label('Adresse E-mail')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->maxLength(255),
                        
                        TextInput::make('password')
                            ->label('Mot de passe')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255),

                        // 💡 VERROUILLAGE DES RÔLES : Forcés en arrière-plan
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
                    ->label('Nom complet')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                TextColumn::make('email')
                    ->label('Adresse E-mail')
                    ->searchable(),
                    
                TextColumn::make('created_at')
                    ->label('Date de création')
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
                        // 💡 SÉCURITÉ : Empêcher l'utilisateur de se supprimer lui-même
                        if (Filament::auth()->id() === $record->id) {
                            Notification::make()
                                ->title('Action impossible')
                                ->body('Vous ne pouvez pas supprimer le compte avec lequel vous êtes actuellement connecté.')
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