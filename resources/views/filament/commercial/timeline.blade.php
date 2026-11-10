@include('filament.commercial.styles')
@php $timeline = \App\Support\TripTimeline::build($folder); @endphp
<div class="tk-c-muted" style="font-size:.8rem;margin-bottom:.5rem">
    {{ __('Lecture seule, d’après les données enregistrées. Trait plein vert : commandée. Trait pointillé : simplement préparée (non commandée, non confirmée).') }}
</div>
@include('filament.commercial.alerts', ['alerts' => $timeline['alerts']])
@forelse ($timeline['days'] as $day)
    <div class="tk-c-day">
        <div class="d">{{ $day['date']->locale('fr')->isoFormat('ddd D MMM') }}</div>
        <div>
            @foreach ($day['entries'] as $e)
                <div class="tk-c-ent {{ $e['state'] }}">
                    <span>{{ $e['label'] }}@if (! empty($e['note'])) <span title="{{ __('Informations pour la billetterie') }}">📝</span>@endif</span>
                    @if ($e['sub'])<span class="tk-c-muted">{{ $e['sub'] }}</span>@endif
                    @if ($e['state'] === 'planned')<span class="tk-c-badge plan">{{ __('Préparée') }}</span>@endif
                </div>
            @endforeach
        </div>
    </div>
@empty
    <span class="tk-c-muted">{{ __('Rien à afficher : ajoutez des vols, hébergements ou prestations datés.') }}</span>
@endforelse
