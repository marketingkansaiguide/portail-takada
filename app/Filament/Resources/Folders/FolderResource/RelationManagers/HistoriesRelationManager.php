<?php

namespace App\Filament\Resources\Folders\FolderResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class HistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories'; // 🎯 Pointe vers ta nouvelle table
    protected static ?string $title = 'Historique des modifications';

    public function form(Schema $schema): Schema { return $schema->components([]); }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label(__('Date & Heure'))->dateTime('d/m/Y - H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label(__('Auteur'))->default('Système'),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Création' => 'success',
                        'Mise à jour' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('changes_payload')
                    ->label(__('Détails des changements'))
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) return new HtmlString("<span style='color: #94a3b8; font-style: italic;'>Aucun changement détecté</span>");

                        // 🎯 TRADUCTION EXACTE DU FORMULAIRE
                        $dict = [
                            'folder_name' => 'Nom du dossier', 'lead_traveler_name' => 'Pax Leader', 'hotel_booking_name' => 'Nom réservation hôtel',
                            'agency_id' => 'Agence émettrice', 'pax_adults' => 'Composition : Adultes', 'pax_children' => 'Composition : Enfants',
                            'start_date' => 'Date d\'arrivée au Japon', 'end_date' => 'Date de départ', 'status' => 'Statut du dossier',
                            'folder_fee' => 'Frais de dossier (¥)', 'total_price' => 'Montant total (¥)', 'flight_info' => 'Informations de vols',
                            'first_hotel_name' => 'Nom du premier hôtel', 'first_hotel_check_in' => 'Date de check-in du 1er hôtel',
                            'first_hotel_address' => 'Adresse du premier hôtel', 'ticket_dispatch_method' => 'Envoi de la billetterie',
                            'ticket_dispatch_other' => 'Lieu d\'envoi', 'product_id' => 'Produit / Activité', 'product_option_id' => 'Option choisie',
                            'item_status_id' => 'Statut de la prestation', 'service_date' => 'Date de la prestation', 'quantity' => 'Quantité / Voyageurs',
                            'unit_price' => 'Prix unitaire (¥)', 'last_name' => 'Nom de famille', 'first_name' => 'Prénom', 'birth_date' => 'Date de naissance',
                            'nationality' => 'Nationalité', 'dietary_restrictions' => 'Allergies / restrictions', 'mobility_concerns' => 'Handicap', 'phone' => 'Numéro de contact'
                        ];

                        $lines = [];
                        foreach ($state as $key => $change) {
                            $oldVal = is_bool($change['old']) ? ($change['old'] ? 'Oui' : 'Non') : (string) $change['old'];
                            $newVal = is_bool($change['new']) ? ($change['new'] ? 'Oui' : 'Non') : (string) $change['new'];

                            $oldVal = Str::limit(empty($oldVal) ? 'Vide' : $oldVal, 40);
                            $newVal = Str::limit(empty($newVal) ? 'Vide' : $newVal, 40);

                            // Décodeur de Repeaters pour un affichage humain
                            if (preg_match('/^(folderItems|folderPassengers|contact_phones)\.(\d+)\.(.+)$/', $key, $matches)) {
                                $repeaterName = match($matches[1]) { 'folderItems' => 'Prestation', 'folderPassengers' => 'Voyageur', 'contact_phones' => 'Tél', default => $matches[1] };
                                $index = (int)$matches[2] + 1;
                                $field = isset($dict[$matches[3]]) ? __($dict[$matches[3]]) : $matches[3];
                                $translatedKey = "{$repeaterName} #{$index} - {$field}";
                            } else {
                                $translatedKey = isset($dict[$key]) ? __($dict[$key]) : $key;
                            }

                            $lines[] = "<strong>{$translatedKey}</strong> : <span style='color: #ef4444; text-decoration: line-through;'>{$oldVal}</span> ➔ <span style='color: #22c55e;'>{$newVal}</span>";
                        }
                        return new HtmlString(implode('<br>', $lines));
                    })
                    ->wrap()->size('xs'),
            ])
            ->defaultSort('created_at', 'desc'); 
    }
}