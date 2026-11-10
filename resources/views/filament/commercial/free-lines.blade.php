@php $lines = $folder->freeLines()->get(); @endphp
@if ($folder->isInternal())
<div style="width:100%;margin-top:1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.375rem">
        <div>
            <strong>{{ __('Lignes libres') }} ({{ $lines->count() }})</strong>
            <span class="tk-c-muted">{{ __('À fournir, sans commande fournisseur : jamais dans la commande.') }}</span>
        </div>
        <x-filament::button size="sm" color="gray" wire:click="mountAction('editFreeLine')">{{ __('Ajouter une ligne libre') }}</x-filament::button>
    </div>
    @foreach ($lines as $fl)
        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;padding:.25rem .5rem;border:1px dashed #d1d5db;border-radius:.5rem;margin-bottom:.375rem;background:#fff">
            <button type="button" style="flex:1;text-align:left;min-height:36px;display:flex;gap:.75rem;flex-wrap:wrap;align-items:baseline" wire:click="mountAction('editFreeLine', {id: {{ $fl->id }}})">
                <span style="white-space:nowrap">{{ $fl->service_date?->format('d/m') ?? '—' }}</span>
                <strong>📓 {{ $fl->label }}</strong>
                <span class="tk-c-muted">{{ $fl->pax }} pax</span>
                @if ($fl->note)<span title="{{ $fl->note }}">📝 {{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $fl->note), 50) }}</span>@endif
                <span class="tk-c-muted">{{ __('À fournir') }}</span>
            </button>
            <span style="white-space:nowrap">{{ $fl->sale_price !== null ? number_format($fl->sale_price, 0, ',', ' ').' ¥' : '—' }}</span>
            <button type="button" style="text-decoration:underline;color:#b91c1c;min-height:32px" wire:click="mountAction('deleteFreeLine', {id: {{ $fl->id }}})">{{ __('Supprimer') }}</button>
        </div>
    @endforeach
</div>
@endif
