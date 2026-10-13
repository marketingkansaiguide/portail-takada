<?php

namespace App\Filament\Resources\SeoSites\Tables;

use App\Models\SeoSite;
use App\Services\SeoSuiviApi;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeoSitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Nom'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('url')
                    ->label(__('Adresse'))
                    ->url(fn (SeoSite $record): string => $record->url, shouldOpenInNewTab: true)
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label(__('Actif'))
                    ->boolean(),

                TextColumn::make('last_success_at')
                    ->label(__('Dernière lecture réussie'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('last_error')
                    ->label(__('Dernière erreur'))
                    ->color('danger')
                    ->limit(60)
                    ->tooltip(fn (SeoSite $record): ?string => $record->last_error)
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('test')
                    ->label(__('Tester la connexion'))
                    ->icon('heroicon-o-signal')
                    ->color('gray')
                    ->action(function (SeoSite $record): void {
                        try {
                            $info = app(SeoSuiviApi::class)->testConnection($record);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title(__('Connexion impossible'))
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('Connexion réussie'))
                            ->body(trim(($info['name'] ?? $record->name).' · '.__('extension').' '.($info['plugin_version'] ?? '?')))
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ]);
    }
}
