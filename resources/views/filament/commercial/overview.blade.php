@include('filament.commercial.styles')
@php
    $timeline = \App\Support\TripTimeline::build($folder);
    $plannedCount = \App\Models\FolderPlannedItem::where('folder_id', $folder->id)->where('state', 'draft')->count();
    $all = $folder->folderItems()->with('itemStatus')->get();
    $cancelled = $all->filter(fn ($i) => $i->isCancelled())->count();
    $ordered = $all->reject(fn ($i) => $i->isCancelled())->filter(fn ($i) => ! $i->is_internal);
    $orderedCount = $ordered->count();
    $pending = $ordered->filter(fn ($i) => str_contains(mb_strtolower((string) $i->itemStatus?->name), 'attente'))->count();
    $confirmed = $ordered->filter(fn ($i) => str_contains(mb_strtolower((string) $i->itemStatus?->name), 'confirm'))->count();
    $total = $plannedCount + $orderedCount;
    $pct = $total > 0 ? (int) round($orderedCount / $total * 100) : 0;

    // Une seule zone « À faire » : les alertes du dossier + les prestations préparées non commandées.
    $todo = $timeline['alerts'];
    if ($plannedCount > 0) {
        $todo[] = ['type' => 'planned_pending', 'label' => trans_choice(':count prestation préparée, non commandée|:count prestations préparées, non commandées', $plannedCount, ['count' => $plannedCount]),
            'target' => ['kind' => 'item', 'id' => null, 'tab' => 'items'], 'range' => null];
    }
@endphp

@include('filament.commercial.alerts', ['alerts' => $todo])

<div class="tk-c-box" style="margin-bottom:.9rem">
    <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
        <strong>{{ __('Prestations') }} {{ $total }}</strong>
        <div style="flex:1;min-width:8rem;height:.6rem;background:#e5e7eb;border-radius:999px;overflow:hidden" role="img" aria-label="{{ $pct }} % {{ __('commandées') }}">
            <div style="width:{{ $pct }}%;height:100%;background:#16a34a"></div>
        </div>
        <span>{{ $plannedCount }} {{ __('à commander') }} · {{ $orderedCount }} {{ trans_choice('commandée|commandées', $orderedCount) }}
            @if ($orderedCount > 0)({{ $pending }} {{ __('en attente') }}, {{ $confirmed }} {{ trans_choice('confirmée|confirmées', $confirmed) }})@endif
            · {{ $cancelled }} {{ trans_choice('annulée|annulées', $cancelled) }}</span>
    </div>
</div>

<div class="tk-c-two">
    <div class="tk-c-box">
        <h4>✈️ {{ __('Vols') }}</h4>
        @forelse ($folder->flightSegments()->orderBy('departure_local')->get() as $f)
            <div class="tk-c-ent info">
                <strong>{{ $f->direction === 'outbound' ? __('Aller') : ($f->direction === 'return' ? __('Retour') : __('Vol')) }}</strong>
                <span>{{ trim(($f->airline ? $f->airline.' ' : '').$f->flight_number) }} {{ $f->departure_airport }} → {{ $f->arrival_airport }}</span>
                <span class="tk-c-muted">{{ $f->departure_local?->format('d/m H:i') }}</span>
            </div>
        @empty
            <span class="tk-c-muted">{{ __('Aucun vol renseigné.') }}</span>
        @endforelse
    </div>
    <div class="tk-c-box">
        <h4>🏨 {{ __('Hôtels') }}</h4>
        @forelse ($folder->stays()->get()->sortBy(fn ($s) => ($s->check_in?->format('Y-m-d') ?? '9999').'-'.$s->id) as $s)
            <div class="tk-c-ent {{ $s->booked_by === 'client' ? 'client' : 'stay' }}">
                <span style="white-space:nowrap">{{ $s->check_in?->format('d/m') ?? '?' }} → {{ $s->check_out?->format('d/m') ?? '?' }}</span>
                <strong>{{ $s->hotel_name }}</strong>
                <span class="tk-c-muted">{{ $s->city }}@if ($s->nights()) · {{ $s->nights() }} {{ __('n.') }}@endif · {{ $s->roomsSummary() }}</span>
                <span class="tk-c-badge {{ $s->status === 'confirmed' ? 'ok' : ($s->status === 'cancelled' ? 'bad' : ($s->status === 'booked_by_client' ? 'info' : 'warn')) }}">{{ $s->booked_by === 'client' ? __('Client') : __(\App\Models\FolderStay::STATUSES[$s->status] ?? $s->status) }}</span>
            </div>
        @empty
            <span class="tk-c-muted">{{ __('Aucun hébergement renseigné.') }}</span>
        @endforelse
    </div>
</div>

<div class="tk-c-box" style="margin-top:.9rem">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h4 style="margin:0">{{ __('Consignes billetterie') }}</h4>
        <button type="button" style="text-decoration:underline;min-height:32px" wire:click="mountAction('editFolder')">{{ __('Modifier') }}</button>
    </div>
    @if ($folder->ticketing_urgent && $folder->ticketing_urgent_note)
        <div class="tk-c-alert urgent" style="margin:.4rem 0">⚠ {{ __('URGENT') }} : {{ $folder->ticketing_urgent_note }}</div>
    @endif
    @if ($folder->ticketing_general_notes)
        <div style="white-space:pre-wrap">{{ $folder->ticketing_general_notes }}</div>
    @elseif (! $folder->ticketing_urgent)
        <span class="tk-c-muted">{{ __('Aucune consigne.') }}</span>
    @endif
</div>
