<?php

namespace App\Filament\Agency\Resources\AgencyFolderResource\Pages;

use App\Filament\Agency\Resources\AgencyFolderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class EditAgencyFolder extends EditRecord
{
    protected static string $resource = AgencyFolderResource::class;

    public function form(Schema $schema): Schema
    {
        $components = parent::form($schema)->getComponents();
        
        // 💡 L'AGENCE A DROIT À SON ESPACE DE DISCUSSION AVEC VOUS
        $components[] = Section::make('Discussion avec l\'équipe Takada')
            ->description('Un doute, une modification tarifaire ou une question sur ce dossier ? Échangez directement avec nous ici.')
            ->schema([
                Placeholder::make('chat_placeholder')
                    ->hiddenLabel()
                    ->content(fn ($record) => new HtmlString(
                        \Illuminate\Support\Facades\Blade::render('@livewire("folder-chat", ["folder" => $folder])', ['folder' => $record])
                    )),
            ]);

        return $schema->components($components);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Sécurité lors de la mise à jour, on recalcule le prix exact des prestations demandées
        $totalItems = 0;
        if (isset($data['folderItems']) && is_array($data['folderItems'])) {
            foreach ($data['folderItems'] as $item) {
                $totalItems += (float) ($item['total_price'] ?? 0);
            }
        }
        $data['total_price'] = $totalItems;
        
        return $data;
    }
}