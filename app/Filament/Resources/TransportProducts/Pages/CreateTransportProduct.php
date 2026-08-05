<?php

namespace App\Filament\Resources\TransportProducts\Pages;

use App\Filament\Resources\TransportProducts\TransportProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransportProduct extends CreateRecord
{
    protected static string $resource = TransportProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['product_type'] = 'transport';
        $data['is_on_demand'] = true;
        return $data;
    }
}