<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action, \App\Models\Product $record) {
                    $n = $record->usageCount();
                    if ($n > 0) {
                        \Filament\Notifications\Notification::make()
                            ->title(__('Suppression impossible'))
                            ->body(__('Ce produit est utilisé dans :n prestation(s) de dossiers. Désactivez-le plutôt que de le supprimer.', ['n' => $n]))
                            ->danger()->send();
                        $action->cancel();
                    }
                }),
        ];
    }
}
