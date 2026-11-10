@php
    $stays = $folder->stays()->get()->sortBy(fn ($s) => ($s->check_in?->format('Y-m-d') ?? '9999').'-'.$s->id)->values();
    $gaps = collect(\App\Support\TripTimeline::build($folder)['alerts'])->where('type', 'nights_missing')->map(fn ($a) => $a['range'])->values();
    $rows = collect();
    foreach ($stays as $s) { $rows->push(['at' => $s->check_in?->format('Y-m-d') ?? '9999', 'kind' => 'stay', 'stay' => $s]); }
    foreach ($gaps as $g) { $rows->push(['at' => $g['from'], 'kind' => 'gap', 'gap' => $g]); }
    $rows = $rows->sortBy('at')->values();
@endphp
@if ($stays->isNotEmpty() || $gaps->isNotEmpty())
<div style="overflow-x:auto;margin-bottom:.75rem">
<table style="width:100%;font-size:.85rem;border-collapse:collapse">
    <thead><tr style="text-align:left;color:#6b7280;border-bottom:1px solid #e5e7eb">
        <th style="padding:6px">{{ __('Arrivée') }}</th><th style="padding:6px">{{ __('Départ') }}</th><th style="padding:6px">{{ __('Nuits') }}</th>
        <th style="padding:6px">{{ __('Hôtel · ville') }}</th><th style="padding:6px">{{ __('Chambres') }}</th><th style="padding:6px">{{ __('Repas') }}</th>
        <th style="padding:6px">{{ __('Statut') }}</th><th style="padding:6px">{{ __('Réservé par') }}</th><th style="padding:6px"><span class="sr-only">{{ __('Actions') }}</span></th>
    </tr></thead>
    <tbody>
    @foreach ($rows as $r)
        @if ($r['kind'] === 'gap')
            @php $g = $r['gap']; @endphp
            <tr style="background:#fffbeb;color:#92400e">
                <td colspan="8" style="padding:6px;font-weight:600">⚠ {{ \Carbon\Carbon::parse($g['from'])->format('d/m') }} → {{ \Carbon\Carbon::parse($g['to'])->format('d/m') }} : {{ trans_choice(':count nuit sans hébergement|:count nuits sans hébergement', $g['nights'], ['count' => $g['nights']]) }}</td>
                <td style="padding:6px"><button type="button" style="text-decoration:underline;font-weight:600;min-height:28px" wire:click="addStayOnGap('{{ $g['from'] }}', {{ $g['nights'] }})">{{ __('Ajouter un hôtel sur ces dates') }}</button></td>
            </tr>
        @else
            @php $s = $r['stay']; @endphp
            <tr style="border-bottom:1px solid #f3f4f6">
                <td style="padding:6px;white-space:nowrap">{{ $s->check_in?->format('d/m') ?? '—' }}</td>
                <td style="padding:6px;white-space:nowrap">{{ $s->check_out?->format('d/m') ?? '—' }}</td>
                <td style="padding:6px">{{ $s->nights() ?? '—' }}</td>
                <td style="padding:6px"><strong>{{ $s->hotel_name }}</strong>@if ($s->city) · {{ $s->city }}@endif</td>
                <td style="padding:6px">{{ $s->roomsSummary() ?: '—' }}</td>
                <td style="padding:6px">{{ $s->meal_plan ? __(\App\Models\FolderStay::MEAL_PLANS[$s->meal_plan] ?? $s->meal_plan) : '—' }}</td>
                <td style="padding:6px">{{ __(\App\Models\FolderStay::STATUSES[$s->status] ?? $s->status) }}</td>
                <td style="padding:6px">{{ __(\App\Models\FolderStay::BOOKED_BY[$s->booked_by] ?? $s->booked_by) }}</td>
                <td style="padding:6px;white-space:nowrap">
                    <a href="#" style="text-decoration:underline" onclick="event.preventDefault(); window.tkOpenTarget(@js(__('Hébergements')), 'record-{{ $s->id }}');">{{ __('Modifier') }}</a> ·
                    <button type="button" style="text-decoration:underline" wire:click="duplicateStay({{ $s->id }})">{{ __('Dupliquer') }}</button>
                </td>
            </tr>
        @endif
    @endforeach
    </tbody>
</table>
</div>
<script>
    window.tkOpenTarget = window.tkOpenTarget || function (tabLabel, key) {
        const tab = [...document.querySelectorAll('[role=tab]')].find(t => t.innerText.trim().startsWith(tabLabel));
        if (tab) tab.click();
        if (! key) return;
        setTimeout(() => {
            const panel = document.querySelector('[role=tabpanel].fi-active') || document;
            const item = panel.querySelector('[x-sortable-item="' + key + '"]');
            if (item) { item.dispatchEvent(new CustomEvent('expand')); item.scrollIntoView({block: 'center', behavior: 'smooth'}); }
        }, 350);
    };
</script>
@endif
