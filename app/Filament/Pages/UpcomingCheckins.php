<?php

namespace App\Filament\Pages;

use App\Models\Folder;
use BackedEnum;
use UnitEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class UpcomingCheckins extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Check-ins';
    protected static ?string $title = 'Planning des Check-ins à venir';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.upcoming-checkins';

    public static function getNavigationBadge(): ?string
    {
        return (string) Folder::whereIn('status', ['confirmed', 'completed'])->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Folder::query()
                    ->with(['agency', 'mainSeller'])
                    ->whereIn('status', ['confirmed', 'completed'])
                    ->orderByRaw('COALESCE(first_hotel_check_in, start_date) ASC')
            )
            ->columns([
                Tables\Columns\TextColumn::make('checkin_date')
                    ->label('Check-in')
                    ->getStateUsing(fn (Folder $record) => $record->first_hotel_check_in ?? $record->start_date)
                    ->date('d/m/Y')
                    ->badge()
                    ->color(function ($state) {
                        if (!$state) return 'gray';
                        $diff = Carbon::now()->startOfDay()->diffInDays(Carbon::parse($state)->startOfDay(), false);
                        if ($diff < 0) return 'gray';
                        if ($diff <= 3) return 'danger';
                        if ($diff <= 7) return 'warning';
                        return 'success';
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("COALESCE(first_hotel_check_in, start_date) {$direction}");
                    }),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Réf. Dossier')
                    ->searchable(['reference', 'folder_name'])
                    ->weight('bold')
                    ->description(fn (Folder $record) => $record->folder_name),

                Tables\Columns\TextColumn::make('lead_traveler_name')
                    ->label('Pax Leader')
                    ->searchable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('first_hotel_name')
                    ->label('1er Hôtel (Nom)')
                    ->searchable()
                    ->default('---')
                    ->description(fn (Folder $record) => Str::limit($record->first_hotel_address ?? '', 35)),

                Tables\Columns\TextColumn::make('ticket_dispatch_method')
                    ->label('Méthode d\'envoi')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'hotel' => '🏨 Hôtel',
                        'guide' => '👤 Guide',
                        'autre' => '📍 Autre',
                        default => $state ?? '🏨 Hôtel',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'hotel' => 'info',
                        'guide' => 'warning',
                        'autre' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('dispatch_address')
                    ->label('Adresse d\'envoi')
                    ->limit(40)
                    ->tooltip(fn (Folder $record) => $record->dispatch_address),

                // 🏷️ RAPPEL VISUEL DE L'ÉTAT D'EXPORTATION DE L'ÉTIQUETTE
                Tables\Columns\TextColumn::make('label_exported_at')
                    ->label('Statut Étiquette')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? '🏷️ Exportée' : '⏳ Non exportée')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->description(fn (Folder $record) => $record->label_exported_at ? 'Le ' . $record->label_exported_at->format('d/m/Y à H:i') : null)
                    ->sortable(),

                Tables\Columns\TextColumn::make('agency.name')
                    ->label('Agence')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // 🔍 FILTRE SUR L'EXPORT DE L'ÉTIQUETTE
                SelectFilter::make('label_exported')
                    ->label('Statut Étiquette')
                    ->options([
                        'pending' => '⏳ Non exportée',
                        'exported' => '🏷️ Exportée',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'pending' => $query->whereNull('label_exported_at'),
                            'exported' => $query->whereNotNull('label_exported_at'),
                            default => $query,
                        };
                    }),

                SelectFilter::make('ticket_dispatch_method')
                    ->label('Mode d\'envoi')
                    ->options([
                        'hotel' => 'Hôtel',
                        'guide' => 'Guide',
                        'autre' => 'Autre',
                    ]),

                Filter::make('checkin_period')
                    ->form([
                        DatePicker::make('from')->label('Check-in après le'),
                        DatePicker::make('until')->label('Check-in avant le'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereRaw('COALESCE(first_hotel_check_in, start_date) >= ?', [$date]),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereRaw('COALESCE(first_hotel_check_in, start_date) <= ?', [$date]),
                            );
                    }),
            ])
            ->recordActions([
                // 🔄 ACTION INDIVIDUELLE : Basculer/Réinitialiser le statut d'export
                Action::make('toggleLabelExport')
                    ->label(fn (Folder $record) => $record->label_exported_at ? 'Marquer non exportée' : 'Marquer exportée')
                    ->icon(fn (Folder $record) => $record->label_exported_at ? 'heroicon-o-arrow-path' : 'heroicon-o-check-circle')
                    ->color(fn (Folder $record) => $record->label_exported_at ? 'gray' : 'success')
                    ->action(function (Folder $record) {
                        $record->update([
                            'label_exported_at' => $record->label_exported_at ? null : now(),
                        ]);
                    }),

                Action::make('openFolder')
                    ->label('Dossier')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->url(fn (Folder $record) => \App\Filament\Resources\Folders\FolderResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([
                BulkAction::make('export_labels')
                    ->label('Imprimer la plaquette d\'étiquettes')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->action(function (Collection $records, $livewire) {
                        // 1. Marquer automatiquement les dossiers sélectionnés comme exportés
                        $records->each(fn ($record) => $record->update(['label_exported_at' => now()]));

                        // 2. Ouvrir la plaquette d'étiquettes
                        $ids = $records->pluck('id')->implode(',');
                        $url = route('pdf.labels', ['ids' => $ids]);
                        $livewire->js("window.open('{$url}', '_blank')");
                    }),
            ]);
    }
}