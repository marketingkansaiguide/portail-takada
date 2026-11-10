@php
    $ids = $this->selectedPlannedIds;
    $planned = $folder ? $folder->plannedItems()->get() : collect();
    $selected = $planned->whereIn('id', $ids);
    $total = (int) $selected->sum('total_price');
    $count = $selected->count();
@endphp
@if ($planned->count() > 1)
    <div style="display:flex;gap:.5rem;align-items:center;margin-top:.5rem">
        <x-filament::button color="gray" size="sm" wire:click="selectAllPlanned({{ $planned->count() > $count ? 'true' : 'false' }})">
            {{ $planned->count() > $count ? __('Tout sélectionner') : __('Tout désélectionner') }}
        </x-filament::button>
    </div>
@endif
@if ($count > 0)
    {{-- Barre fixée en bas de la fenêtre tant que l'onglet Prestations est affiché. --}}
    <div style="height:5rem" aria-hidden="true"></div>
    <div class="tk-c-selbar" role="region" aria-label="{{ __('Sélection de prestations') }}"
         style="position:fixed;bottom:1rem;left:50%;transform:translateX(-50%);z-index:40;width:min(56rem,calc(100vw - 2rem));display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;padding:.625rem .875rem;border:1px solid #d1d5db;border-radius:.625rem;background:#fff;box-shadow:0 8px 24px -6px rgba(15,23,42,.35)">
        <span style="font-weight:600">{{ trans_choice(':count sélectionnée|:count sélectionnées', $count, ['count' => $count]) }} · {{ number_format($total, 0, ',', ' ') }} ¥</span>
        <span style="margin-left:auto;display:flex;gap:.5rem;flex-wrap:wrap">
            <x-filament::button color="gray" size="sm" wire:click="selectAllPlanned(false)">{{ __('Tout désélectionner') }}</x-filament::button>
            <x-filament::button size="sm" wire:click="orderSelected" wire:loading.attr="disabled" wire:target="orderSelected">{{ trans_choice('Commander la prestation…|Commander les :count prestations…', $count, ['count' => $count]) }}</x-filament::button>
        </span>
    </div>
@endif
