<?php

namespace App\Filament\Commercial\Resources;

use App\Filament\Commercial\Resources\CommercialFolderResource\Pages;
use App\Filament\Resources\Folders\FolderResource;

/**
 * Les dossiers dans l'espace commercial : même fiche, mêmes règles et même moteur
 * que l'administration (FolderResource), avec des adresses propres à cet espace.
 */
class CommercialFolderResource extends FolderResource
{
    protected static ?string $slug = 'dossiers';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('Dossiers');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Dossiers');
    }

    /**
     * Liste des dossiers du commercial : le voyage d'abord, la référence en fin de ligne, aucune pré-facture,
     * des statuts en français et « — » quand un montant est inconnu.
     */
    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['plannedItems', 'folderItems']))
            ->defaultSort('start_date', 'desc')
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('folder_name')->label(__('Voyage'))->weight('bold')->searchable(['folder_name', 'reference'])->sortable()->wrap()->extraHeaderAttributes(['style' => 'min-width:12.5rem'])->extraCellAttributes(['style' => 'min-width:12.5rem'])
                    ->getStateUsing(fn (\App\Models\Folder $r) => $r->folder_name ?: ($r->lead_traveler_name ?: $r->reference))
                    ->description(fn (\App\Models\Folder $r) => $r->reference),
                \Filament\Tables\Columns\TextColumn::make('start_date')->label(__('Dates'))->sortable()
                    ->listWithLineBreaks()
                    ->getStateUsing(fn (\App\Models\Folder $r) => [($r->start_date?->format('d/m/y') ?? '—'), '→ ' . ($r->end_date?->format('d/m/y') ?? '—')]),
                \Filament\Tables\Columns\TextColumn::make('pax_adults')->label(__('Pax'))
                    ->getStateUsing(fn (\App\Models\Folder $r) => (int) $r->pax_adults + (int) $r->pax_children),
                \Filament\Tables\Columns\TextColumn::make('owner.name')->label(__('Commercial'))->formatStateUsing(fn (?string $state) => $state ? \Illuminate\Support\Str::before($state, ' ') : '—'),
                \Filament\Tables\Columns\TextColumn::make('ops_status')->label(__('Avancement'))->badge()->wrap()
                    ->formatStateUsing(fn (?string $state) => __(\App\Models\Folder::OPS_STATUSES[$state] ?? (string) $state)),
                \Filament\Tables\Columns\TextColumn::make('planned_items_count')->label(__('Prestations'))->wrap()->extraHeaderAttributes(['style' => 'min-width:8.5rem'])->extraCellAttributes(['style' => 'min-width:8.5rem'])
                    ->getStateUsing(fn (\App\Models\Folder $r) => $r->isInternal()
                        ? (int) $r->planned_items_count . ' ' . __('à commander') . ' · ' . (int) $r->folder_items_count . ' ' . trans_choice('commandée|commandées', (int) $r->folder_items_count)
                        : '—'),
                \Filament\Tables\Columns\TextColumn::make('total_price')->label(__('Montant'))
                    ->getStateUsing(function (\App\Models\Folder $r) {
                        $sum = (float) $r->billableFolderItems()->sum('total_price');

                        return $sum > 0 ? number_format($sum, 0, ',', ' ') . ' ¥' : '—';
                    }),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('ops_status')->label(__('Avancement'))->options(array_map(fn ($l) => __($l), \App\Models\Folder::OPS_STATUSES)),
                \Filament\Tables\Filters\SelectFilter::make('owner_user_id')->label(__('Commercial'))->relationship('owner', 'name'),
                \Filament\Tables\Filters\Filter::make('period')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('from')->label(__('Voyage à partir du')),
                        \Filament\Forms\Components\DatePicker::make('until')->label(__('Voyage jusqu’au')),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('end_date', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('start_date', '<=', $d))),
            ])
            ->recordActions([\Filament\Actions\EditAction::make()->label(__('Ouvrir'))->iconButton()->tooltip(__('Ouvrir'))])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommercialFolders::route('/'),
            'create' => Pages\CreateCommercialFolder::route('/nouveau-dossier-agence'),
            'create-internal' => Pages\CreateCommercialInternalFolder::route('/nouveau'),
            'edit' => Pages\EditCommercialFolder::route('/{record}'),
        ];
    }
}
