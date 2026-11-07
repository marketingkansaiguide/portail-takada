<?php

namespace App\Filament\Commercial\Widgets;

use App\Filament\Commercial\Resources\CommercialFolderResource;
use App\Models\Folder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Dossiers internes en cours, prochains départs d'abord. */
class FoldersToFollow extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Dossiers internes à suivre';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Folder::query()
                ->internal()
                ->whereNotIn('ops_status', ['termine', 'annule'])
                ->withCount(['plannedItems', 'allFolderItems'])
                ->orderByRaw('start_date IS NULL')
                ->orderBy('start_date'))
            ->columns([
                TextColumn::make('reference')->label(__('Référence'))->weight('bold'),
                TextColumn::make('folder_name')->label(__('Dossier'))->searchable(),
                TextColumn::make('ops_status')->label(__('Avancement'))->badge()
                    ->formatStateUsing(fn (?string $state) => Folder::OPS_STATUSES[$state] ?? ''),
                TextColumn::make('start_date')->label(__('Arrivée'))->date('d/m/Y')->placeholder('—'),
                TextColumn::make('owner.name')->label(__('Responsable')),
                TextColumn::make('planned_items_count')->label(__('En préparation')),
                TextColumn::make('all_folder_items_count')->label(__('Commandées')),
            ])
            ->filters([
                Filter::make('mine')->label(__('Mes dossiers'))
                    ->query(fn ($query) => $query->where('owner_user_id', auth('web')->id())),
            ])
            ->recordUrl(fn (Folder $record) => CommercialFolderResource::getUrl('edit', ['record' => $record]))
            ->paginated([10, 25, 50]);
    }
}
