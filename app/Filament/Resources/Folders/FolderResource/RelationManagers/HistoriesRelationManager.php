<?php

namespace App\Filament\Resources\Folders\FolderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class HistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date & Heure')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->default('Système')
                    ->sortable(),

                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color('info'),

                // Lecture depuis la structure JSON (clé 'summary' de 'changes_payload')
                Tables\Columns\TextColumn::make('changes_payload.summary')
                    ->label('Détails des changements')
                    ->wrap() // Permet le retour à la ligne automatique dans la cellule
                    ->html() // Autorise l'affichage propre des balises HTML
                    ->formatStateUsing(fn (?string $state): string => nl2br(e($state ?? 'Aucun détail'))),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Désactivation des actions de création manuelle pour éviter les faux historiques
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('created_at', 'desc');
    }
}