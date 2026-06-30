<?php

namespace App\Filament\Resources\Folders\FolderResource\RelationManagers;

use BackedEnum;
use Filament\Schemas\Schema; // 🎯 IMPORT FILAMENT V4
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ActivitiesRelationManager extends RelationManager
{
    // 🎯 NOM OFFICIEL DE LA RELATION DANS SPATIE V5
    protected static string $relationship = 'activitiesAsSubject';

    protected static ?string $title = 'Historique des modifications';
    
    protected static string|BackedEnum|null $icon = 'heroicon-o-clock';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]); 
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Date & Heure'))
                    ->dateTime('d/m/Y - H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label(__('Auteur'))
                    ->default(__('Système (Auto)')),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('Action'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'created' => __('Création Dossier'),
                        'updated' => __('Mise à jour Dossier'),
                        'deleted' => __('Suppression Dossier'),
                        'prestation_created' => __('Ajout Prestation'),
                        'prestation_updated' => __('Modif. Prestation'),
                        'prestation_deleted' => __('Retrait Prestation'),
                        'voyageur_created' => __('Ajout Voyageur'),
                        'voyageur_updated' => __('Modif. Voyageur'),
                        'voyageur_deleted' => __('Retrait Voyageur'),
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'created', 'prestation_created', 'voyageur_created' => 'success',
                        'updated', 'prestation_updated', 'voyageur_updated' => 'warning',
                        'deleted', 'prestation_deleted', 'voyageur_deleted' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('properties')
                    ->label(__('Détails des changements'))
                    ->formatStateUsing(function ($state, $record) {
                        $props = $record->properties;
                        if ($props instanceof \Illuminate\Support\Collection) {
                            $props = $props->toArray();
                        } elseif (is_string($props)) {
                            $props = json_decode($props, true);
                        }
                        
                        if (empty($props) || !isset($props['attributes'])) {
                            return new HtmlString("<span style='color: #94a3b8; font-style: italic;'>Initialisation des données</span>");
                        }
                        
                        $changes = [];
                        foreach ($props['attributes'] as $key => $newValue) {
                            if (in_array($key, ['updated_at', 'created_at', 'id', 'folder_id'])) continue;

                            $oldValue = $props['old'][$key] ?? 'Vide';
                            
                            $oldStr = is_array($oldValue) ? 'Tableau' : (string) $oldValue;
                            $newStr = is_array($newValue) ? 'Tableau' : (string) $newValue;

                            $oldStr = Str::limit($oldStr, 35);
                            $newStr = Str::limit($newStr, 35);

                            $changes[] = "<strong>{$key}</strong> : <span style='color: #ef4444; text-decoration: line-through;'>{$oldStr}</span> ➔ <span style='color: #22c55e;'>{$newStr}</span>";
                        }
                        return new HtmlString(empty($changes) ? __('Aucun détail capturé') : implode('<br>', $changes));
                    })
                    ->wrap()
                    ->size('xs'),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc'); 
    }
}