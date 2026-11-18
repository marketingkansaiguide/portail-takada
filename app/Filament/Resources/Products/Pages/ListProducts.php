<?php

namespace App\Filament\Resources\Products\Pages;

use App\Exports\ProductsExport;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label(__('Exporter en Excel'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    // Respecte les filtres et la recherche actifs dans la liste.
                    $export = new ProductsExport($this->getTableQueryForExport());

                    return Excel::download($export, 'Produits_'.now()->format('Y_m_d_His').'.xlsx');
                }),

            CreateAction::make(),
        ];
    }
}
