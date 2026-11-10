@php
    $tabLabels = ['stays' => __('Hôtels'), 'flights' => __('Vols'), 'items' => __('Prestations'), 'timeline' => __('Planning')];
@endphp
@if (! empty($alerts))
    <div class="tk-c-alert" role="region" aria-label="{{ __('À faire pour ce dossier') }}">
        <strong>{{ __('À faire pour ce dossier') }} ({{ count($alerts) }})</strong>
        <ul style="list-style:none;padding:0;margin:.25rem 0 0">
            @foreach ($alerts as $a)
                @php
                    $t = $a['target'] ?? null;
                    $tab = $tabLabels[$t['tab'] ?? ''] ?? null;
                    $id = ($t && ! empty($t['id'])) ? (int) $t['id'] : null;
                    $from = $t['check_in'] ?? null;
                    $nights = $t['nights'] ?? null;
                    $kind = $t['kind'] ?? null;
                @endphp
                <li style="display:flex;gap:.5rem;align-items:baseline;flex-wrap:wrap;padding:.125rem 0">
                    <span>● {{ $a['label'] }}</span>
                    @if ($tab && in_array($kind, ['stay', 'flight', 'item'], true))
                        <button type="button" style="color:#a32035;font-weight:600;text-decoration:underline;min-height:24px"
                                wire:click="openTarget('{{ $kind }}', {{ $id ?? 'null' }}, @js($tab), {{ $from ? "'".$from."'" : 'null' }}, {{ $nights ?? 'null' }})">{{ $id || $from ? __('Compléter') : __('Voir') }} ›</button>
                    @elseif ($tab)
                        <button type="button" style="color:#a32035;font-weight:600;text-decoration:underline;min-height:24px" onclick="window.tkOpenTarget(@js($tab))">{{ __('Voir') }} ›</button>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
