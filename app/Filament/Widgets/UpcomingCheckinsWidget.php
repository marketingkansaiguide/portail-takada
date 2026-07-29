<?php

namespace App\Filament\Widgets;

use App\Models\Folder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action; // L'import d'origine qui fonctionne !
use Filament\Widgets\TableWidget as BaseWidget;
use Carbon\Carbon;

class UpcomingCheckinsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 1;
    protected static ?int $sort = 1;

    protected function getTableHeading(): string|null
    {
        return __('🛏️ Prochains check-in');
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
                    ->label(__('Date'))
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('d/m'))
                    ->badge()
                    ->color(fn ($state) => Carbon::parse($state)->diffInDays(Carbon::now()) <= 3 ? 'danger' : 'warning'),

                Tables\Columns\TextColumn::make('folder_name')
                    ->label(__('Dossier'))
                    ->weight('bold')
                    ->wrap() // Empêche le scroll horizontal
                    ->description(fn ($record) => $record->agency->name ?? ''),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Statut'))
                    ->badge()
                    ->wrap()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                Action::make('open')
                    ->hiddenLabel()
                    ->tooltip(__('Ouvrir'))
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Folder $record): string => \App\Filament\Resources\Folders\FolderResource::getUrl('edit', ['record' => $record]))
                    ->color('gray'),
            ])
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            // L'astuce Flexbox pour forcer le 100% de hauteur
            ->extraAttributes([
                'class' => 'h-full flex flex-col',
            ]);
    }
}