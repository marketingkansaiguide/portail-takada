@include('filament.commercial.styles')
@php
    $statuses = \App\Models\Folder::OPS_STATUSES;
    $pax = (int) $folder->pax_adults + (int) $folder->pax_children;
@endphp
<div class="tk-c-bar">
    <span class="ref">{{ $folder->reference }}</span>
    <span class="name">{{ $folder->folder_name }}</span>
    <span class="tk-c-badge info">{{ $folder->sale_type === 'b2b' ? 'B2B' : ($folder->sale_type === 'b2c' ? 'B2C' : __('Type de vente à préciser')) }}</span>
    @if ($folder->clientAgency)<span class="tk-c-muted">{{ $folder->clientAgency->name }}</span>@endif
    @if ($folder->quote_reference)<span class="tk-c-muted">{{ __('Devis') }} {{ $folder->quote_reference }}</span>@endif
    <span class="tk-c-muted">{{ $folder->start_date?->format('d/m/Y') ?? '—' }} → {{ $folder->end_date?->format('d/m/Y') ?? '—' }} · {{ $pax }} pax</span>
    <span class="tk-c-badge">{{ __($statuses[$folder->ops_status] ?? $folder->ops_status) }}</span>
    @if ($folder->ticketing_urgent)<span class="tk-c-badge bad">⚠ {{ __('URGENT billetterie') }}@php $urgentText = $folder->ticketing_urgent_note ?: \Illuminate\Support\Str::limit(trim((string) strtok((string) $folder->ticketing_general_notes, "\n")), 120); @endphp @if ($urgentText) : {{ $urgentText }}@endif</span>@endif
</div>
