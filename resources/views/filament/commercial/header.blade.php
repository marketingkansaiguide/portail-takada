@include('filament.commercial.styles')
@php
    $statuses = \App\Models\Folder::OPS_STATUSES;
    $pax = (int) $folder->pax_adults + (int) $folder->pax_children;
    $nights = ($folder->start_date && $folder->end_date) ? (int) round($folder->start_date->floatDiffInDays($folder->end_date)) : null;
    $owner = $folder->owner?->name;
    $urgentText = $folder->ticketing_urgent ? ($folder->ticketing_urgent_note ?: \Illuminate\Support\Str::limit(trim((string) strtok((string) $folder->ticketing_general_notes, "\n")), 120)) : null;
@endphp
{{-- En-tête du dossier : visible sur tous les onglets, collé en haut au défilement. Le titre de la page est le nom du voyage. --}}
<div class="tk-c-bar tk-c-sticky">
    <span class="tk-c-muted">{{ $folder->start_date?->format('d/m/Y') ?? '—' }} → {{ $folder->end_date?->format('d/m/Y') ?? '—' }}@if ($nights !== null) · {{ trans_choice(':count nuit|:count nuits', $nights, ['count' => $nights]) }}@endif · {{ trans_choice(':count voyageur|:count voyageurs', $pax, ['count' => $pax]) }}@if ($owner) · {{ $owner }}@endif</span>
    <span class="tk-c-badge info">{{ $folder->sale_type === 'b2b' ? 'B2B' : ($folder->sale_type === 'b2c' ? 'B2C' : __('Type de vente à préciser')) }}</span>
    @if ($folder->clientAgency)<span class="tk-c-muted">{{ $folder->clientAgency->name }}</span>@endif
    @if ($folder->quote_reference)<span class="tk-c-muted">{{ __('Devis') }} {{ $folder->quote_reference }}</span>@endif
    <span class="tk-c-badge" title="{{ __('Changer ce statut ne commande aucune prestation.') }}">{{ __($statuses[$folder->ops_status] ?? $folder->ops_status) }}</span>
    <button type="button" class="tk-c-muted" style="font-size:.75rem" title="{{ __('Copier la référence') }}" onclick="navigator.clipboard && navigator.clipboard.writeText(@js($folder->reference))">{{ $folder->reference }}</button>
    @if ($urgentText)
        <span class="tk-c-badge bad" style="flex-basis:100%;white-space:normal;border-radius:.4rem;padding:.3rem .6rem" role="alert">⚠ {{ __('URGENT billetterie') }}@if ($urgentText) : {{ $urgentText }}@endif</span>
    @endif
</div>
