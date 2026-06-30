<?php

namespace App\Filament\Resources\Folders\FolderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Historique des modifications';
    
    protected static ?string $icon = 'heroicon-o-clock';

    public function form(Form $form): Form
    {
        return $form->schema([]); // Ce tableau est en lecture seule
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
                        'created' => __('Création'),
                        'updated' => __('Mise à jour'),
                        'deleted' => __('Suppression'),
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('properties')
                    ->label(__('Détails des changements'))
                    ->formatStateUsing(function ($state) {
                        $stateArray = is_array($state) ? $state : (is_object($state) && method_exists($state, 'toArray') ? $state->toArray() : json_decode((string) $state, true));
                        
                        if (empty($stateArray) || !isset($stateArray['attributes'])) {
                            return new HtmlString("<span style='color: #94a3b8; font-style: italic;'>Initialisation du dossier</span>");
                        }
                        
                        $changes = [];
                        foreach ($stateArray['attributes'] as $key => $newValue) {
                            if (in_array($key, ['updated_at', 'created_at', 'id'])) continue;

                            $oldValue = $stateArray['old'][$key] ?? 'Vide';
                            $oldStr = is_array($oldValue) ? 'Tableau de données' : (string) $oldValue;
                            $newStr = is_array($newValue) ? 'Tableau de données' : (string) $newValue;

                            $oldStr = Str::limit($oldStr, 25);
                            $newStr = Str::limit($newStr, 25);

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
            ->defaultSort('created_at', 'desc'); // Affiche les actions les plus récentes en haut
    }
}