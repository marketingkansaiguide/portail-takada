@include('filament.commercial.styles')
@php $items = $folder->folderItems()->with(['product', 'itemStatus'])->orderBy('service_date')->get(); @endphp
@if ($items->isEmpty())
    <span class="tk-c-muted">{{ __('Aucune prestation commandée pour l’instant.') }}</span>
@else
    <table class="tk-c-table">
        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Prestation') }}</th><th>{{ __('Qté') }}</th><th>{{ __('Rendez-vous') }}</th><th>{{ __('Informations pour la billetterie') }}</th><th>{{ __('Statut') }}</th></tr></thead>
        <tbody>
        @foreach ($items as $i)
            @php $st = mb_strtolower((string) $i->itemStatus?->name); @endphp
            <tr>
                <td style="white-space:nowrap">{{ $i->service_date?->format('d/m/Y') }}</td>
                <td>{{ $i->product?->name ?? $i->title }}</td>
                <td>{{ $i->quantity }}</td>
                <td>{{ trim(($i->meeting_time ? $i->meeting_time.' · ' : '').$i->meeting_point, ' ·') ?: '—' }}</td>
                <td style="white-space:pre-wrap;max-width:24rem">{{ $i->ticketing_notes ?: '—' }}</td>
                <td><span class="tk-c-badge {{ str_contains($st, 'confirm') ? 'ok' : (str_contains($st, 'annul') || str_contains($st, 'disponib') ? 'bad' : 'warn') }}">{{ $i->itemStatus?->name ?? '—' }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
