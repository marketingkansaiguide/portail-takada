<?php

namespace App\Filament\Widgets;

use App\Models\Folder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action; 
use Filament\Widgets\TableWidget as BaseWidget;
use Carbon\Carbon;

class UpcomingCheckinsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 2;

    protected function getTableHeading(): string|null
    {
        return __('🛎️ Rappel des prochains check-in (Arrivées à J-15)');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Folder::query()
                    ->whereBetween('start_date', [
                        Carbon::now()->startOfDay(),
                        Carbon::now()->addDays(15)->endOfDay(),
                    ])
                    ->whereNotIn('status', ['cancelled', 'completed'])
                    ->orderBy('start_date', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('start_date')
                    ->label(__('Arrivée'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->color(function ($state) {
                        $days = Carbon::parse($state)->diffInDays(Carbon::now());
                        return $days <= 3 ? 'danger' : 'warning';
                    }),

                Tables\Columns\TextColumn::make('folder_name')
                    ->label(__('Nom du dossier'))
                    ->searchable()
                    ->weight('bold')
                    ->limit(40),

                Tables\Columns\TextColumn::make('agency.name')
                    ->label(__('Agence'))
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('lead_traveler_name')
                    ->label(__('Pax Leader'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Statut'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Action::make('open')
                    ->label(__('Ouvrir'))
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Folder $record): string => \App\Filament\Resources\Folders\FolderResource::getUrl('edit', ['record' => $record]))
                    ->button()
                    ->color('gray'),
            ])
            ->paginated([5, 10, 'all'])
            ->defaultPaginationPageOption(5);
    }
}