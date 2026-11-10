@php
    $tabLabels = ['stays' => __('Hébergements'), 'flights' => __('Vols'), 'items' => __('Prestations'), 'timeline' => __('Chronologie')];
@endphp
@if (! empty($alerts))
    <div class="tk-c-alert" role="region" aria-label="{{ __('À vérifier') }}">
        <strong>{{ __('À vérifier') }}</strong>
        <ul style="list-style:none;padding:0;margin:.25rem 0 0">
            @foreach ($alerts as $a)
                @php
                    $t = $a['target'] ?? null;
                    $key = ($t && ! empty($t['id']) && in_array($t['kind'] ?? '', ['stay', 'flight', 'item'], true)) ? 'record-'.$t['id'] : null;
                    $tab = $tabLabels[$t['tab'] ?? ''] ?? null;
                @endphp
                <li style="display:flex;gap:.5rem;align-items:baseline;flex-wrap:wrap;padding:.125rem 0">
                    <span>● {{ $a['label'] }}</span>
                    @if ($tab)
                        <a href="#" style="color:#a32035;font-weight:600;text-decoration:underline;min-height:24px"
                           onclick="event.preventDefault(); window.tkOpenTarget(@js($tab), @js($key));">{{ $key ? __('Ouvrir') : __('Voir') }} ›</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
