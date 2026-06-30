<?php

namespace App\Filament\Resources\Folders\FolderResource\RelationManagers;

use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activitiesAsSubject';
    protected static ?string $title = 'Historique des modifications';
    protected static string|BackedEnum|null $icon = 'heroicon-o-clock';

    public function form(Schema $schema): Schema { return $schema->components([]); }

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
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('attribute_changes')
                    ->label(__('Détails des changements'))
                    ->formatStateUsing(function ($state, $record) {
                        $props = $record->attribute_changes;
                        if ($props instanceof \Illuminate\Support\Collection) $props = $props->toArray();
                        elseif (is_string($props)) $props = json_decode($props, true);
                        
                        if (empty($props) || !isset($props['attributes'])) {
                            return new HtmlString("<span style='color: #94a3b8; font-style: italic;'>" . __('Initialisation des données') . "</span>");
                        }
                        
                        // 🎯 RELAIS VERS TES TRADUCTIONS JSON
                        $dbToTranslations = [
                            'folder_name' => 'Nom du dossier', 'lead_traveler_name' => 'Pax Leader', 'agency_id' => 'Agence émettrice',
                            'status' => 'Statut du dossier', 'start_date' => 'Date d\'arrivée au Japon', 'end_date' => 'Date de départ',
                            'folder_fee' => 'Frais de dossier', 'total_price' => 'Montant total',
                            'first_hotel_name' => 'Nom du premier hôtel', 'first_hotel_check_in' => 'Date de check-in du 1er hôtel',
                            'ticket_dispatch_method' => 'Envoi de la billetterie', 'product_id' => 'Produit / Activité',
                            'product_option_id' => 'Option choisie', 'item_status_id' => 'Statut',
                            'service_date' => 'Date de la prestation', 'quantity' => 'Quantité / Voyageurs', 'unit_price' => 'Prix unitaire',
                            'last_name' => 'Nom de famille', 'first_name' => 'Prénom', 'birth_date' => 'Date de naissance',
                            'nationality' => 'Nationalité', 'dietary_restrictions' => 'Allergies / restrictions alimentaires', 'mobility_concerns' => 'Handicap / mobilité réduite'
                        ];

                        $changes = [];
                        foreach ($props['attributes'] as $key => $newValue) {
                            if (in_array($key, ['id', 'folder_id'])) continue; // On masque les ID techniques inintéressants

                            $oldValue = $props['old'][$key] ?? null;
                            
                            $oldStr = $oldValue === null ? 'Vide' : (is_bool($oldValue) ? ($oldValue ? 'Oui' : 'Non') : (is_array($oldValue) ? 'Tableau' : (string) $oldValue));
                            $newStr = $newValue === null ? 'Vide' : (is_bool($newValue) ? ($newValue ? 'Oui' : 'Non') : (is_array($newValue) ? 'Tableau' : (string) $newValue));

                            $oldStr = Str::limit($oldStr, 40);
                            $newStr = Str::limit($newStr, 40);

                            // 🎯 LE MOTEUR QUI DÉCODE "folderItems.0.quantity" EN "Prestation #1 - Quantité"
                            if (preg_match('/^(folderItems|folder_items)\.(\d+)\.(.+)$/', $key, $matches)) {
                                $index = (int)$matches[2] + 1;
                                $field = isset($dbToTranslations[$matches[3]]) ? __($dbToTranslations[$matches[3]]) : $matches[3];
                                $translatedKey = "Prestation #{$index} - {$field}";
                            } elseif (preg_match('/^(folderPassengers|folder_passengers)\.(\d+)\.(.+)$/', $key, $matches)) {
                                $index = (int)$matches[2] + 1;
                                $field = isset($dbToTranslations[$matches[3]]) ? __($dbToTranslations[$matches[3]]) : $matches[3];
                                $translatedKey = "Voyageur #{$index} - {$field}";
                            } elseif (preg_match('/^contact_phones\.(\d+)\.(.+)$/', $key, $matches)) {
                                $index = (int)$matches[2] + 1;
                                $translatedKey = "Téléphone #{$index}";
                            } else {
                                $translatedKey = isset($dbToTranslations[$key]) ? __($dbToTranslations[$key]) : $key;
                            }

                            $changes[] = "<strong>{$translatedKey}</strong> : <span style='color: #ef4444; text-decoration: line-through;'>{$oldStr}</span> ➔ <span style='color: #22c55e;'>{$newStr}</span>";
                        }
                        return new HtmlString(empty($changes) ? __('Aucun détail capturé') : implode('<br>', $changes));
                    })
                    ->wrap()
                    ->size('xs'),
            ])
            ->defaultSort('created_at', 'desc'); 
    }
}