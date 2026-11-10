@php($flights = $folder->flightSegments()->orderBy('departure_local')->orderBy('id')->get())
<div style="width:100%">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
        <span class="tk-c-muted">{{ __('Informations seulement : aucune réservation aérienne n’est créée.') }}</span>
        <x-filament::button size="sm" wire:click="mountAction('editFlight')">{{ __('Ajouter un vol') }}</x-filament::button>
    </div>
    @if ($flights->isEmpty())
        <p class="tk-c-muted">{{ __('Aucun vol renseigné.') }}</p>
    @else
    <table style="width:100%;font-size:.85rem;border-collapse:collapse">
        <thead><tr style="text-align:left;color:#6b7280;border-bottom:1px solid #e5e7eb">
            <th style="padding:6px">{{ __('Sens') }}</th><th style="padding:6px">{{ __('Vol') }}</th><th style="padding:6px">{{ __('Trajet') }}</th>
            <th style="padding:6px">{{ __('Départ') }}</th><th style="padding:6px">{{ __('Arrivée') }}</th><th style="padding:6px"><span class="sr-only">{{ __('Actions') }}</span></th>
        </tr></thead>
        <tbody>
        @foreach ($flights as $f)
            <tr style="border-bottom:1px solid #f3f4f6">
                <td style="padding:6px">{{ __(\App\Models\FolderFlightSegment::DIRECTIONS[$f->direction] ?? $f->direction) }}</td>
                <td style="padding:6px">{{ trim($f->airline.' '.$f->flight_number) ?: '—' }}</td>
                <td style="padding:6px">{{ $f->departure_airport ?: '?' }} → {{ $f->arrival_airport ?: '?' }}</td>
                <td style="padding:6px;white-space:nowrap">{{ $f->departure_local?->format('d/m H:i') ?? '—' }}</td>
                <td style="padding:6px;white-space:nowrap">{{ $f->arrival_local?->format('d/m H:i') ?? '—' }}</td>
                <td style="padding:6px;white-space:nowrap;text-align:right">
                    <button type="button" style="text-decoration:underline;min-height:32px" wire:click="mountAction('editFlight', {id: {{ $f->id }}})">{{ __('Modifier') }}</button> ·
                    <button type="button" style="text-decoration:underline;color:#b91c1c;min-height:32px" wire:click="mountAction('deleteFlight', {id: {{ $f->id }}})">{{ __('Supprimer') }}</button>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
