@include('filament.commercial.styles')
@php
    $planning = \App\Support\TripTimeline::planning($folder);
    // Statut par forme et libellé, jamais par la couleur seule : ○ préparée, ● commandée, ✔ confirmée, barré annulée.
    $mark = ['planned' => ['○', __('Préparée')], 'ordered' => ['●', __('Commandée')], 'confirmed' => ['✔', __('Confirmée')], 'cancelled' => ['✕', __('Annulée')], 'info' => ['', ''], 'free' => ['', __('À fournir')]];
    $icon = ['flight' => '✈', 'transport' => '🚆', 'item' => '🎟', 'free' => '📓'];
@endphp
<div class="tk-c-muted" style="font-size:.8rem;margin-bottom:.5rem;width:100%">
    {{ __('Un bloc par jour, avec la nuit en fin de journée. Cliquez sur une ligne préparée, un vol ou une nuit pour l’ouvrir.') }}
</div>
@forelse ($planning as $day)
    <section class="tk-c-day" style="width:100%" aria-label="{{ $day['date']->locale('fr')->isoFormat('dddd D MMMM') }}">
        <div class="d">
            {{ $day['date']->locale('fr')->isoFormat('ddd DD/MM') }}
            <div class="tk-c-muted">@if ($day['outside']){{ __('hors dossier') }}@else J{{ $day['no'] }}@endif</div>
        </div>
        <div style="flex:1;min-width:0">
            @if ($day['warnings'])
                <div class="tk-c-alert" style="margin-bottom:.4rem">
                    @foreach ($day['warnings'] as $w)<div>⚠ {{ $w }}</div>@endforeach
                </div>
            @endif
            @forelse ($day['entries'] as $e)
                @php $m = $mark[$e['status']] ?? ['', '']; $t = $e['target'] ?? null; @endphp
                <div class="tk-c-ent" style="display:flex;gap:.5rem;align-items:baseline;flex-wrap:wrap">
                    <span style="width:3rem;white-space:nowrap">{{ $e['time'] ?? '' }}@if (! $e['time'] && $e['time_missing'])<span style="color:#92400e" title="{{ __('Heure à préciser') }}">⚠</span>@endif</span>
                    <span aria-hidden="true">{{ $icon[$e['type']] ?? '•' }}</span>
                    @if ($t)
                        <button type="button" style="text-align:left;text-decoration:underline;min-height:28px;{{ $e['status'] === 'cancelled' ? 'text-decoration:line-through;' : '' }}"
                                wire:click="mountAction('{{ ['flight' => 'editFlight', 'free' => 'editFreeLine'][$t['kind']] ?? 'editPlanned' }}', {id: {{ $t['id'] }}})">{{ $e['label'] }}</button>
                    @else
                        <span style="{{ $e['status'] === 'cancelled' ? 'text-decoration:line-through;' : '' }}">{{ $e['label'] }}</span>
                    @endif
                    @if ($e['note'])<span title="{{ __('Note pour la billetterie') }}">📝</span>@endif
                    @if (! empty($e['where']))<span class="tk-c-muted">{{ \Illuminate\Support\Str::limit($e['where'], 40) }}</span>@endif
                    @if ($m[1] !== '')<span class="tk-c-muted">{{ $m[0] }} {{ $m[1] }}</span>@endif
                    @if (! $e['time'] && $e['time_missing'])<span style="color:#92400e">{{ __('heure à préciser') }}</span>@endif
                </div>
            @empty
                @if (! $day['outside'])<div class="tk-c-muted">{{ __('Journée libre') }}</div>@endif
            @endforelse
            @if ($day['night'])
                @php $n = $day['night']; @endphp
                <div class="tk-c-ent" style="display:flex;gap:.5rem;align-items:baseline;flex-wrap:wrap;{{ $n['kind'] === 'missing' ? 'color:#92400e;font-weight:600' : '' }}">
                    <span style="width:3rem"></span><span aria-hidden="true">{{ $n['kind'] === 'missing' ? '⚠' : '🏨' }}</span>
                    @if ($n['kind'] === 'missing')
                        <span>{{ __('Nuit sans hébergement') }}</span>
                        <button type="button" style="text-decoration:underline;min-height:28px" wire:click="mountAction('editStay', {id: null, check_in: '{{ $day['date']->format('Y-m-d') }}', nights: 1})">{{ __('Ajouter un hôtel') }}</button>
                    @else
                        <button type="button" style="text-align:left;text-decoration:underline;min-height:28px" wire:click="mountAction('editStay', {id: {{ $n['stay_id'] }}})">{{ __('Nuit') }} : {{ $n['label'] }}@if ($n['kind'] === 'client') · {{ __('réservé par le client') }}@endif</button>
                    @endif
                </div>
            @endif
        </div>
    </section>
@empty
    <span class="tk-c-muted">{{ __('Renseignez les dates du dossier pour afficher le planning.') }}</span>
@endforelse
