<?php

namespace App\Filament\Resources\TransportFares\Pages;

use App\Filament\Resources\TransportFares\TransportFareResource;
use App\Support\TransportFareImporter;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListTransportFares extends ListRecords
{
    protected static string $resource = TransportFareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')
                ->label(__('Importer le modèle Excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn () => auth()->user()?->hasPermission('transport_fare.create'))
                ->modalDescription(__('Remplace les tarifs du précédent import Excel (les tarifs saisis à la main sont conservés) et le calendrier des saisons s’il est rempli.'))
                ->schema([
                    FileUpload::make('file')
                        ->label(__('Modèle rempli (.xlsx)'))
                        ->disk('local')
                        ->directory('imports/transport-fares')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->required(),
                    Toggle::make('dry_run')
                        ->label(__('Vérifier seulement (ne rien enregistrer)')),
                ])
                ->action(function (array $data, TransportFareImporter $importer) {
                    $path = Storage::disk('local')->path($data['file']);
                    try {
                        $result = $importer->import($path, (bool) ($data['dry_run'] ?? false));
                    } catch (\Throwable $e) {
                        Notification::make()->title(__('Import impossible'))->body($e->getMessage())->danger()->send();

                        return;
                    } finally {
                        Storage::disk('local')->delete($data['file']);
                    }

                    $skipped = collect($result['skipped'])->map(fn ($reason, $row) => __('Ligne :row : :reason', ['row' => $row, 'reason' => $reason]))->take(15)->implode("\n");
                    Notification::make()
                        ->title(($data['dry_run'] ?? false) ? __('Vérification terminée (rien n’a été enregistré)') : __('Grille importée'))
                        ->body(__(':rows trajets avec prix → :fares tarifs ; :seasons périodes de saison.', ['rows' => $result['rows'], 'fares' => $result['fares'], 'seasons' => $result['seasons']]) . ($skipped ? "\n" . __('Lignes ignorées :') . "\n" . $skipped : ''))
                        ->color($result['skipped'] ? 'warning' : 'success')
                        ->persistent()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
