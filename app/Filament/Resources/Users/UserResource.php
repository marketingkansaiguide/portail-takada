<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use BackedEnum;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    public static function getModelLabel(): string
    {
        return __('Utilisateur');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Utilisateurs & Accès');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'role', 'agency.name'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return array_filter([
            __('Email') => $record->email,
            __('Rôle') => $record->roles->pluck('display_name')->implode(', '),
            __('Agence') => $record->agency?->name,
        ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $viewer = auth('web')->user();

        // Seuls les (super) administrateurs voient/gèrent les comptes d'administration.
        if ($viewer && ! $viewer->hasRole(User::ROLE_SUPER_ADMIN) && ! $viewer->hasRole(User::ROLE_ADMIN)
            && ! $viewer->hasRole(User::ROLE_AGENCY)) {
            $query->whereNotIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN]);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nom'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),

                TablesColumnsTextColumn::make('roles.display_name')
                    ->label(__('Profils'))
                    ->badge()
                    ->color('info'),


                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Date de création'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TablesFiltersSelectFilter::make('roles')
                    ->label(__('Filtrer par profil'))
                    ->relationship('roles', 'display_name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                // Utilisation du nouveau namespace unifié Filament v5
                EditAction::make(),
            ])
            ->bulkActions([
                // Utilisation des nouveaux namespaces unifiés Filament v5
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
