@php
    $stays = $folder->stays()->get()->sortBy(fn ($s) => ($s->check_in?->format('Y-m-d') ?? '9999').'-'.$s->id)->values();
    $gaps = collect(\App\Support\TripTimeline::build($folder)['alerts'])->where('type', 'nights_missing')->map(fn ($a) => $a['range'])->values();
    $rows = collect();
    foreach ($stays as $s) { $rows->push(['at' => $s->check_in?->format('Y-m-d') ?? '9999', 'kind' => 'stay', 'stay' => $s]); }
    foreach ($gaps as $g) { $rows->push(['at' => $g['from'], 'kind' => 'gap', 'gap' => $g]); }
    $rows = $rows->sortBy('at')->values();
@endphp
<div style="width:100%;overflow-x:auto">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
    <span class="tk-c-muted">{{ __('Tous les séjours, qu’ils soient réservés par Takada, l’agence ou le client. Un hébergement n’est jamais une commande.') }}</span>
    <x-filament::button size="sm" wire:click="mountAction('editStay')">{{ __('Ajouter un hôtel') }}</x-filament::button>
</div>
@if ($stays->isEmpty() && $gaps->isEmpty())
    <p class="tk-c-muted">{{ __('Aucun hébergement renseigné.') }}</p>
@else
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
                <td style="padding:6px"><button type="button" style="text-decoration:underline;font-weight:600;min-height:28px" wire:click="mountAction('editStay', {id: null, check_in: '{{ $g['from'] }}', nights: {{ $g['nights'] }}})">{{ __('Ajouter un hôtel sur ces dates') }}</button></td>
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
                    <button type="button" style="text-decoration:underline;min-height:28px" wire:click="mountAction('editStay', {id: {{ $s->id }}})">{{ __('Modifier') }}</button> ·
                    <button type="button" style="text-decoration:underline;min-height:28px" wire:click="duplicateStay({{ $s->id }})">{{ __('Dupliquer') }}</button> ·
                    <button type="button" style="text-decoration:underline;color:#b91c1c;min-height:28px" wire:click="mountAction('deleteStay', {id: {{ $s->id }}})">{{ __('Supprimer') }}</button>
                </td>
            </tr>
        @endif
    @endforeach
    </tbody>
</table>
@endif
</div>
