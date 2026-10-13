<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages;
use App\Models\Role;
use App\Support\PermissionCatalog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    public static function getModelLabel(): string
    {
        return __('Profil');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Permissions des Rôles');
    }

    public static function form(Schema $schema): Schema
    {
        $locked = fn (?Role $record): bool => $record && in_array($record->id, Role::LOCKED, true);

        return $schema->components([
            Section::make(__('Configuration du profil'))
                ->columns(2)
                ->schema([
                    TextInput::make('display_name')
                        ->label(__('Nom du profil'))
                        ->disabled($locked)
                        ->required(),
                    TextInput::make('id')
                        ->label(__('Identifiant technique'))
                        ->helperText(__('Minuscules, chiffres et _ ; non modifiable après création.'))
                        ->regex('/^[a-z][a-z0-9_]{2,40}$/')
                        ->unique(ignoreRecord: true)
                        ->visible(fn (?Role $record) => ! $record)
                        ->required(),
                    Toggle::make('is_staff')
                        ->label(__('Accès au back-office Takada'))
                        ->helperText(__('Autorise la connexion au panel d\'administration.'))
                        ->disabled($locked),
                ]),

            ...collect(PermissionCatalog::groups())->map(
                fn (array $options, string $title) => Section::make($title)
                    ->compact()
                    ->collapsible()
                    ->schema([
                        CheckboxList::make('permissions') // même nom : le JSON rassemble tout
                            ->hiddenLabel()
                            ->options($options)
                            ->columns(2)
                            ->gridDirection('vertical')
                            ->bulkToggleable(),
                    ])
            )->values()->all(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label(__('Profil métier'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_staff')
                    ->label(__('Back-office'))
                    ->boolean(),

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
                Action::make('duplicate')
                    ->label(__('Dupliquer'))
                    ->icon('heroicon-o-document-duplicate')
                    ->visible(fn (Role $record) => $record->id !== Role::AGENCY)
                    ->action(function (Role $record) {
                        $id = $record->id . '_copie';
                        $n = 2;
                        while (Role::whereKey($id)->exists()) {
                            $id = $record->id . '_copie' . $n++;
                        }
                        Role::create([
                            'id' => $id,
                            'display_name' => $record->display_name . ' (copie)',
                            'permissions' => $record->permissions,
                            'is_staff' => $record->is_staff,
                        ]);
                    }),
                DeleteAction::make()
                    ->visible(fn (Role $record) => ! in_array($record->id, [...Role::LOCKED, 'admin', 'agent'], true))
                    ->before(function (Role $record, DeleteAction $action) {
                        if (DB::table('role_user')->where('role_id', $record->id)->exists()
                            || DB::table('users')->where('role', $record->id)->exists()) {
                            Notification::make()->danger()->title(__('Profil encore attribué à des utilisateurs'))->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRoles::route('/{record}/edit'),
        ];
    }
}
