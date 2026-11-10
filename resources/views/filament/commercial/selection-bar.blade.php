@php
    $ids = $this->selectedPlannedIds;
    $planned = $folder ? $folder->plannedItems()->get() : collect();
    $selected = $planned->whereIn('id', $ids);
    $total = (int) $selected->sum('total_price');
    $count = $selected->count();
@endphp
<div class="tk-c-selbar" style="position:sticky;bottom:0;z-index:20;margin-top:.75rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;padding:.625rem .875rem;border:1px solid #d1d5db;border-radius:.5rem;background:#fff;box-shadow:0 -4px 10px -6px rgba(15,23,42,.2)">
    <span style="font-weight:600">
        @if ($count > 0)
            {{ trans_choice(':count sélectionnée|:count sélectionnées', $count, ['count' => $count]) }} · {{ number_format($total, 0, ',', ' ') }} ¥
        @else
            {{ __('Aucune prestation sélectionnée') }} <span class="tk-c-muted">({{ $planned->count() }} {{ __('à commander') }})</span>
        @endif
    </span>
    <span style="margin-left:auto;display:flex;gap:.5rem;flex-wrap:wrap">
        @if ($planned->count() > $count)
            <x-filament::button color="gray" size="sm" wire:click="selectAllPlanned(true)">{{ __('Tout sélectionner') }}</x-filament::button>
        @endif
        @if ($count > 0)
            <x-filament::button color="gray" size="sm" wire:click="selectAllPlanned(false)">{{ __('Tout désélectionner') }}</x-filament::button>
            <x-filament::button size="sm" wire:click="orderSelected" wire:loading.attr="disabled" wire:target="orderSelected">{{ trans_choice('Commander la prestation…|Commander les :count prestations…', $count, ['count' => $count]) }}</x-filament::button>
        @endif
    </span>
</div>
