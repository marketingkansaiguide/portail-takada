@include('filament.commercial.styles')
@php
    $timeline = \App\Support\TripTimeline::build($folder);
    $plannedCount = \App\Models\FolderPlannedItem::where('folder_id', $folder->id)->where('state', 'draft')->count();
    $ordered = $folder->billableFolderItems()->with('itemStatus')->get();
    $pending = $ordered->filter(fn ($i) => str_contains(mb_strtolower((string) $i->itemStatus?->name), 'attente'))->count();
    $confirmed = $ordered->filter(fn ($i) => str_contains(mb_strtolower((string) $i->itemStatus?->name), 'confirm'))->count();
    $cancelled = $folder->folderItems()->with('itemStatus')->get()->filter(fn ($i) => $i->isCancelled())->count();
@endphp

@if ($folder->ticketing_general_notes)
    <div class="tk-c-alert">
        <strong>{{ __('Consignes générales pour la billetterie') }}</strong>
        <div style="white-space:pre-wrap">{{ $folder->ticketing_general_notes }}</div>
    </div>
@endif

@if ($timeline['alerts'])
    <div class="tk-c-alert">
        <strong>{{ __('À vérifier') }}</strong>
        <ul>@foreach ($timeline['alerts'] as $a)<li>{{ $a["label"] }}</li>@endforeach</ul>
    </div>
@endif

<div class="tk-c-cards">
    <div class="tk-c-card"><div class="n">{{ $plannedCount }}</div><div class="l">{{ __('Prestations en préparation') }}</div></div>
    <div class="tk-c-card"><div class="n">{{ $ordered->count() }}</div><div class="l">{{ __('Prestations commandées') }}</div></div>
    <div class="tk-c-card"><div class="n">{{ $pending }}</div><div class="l">{{ __('En attente de traitement') }}</div></div>
    <div class="tk-c-card"><div class="n">{{ $confirmed }}</div><div class="l">{{ __('Confirmées') }}</div></div>
    <div class="tk-c-card"><div class="n">{{ $cancelled }}</div><div class="l">{{ __('Annulées / indisponibles') }}</div></div>
</div>

<div class="tk-c-two">
    <div class="tk-c-box">
        <h4>✈️ {{ __('Vols') }}</h4>
        @forelse ($folder->flightSegments as $f)
            <div class="tk-c-ent info">
                <strong>{{ trim(($f->airline ? $f->airline.' ' : '').$f->flight_number) ?: __('Vol') }}</strong>
                <span>{{ $f->departure_airport }} → {{ $f->arrival_airport }}</span>
                <span class="tk-c-muted">{{ $f->departure_local?->format('d/m H:i') }} → {{ $f->arrival_local?->format('d/m H:i') }}</span>
            </div>
        @empty
            <span class="tk-c-muted">{{ __('Aucun vol renseigné.') }}</span>
        @endforelse
    </div>
    <div class="tk-c-box">
        <h4>🏨 {{ __('Hébergements') }}</h4>
        @forelse ($folder->stays as $s)
            <div class="tk-c-ent {{ $s->booked_by === 'client' ? 'client' : 'stay' }}">
                <strong>{{ $s->hotel_name }}</strong>
                <span class="tk-c-muted">{{ $s->city }} · {{ $s->check_in?->format('d/m') }} → {{ $s->check_out?->format('d/m') }}@if ($s->nights()) ({{ $s->nights() }} {{ __('n.') }})@endif</span>
                <span class="tk-c-badge {{ $s->status === 'confirmed' ? 'ok' : ($s->status === 'cancelled' ? 'bad' : ($s->status === 'booked_by_client' ? 'info' : 'warn')) }}">{{ __(\App\Models\FolderStay::STATUSES[$s->status] ?? $s->status) }}</span>
            </div>
        @empty
            <span class="tk-c-muted">{{ __('Aucun hébergement renseigné.') }}</span>
        @endforelse
    </div>
</div>
