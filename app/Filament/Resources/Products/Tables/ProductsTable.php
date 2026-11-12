<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('primary_image_url')
                    ->label(__('Photo'))
                    ->getStateUsing(fn (\App\Models\Product $record): ?string => $record->thumbnailUrl(120))
                    ->circular()
                    ->imageSize(48),

                TextColumn::make('name')
                    ->label(__('Prestation'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('productSuppliers.supplier.name')
                    ->label(__('Fournisseurs'))
                    ->listWithLineBreaks(),

                TextColumn::make('category.name')
                    ->label(__('Catégorie'))
                    ->badge()
                    ->sortable(),

                ToggleColumn::make('is_public')
                    ->label(__('Actif'))
                    ->sortable(),

                IconColumn::make('is_lottery')
                    ->label(__('Disponibilité limitée'))
                    ->boolean(),

                IconColumn::make('is_on_demand')
                    ->label(__('Devis'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_public')
                    ->label(__('Statut'))
                    ->trueLabel(__('Actifs'))
                    ->falseLabel(__('Inactifs'))
                    ->placeholder(__('Tous'))
                    ->default(true),

                SelectFilter::make('category')
                    ->label(__('Filtrer par catégorie'))
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()
                    ->modalHeading(__('Supprimer ce produit ?'))
                    ->modalDescription(__('Le produit, ses options, ses prix et ses périodes seront supprimés définitivement.'))
                    ->before(function (\Filament\Actions\DeleteAction $action, \App\Models\Product $record) {
                        $n = $record->usageCount();
                        if ($n > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title(__('Suppression impossible'))
                                ->body(__('Ce produit est utilisé dans :n prestation(s) de dossiers. Désactivez-le (colonne « Actif ») plutôt que de le supprimer.', ['n' => $n]))
                                ->danger()->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make()
                        ->modalDescription(__('Les produits sélectionnés, leurs options, prix et périodes seront supprimés définitivement.'))
                        ->before(function (\Filament\Actions\DeleteBulkAction $action, Collection $records) {
                            $used = $records->filter(fn ($p) => $p->usageCount() > 0);
                            if ($used->isNotEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->title(__('Suppression impossible'))
                                    ->body(__('Produits utilisés dans des dossiers : :list. Retirez-les de la sélection ou désactivez-les.', ['list' => $used->pluck('name')->take(5)->implode(', ').($used->count() > 5 ? '…' : '')]))
                                    ->danger()->send();
                                $action->cancel();
                            }
                        }),
                    BulkAction::make('activate')
                        ->label(__('Activer'))
                        ->icon('heroicon-o-eye')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_public' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')
                        ->label(__('Désactiver'))
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_public' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
