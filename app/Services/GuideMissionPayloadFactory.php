<?php

namespace App\Services;

use App\Models\FolderItem;
use App\Models\ProductOption;
use Illuminate\Validation\ValidationException;

class GuideMissionPayloadFactory
{
    /** @return array<string, mixed> */
    public function make(FolderItem $item): array
    {
        $item->loadMissing(['folder.agency', 'product', 'productOption']);
        $folder = $item->folder;
        $product = $item->product;
        $option = $this->guidingOption($item);
        $meetingPlace = $this->meetingPlace($item);

        $allDay = (bool) $product?->guide_all_day && blank($option?->guide_start_time);
        $required = [
            'date' => $item->service_date?->format('Y-m-d'),
            'city' => $this->guideCity($item, $option),
        ];
        if (! $allDay) {
            $required['start_time'] = $option?->guide_start_time ?: $product?->guide_start_time;
            $required['end_time'] = $option?->guide_end_time ?: $product?->guide_end_time;
        }
        $missing = array_keys(array_filter($required, fn ($value): bool => blank($value)));
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'guide_mission' => 'Configuration de guidage incomplète : '.implode(', ', $missing),
            ]);
        }

        $pax = $item->getPaxBreakdown();
        $details = [
            "Nombre de participants :\n- Adultes : {$pax['adults']}\n- Enfants : {$pax['children']}".
                ($pax['teenagers'] > 0 ? "\n- Adolescents : {$pax['teenagers']}" : '').
                "\n- Total : {$pax['total']}",
        ];

        if ($meetingPlace !== null) {
            $details[] = 'Lieu de rendez-vous :' . "\n- Hôtel : {$meetingPlace}";
        }

        $specificRequest = $this->specificRequest($item);
        if ($specificRequest !== null) {
            $details[] = "Demande spécifique :\n- {$specificRequest}";
        }

        return [
            'external_source' => 'ticketing_portal',
            'external_reference' => 'folder_item_'.$item->id,
            'ticketing_order_item_id' => $item->id,
            'title' => trim((string) ($folder?->folder_name ?: $folder?->lead_traveler_name)).' - '.$required['city'],
            // Champ obligatoire côté Planning Guides. Dossier interne : agence associée
            // (information) ou mention explicite ; dossier agence : inchangé.
            'agency' => (string) ($folder?->isInternal()
                ? ($folder->clientAgency?->name ?: 'Takada Travel (dossier interne)')
                : ($folder?->agency?->name ?? '')),
            'client' => (string) ($folder?->folder_name ?: $folder?->lead_traveler_name),
            'date' => $required['date'],
            ...($allDay ? ['all_day' => true] : [
                'start_time' => substr((string) $required['start_time'], 0, 5),
                'end_time' => substr((string) $required['end_time'], 0, 5),
            ]),
            'details' => implode("\n\n", $details),
            'participants' => $pax,
            'meeting_place' => $meetingPlace,
            'specific_request' => $specificRequest,
            'remuneration' => $this->remuneration($item, $option, $pax['total']),
        ];
    }

    private function remuneration(FolderItem $item, ?ProductOption $option, int $quantity): int
    {
        $storedAmount = (int) ($item->purchase_total_price ?? 0);
        if ($storedAmount > 0) {
            return $storedAmount;
        }

        $date = $item->service_date?->format('Y-m-d');
        $total = (float) ($item->product?->guideBasePurchaseForDate($date, $quantity) ?? 0);

        return (int) ($total + (float) ($option?->purchasePriceForDate($date, $quantity) ?? 0));
    }

    private function meetingPlace(FolderItem $item): ?string
    {
        $definitions = collect($item->product?->custom_field_definitions ?? []);
        $values = is_array($item->custom_values) ? $item->custom_values : [];

        foreach ($definitions as $definition) {
            if (($definition['type'] ?? null) !== 'client_hotel') {
                continue;
            }

            $value = $values[(string) ($definition['key'] ?? '')] ?? null;
            if (is_scalar($value) && filled($value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function specificRequest(FolderItem $item): ?string
    {
        $definitions = collect($item->product?->custom_field_definitions ?? []);
        $values = is_array($item->custom_values) ? $item->custom_values : [];

        foreach ($definitions as $definition) {
            $label = mb_strtolower((string) ($definition['name'] ?? ''));
            if (! preg_match('/demande|souhait|guidage|particuli/u', $label)) {
                continue;
            }
            $key = (string) ($definition['key'] ?? '');
            $value = $values[$key] ?? null;
            if (is_scalar($value) && filled($value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    /** Option's city first, then the one typed at booking, then the product's. */
    private function guideCity(FolderItem $item, ?ProductOption $option): string
    {
        if (filled($option?->guide_city)) {
            return trim((string) $option->guide_city);
        }

        $values = is_array($item->custom_values) ? $item->custom_values : [];
        foreach (collect($item->product?->custom_field_definitions ?? []) as $definition) {
            if (($definition['key'] ?? null) !== 'guide_city') {
                continue;
            }

            $value = $values[(string) $definition['key']] ?? null;
            if (is_scalar($value) && filled($value)) {
                return trim((string) $value);
            }
        }

        return trim((string) $item->product?->guide_city);
    }

    private function guidingOption(FolderItem $item): ?ProductOption
    {
        if ($item->productOption) {
            return $item->productOption;
        }

        $ids = collect($item->selected_options ?? [])
            ->pluck('product_option_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();

        return ProductOption::query()
            ->whereIn('id', $ids)
            ->whereNotNull('guide_start_time')
            ->whereNotNull('guide_end_time')
            ->first();
    }
}
