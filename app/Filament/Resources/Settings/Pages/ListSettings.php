<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\SettingResource;
use App\Models\Setting;
use Filament\Resources\Pages\ListRecords;

class ListSettings extends ListRecords
{
    protected static string $resource = SettingResource::class;

    public function mount(): void
    {
        parent::mount();

        $settings = Setting::query()->first();

        $this->redirect(
            $settings
                ? SettingResource::getUrl('edit', ['record' => $settings])
                : SettingResource::getUrl('create'),
        );
    }
}
