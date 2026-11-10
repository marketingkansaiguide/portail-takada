@php
    $planned = $folder->plannedItems()->with('product.category')->orderBy('service_date')->orderBy('meeting_time')->orderBy('id')->get();
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ').' ¥';
@endphp
@if ($folder->isInternal())
<div style="width:100%">
    <div style="font-weight:700;margin-bottom:.375rem">{{ __('À commander') }} ({{ $planned->count() }})</div>
    <p class="tk-c-muted" style="margin:0 0 .5rem">{{ __('Cochez les lignes à commander, puis confirmez dans la barre en bas de l’écran. Rien n’est transmis avant cette confirmation.') }}</p>
    @if ($planned->isEmpty())
        <p class="tk-c-muted">{{ __('Aucune prestation préparée. Utilisez l’ajout rapide ci-dessus.') }}</p>
    @else
    <ul style="list-style:none;margin:0;padding:0">
    @foreach ($planned as $p)
        @php
            $name = \App\Support\EntryProfile::itemLabel((string) ($p->product?->name ?? $p->title ?? __('Prestation')), (array) $p->custom_values, $p->route_from, $p->route_to);
            $where = trim(($p->meeting_time ? $p->meeting_time.' ' : '').$p->meeting_point);
            $checked = in_array($p->id, $this->selectedPlannedIds, true);
        @endphp
        <li style="display:flex;align-items:center;gap:.5rem;padding:.25rem .5rem;border:1px solid #e5e7eb;border-radius:.5rem;margin-bottom:.375rem;background:#fff">
            <label style="display:inline-flex;align-items:center;justify-content:center;min-width:36px;min-height:36px;cursor:pointer">
                <input type="checkbox" class="fi-checkbox-input" style="width:1.125rem;height:1.125rem" wire:click="togglePlannedSelection({{ $p->id }})" @checked($checked)
                       aria-label="{{ __('Sélectionner pour commander') }} : {{ $name }}">
            </label>
            <button type="button" style="flex:1;text-align:left;min-height:36px;display:flex;gap:.75rem;flex-wrap:wrap;align-items:baseline" wire:click="mountAction('editPlanned', {id: {{ $p->id }}})">
                <span style="white-space:nowrap">{{ $p->service_date?->format('d/m') ?? '—' }}@if ($p->meeting_time) {{ $p->meeting_time }}@endif</span>
                <strong>{{ $name }}</strong>
                <span class="tk-c-muted">{{ (int) $p->quantity }} pax</span>
                @if ($where)<span class="tk-c-muted">{{ \Illuminate\Support\Str::limit($where, 50) }}</span>@endif
                @if ($p->ticketing_notes)<span title="{{ $p->ticketing_notes }}">📝 {{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $p->ticketing_notes), 50) }}</span>@endif
                @if (! $p->service_date)<span style="color:#b91c1c">⚠ {{ __('date manquante') }}</span>@endif
            </button>
            <span style="white-space:nowrap">{{ $money($p->total_price) }}</span>
            <button type="button" style="text-decoration:underline;color:#b91c1c;min-height:32px;padding:0 .25rem" wire:click="mountAction('deletePlanned', {id: {{ $p->id }}})">{{ __('Supprimer') }}</button>
        </li>
    @endforeach
    </ul>
    @endif
</div>
@endif
