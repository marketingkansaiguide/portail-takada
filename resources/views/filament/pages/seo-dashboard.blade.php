<x-filament-panels::page>
    <style>
        .seo-dash { display: flex; flex-direction: column; gap: 20px; }
        .seo-toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; justify-content: space-between; }
        .seo-toolbar-fields { display: flex; flex-wrap: wrap; gap: 12px; }
        .seo-toolbar label { display: block; font-size: 12px; font-weight: 600; color: var(--gray-500); margin-bottom: 4px; }
        .seo-period { font-size: 13px; color: var(--gray-500); }
        .seo-panel { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 18px 20px; }
        .dark .seo-panel { background: var(--gray-900); border-color: rgba(255, 255, 255, .1); }
        .seo-panel-title { font-size: 15px; font-weight: 700; margin: 0; color: var(--gray-950); }
        .dark .seo-panel-title { color: #fff; }
        .seo-muted { font-size: 12px; color: var(--gray-500); }
        .dark .seo-muted { color: var(--gray-400); }
        .seo-kpis { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; margin-top: 14px; }
        .seo-kpi { border: 1px solid var(--gray-100); border-radius: 10px; padding: 10px 12px; background: var(--gray-50); }
        .dark .seo-kpi { border-color: rgba(255, 255, 255, .06); background: rgba(255, 255, 255, .03); }
        .seo-kpi-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: var(--gray-500); }
        .seo-kpi-value { font-size: 20px; font-weight: 800; color: var(--gray-950); margin-top: 2px; font-variant-numeric: tabular-nums; }
        .dark .seo-kpi-value { color: #fff; }
        .seo-kpi-delta { font-size: 12px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .seo-good { color: var(--success-600); }
        .seo-bad { color: var(--danger-600); }
        .seo-neutral { color: var(--gray-500); }
        .dark .seo-good { color: var(--success-400); }
        .dark .seo-bad { color: var(--danger-400); }
        .seo-site-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px; align-items: flex-start; }
        .seo-site-links { display: flex; gap: 12px; font-size: 13px; }
        .seo-site-links a { color: var(--primary-600); font-weight: 600; }
        .dark .seo-site-links a { color: var(--primary-400); }
        .seo-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; margin-top: 16px; }
        .seo-block-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: var(--gray-500); margin-bottom: 8px; }
        .seo-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; font-size: 13px; color: var(--gray-700); }
        .dark .seo-list { color: var(--gray-300); }
        .seo-list li { display: flex; gap: 8px; justify-content: space-between; }
        .seo-ellipsis { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
        .seo-pills { display: flex; flex-wrap: wrap; gap: 6px; }
        .seo-pill { font-size: 12px; font-weight: 700; padding: 2px 9px; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); }
        .dark .seo-pill { background: rgba(255, 255, 255, .08); color: var(--gray-200); }
        .seo-pill-critical { background: var(--danger-50); color: var(--danger-700); }
        .seo-pill-warning { background: var(--warning-50); color: var(--warning-700); }
        .seo-pill-ok { background: var(--success-50); color: var(--success-700); }
        .dark .seo-pill-critical { background: rgba(239, 68, 68, .15); color: var(--danger-300); }
        .dark .seo-pill-warning { background: rgba(245, 158, 11, .15); color: var(--warning-300); }
        .dark .seo-pill-ok { background: rgba(34, 197, 94, .15); color: var(--success-300); }
        .seo-error { border: 1px solid var(--danger-200); background: var(--danger-50); color: var(--danger-700); border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-top: 12px; }
        .dark .seo-error { border-color: rgba(239, 68, 68, .3); background: rgba(239, 68, 68, .1); color: var(--danger-300); }
        .seo-spark { width: 100%; height: 56px; display: block; }
        .seo-legend { display: flex; gap: 12px; font-size: 11px; color: var(--gray-500); margin-top: 4px; }
        .seo-legend i { display: inline-block; width: 14px; height: 3px; border-radius: 2px; vertical-align: middle; margin-right: 4px; }
        .seo-empty { text-align: center; padding: 40px 20px; }
    </style>

    <div class="seo-dash">
        <div class="seo-toolbar">
            <div class="seo-toolbar-fields">
                <div>
                    <label for="seo-preset">{{ __('Période') }}</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="seo-preset" wire:model.live="preset">
                            @foreach ($presets as $value => $label)
                                <option value="{{ $value }}">{{ __($label) }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
                <div>
                    <label for="seo-compare">{{ __('Comparer à') }}</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="seo-compare" wire:model.live="compare">
                            @foreach ($compares as $value => $label)
                                <option value="{{ $value }}">{{ __($label) }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            </div>
            @if ($periodLabel)
                <div class="seo-period">{{ $periodLabel }}</div>
            @endif
        </div>

        <div wire:loading.delay class="seo-muted">{{ __('Lecture des sites en cours…') }}</div>

        @if (empty($cards))
            <div class="seo-panel seo-empty">
                <p class="seo-panel-title">{{ __('Aucun site suivi pour l\'instant') }}</p>
                <p class="seo-muted" style="margin-top: 6px;">
                    {{ __('Ajoutez un site WordPress équipé de l\'extension Takada SEO Suivi avec sa clé d\'accès.') }}
                </p>
                @if (\App\Filament\Resources\SeoSites\SeoSiteResource::canCreate())
                    <div style="margin-top: 14px;">
                        <x-filament::button tag="a" :href="\App\Filament\Resources\SeoSites\SeoSiteResource::getUrl('create')" icon="heroicon-o-plus">
                            {{ __('Ajouter un site') }}
                        </x-filament::button>
                    </div>
                @endif
            </div>
        @else
            @if (count($cards) > 1)
                <div class="seo-panel">
                    <p class="seo-panel-title">{{ __('Tous les sites') }}</p>
                    <p class="seo-muted">
                        {{ trans_choice(':ok site sur :total lu avec succès|:ok sites sur :total lus avec succès', $totals['ok'], ['ok' => $totals['ok'], 'total' => $totals['sites']]) }}
                    </p>
                    <div class="seo-kpis">
                        @foreach ($totals['kpis'] as $kpi)
                            @include('filament.pages.partials.seo-kpi', ['kpi' => $kpi])
                        @endforeach
                    </div>
                </div>
            @endif

            @foreach ($cards as $card)
                <div class="seo-panel" wire:key="seo-site-{{ $loop->index }}">
                    <div class="seo-site-head">
                        <div>
                            <p class="seo-panel-title">{{ $card['name'] }}</p>
                            <p class="seo-muted">
                                {{ $card['host'] }}
                                @if ($card['plugin_version']) · {{ __('extension') }} {{ $card['plugin_version'] }} @endif
                                @if ($card['fetched_at']) · {{ __('lu à') }} {{ $card['fetched_at'] }} @endif
                            </p>
                        </div>
                        <div class="seo-site-links">
                            <a href="{{ $card['url'] }}" target="_blank" rel="noopener">{{ __('Voir le site') }}</a>
                            <a href="{{ $card['admin_url'] }}" target="_blank" rel="noopener">{{ __('Suivi SEO détaillé') }}</a>
                        </div>
                    </div>

                    @if ($card['error'])
                        <div class="seo-error">{{ $card['error'] }}</div>
                    @endif

                    @if (! $card['error'] || collect($card['kpis'])->contains(fn ($k) => $k['value'] !== '—'))
                        <div class="seo-kpis">
                            @foreach ($card['kpis'] as $kpi)
                                @include('filament.pages.partials.seo-kpi', ['kpi' => $kpi])
                            @endforeach
                        </div>

                        <div class="seo-grid">
                            {{-- Clics par semaine --}}
                            <div>
                                <div class="seo-block-title">{{ __('Clics Google par semaine') }}</div>
                                @php
                                    $a = $card['spark']['a'];
                                    $b = $card['spark']['b'];
                                    // Échelle min-max commune aux deux périodes, pour que les variations restent lisibles.
                                    $all = array_merge($a, $b) ?: [0];
                                    $min = min($all);
                                    $range = max(1, max($all) - $min);
                                    $n = max(count($a), count($b));
                                    $pts = function (array $values) use ($min, $range, $n) {
                                        if (count($values) < 2) {
                                            return '';
                                        }
                                        $step = 236 / max(1, $n - 1);
                                        return collect($values)->map(fn ($v, $i) => round(2 + $i * $step, 1).','.round(52 - (($v - $min) / $range) * 48, 1))->implode(' ');
                                    };
                                @endphp
                                @if ($n >= 2)
                                    <svg class="seo-spark" viewBox="0 0 240 56" preserveAspectRatio="none" role="img" aria-label="{{ __('Clics par semaine, période actuelle et période de comparaison') }}">
                                        <polyline points="{{ $pts($b) }}" fill="none" stroke="var(--gray-400)" stroke-width="1.5" stroke-dasharray="4 3" vector-effect="non-scaling-stroke" />
                                        <polyline points="{{ $pts($a) }}" fill="none" stroke="var(--primary-500)" stroke-width="2.5" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                                    </svg>
                                    <div class="seo-legend">
                                        <span><i style="background: var(--primary-500)"></i>{{ __('Période') }}</span>
                                        <span><i style="background: var(--gray-400)"></i>{{ __('Comparaison') }}</span>
                                    </div>
                                @else
                                    <p class="seo-muted">{{ __('Pas assez de semaines sur cette période.') }}</p>
                                @endif
                            </div>

                            {{-- Alertes --}}
                            <div>
                                <div class="seo-block-title">{{ __('Alertes') }}</div>
                                <div class="seo-pills" style="margin-bottom: 8px;">
                                    @if ($card['alerts']['counts']['critical'])
                                        <span class="seo-pill seo-pill-critical">{{ $card['alerts']['counts']['critical'] }} {{ __('critique(s)') }}</span>
                                    @endif
                                    @if ($card['alerts']['counts']['warning'])
                                        <span class="seo-pill seo-pill-warning">{{ $card['alerts']['counts']['warning'] }} {{ __('à surveiller') }}</span>
                                    @endif
                                    @if (! array_sum($card['alerts']['counts']))
                                        <span class="seo-pill seo-pill-ok">{{ __('Aucune alerte') }}</span>
                                    @endif
                                </div>
                                <ul class="seo-list">
                                    @foreach ($card['alerts']['items'] as $alert)
                                        <li><span title="{{ $alert['message'] ?? '' }}" style="font-size: 12px;">{{ \Illuminate\Support\Str::limit($alert['message'] ?? '', 140) }}</span></li>
                                    @endforeach
                                </ul>
                            </div>

                            {{-- Pages en mouvement --}}
                            <div>
                                <div class="seo-block-title">{{ __('Pages en mouvement (clics)') }}</div>
                                <ul class="seo-list">
                                    @forelse (array_merge($card['movers']['up'], $card['movers']['down']) as $row)
                                        @php $d = (int) ($row['clicks_delta'] ?? 0); @endphp
                                        <li>
                                            <span class="seo-ellipsis" title="{{ $row['url'] ?? '' }}">{{ parse_url($row['url'] ?? '', PHP_URL_PATH) ?: '/' }}</span>
                                            <span class="seo-kpi-delta {{ $d >= 0 ? 'seo-good' : 'seo-bad' }}">{{ $d >= 0 ? '+' : '' }}{{ number_format($d, 0, ',', ' ') }}</span>
                                        </li>
                                    @empty
                                        <li class="seo-muted">{{ __('Aucune variation notable.') }}</li>
                                    @endforelse
                                </ul>
                            </div>

                            {{-- Plan d'action --}}
                            <div>
                                <div class="seo-block-title">{{ __('Plan d\'action') }}</div>
                                <div class="seo-pills" style="margin-bottom: 8px;">
                                    <span class="seo-pill">{{ $card['actions']['todo'] }} {{ __('à faire') }}</span>
                                    <span class="seo-pill seo-pill-ok">{{ $card['actions']['done'] }} {{ __('faite(s)') }}</span>
                                </div>
                                <ul class="seo-list">
                                    @foreach ($card['actions']['top'] as $action)
                                        <li>
                                            <span class="seo-ellipsis" title="{{ $action['title'] ?? '' }}">{{ $action['title'] ?? '' }}</span>
                                            @if (! empty($action['gain_clicks']))
                                                <span class="seo-muted" style="white-space: nowrap;">+{{ number_format((float) $action['gain_clicks'], 0, ',', ' ') }} {{ __('clics') }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            {{-- Santé technique --}}
                            <div>
                                <div class="seo-block-title">{{ __('Santé technique') }}</div>
                                <ul class="seo-list">
                                    <li>
                                        <span>{{ __('Surveillance') }}</span>
                                        @if ($card['monitor']['total'] === 0)
                                            <span class="seo-muted">{{ __('pas encore de contrôle') }}</span>
                                        @elseif ($card['monitor']['down'] > 0)
                                            <span class="seo-pill seo-pill-critical">{{ $card['monitor']['down'] }} / {{ $card['monitor']['total'] }} {{ __('en erreur') }}</span>
                                        @else
                                            <span class="seo-pill seo-pill-ok">{{ $card['monitor']['total'] }} {{ __('page(s) OK') }}</span>
                                        @endif
                                    </li>
                                    @if ($card['monitor']['availability'] !== null)
                                        <li><span>{{ __('Disponibilité 7 j (pire page)') }}</span><span>{{ number_format($card['monitor']['availability'], 2, ',', ' ') }} %</span></li>
                                    @endif
                                    <li>
                                        <span>{{ __('Audit') }}</span>
                                        @if ($card['audit']['run'])
                                            <span class="seo-muted">{{ $card['audit']['pages'] }} {{ __('pages') }} · {{ $card['audit']['finished_at'] }}</span>
                                        @else
                                            <span class="seo-muted">{{ __('pas encore d\'audit') }}</span>
                                        @endif
                                    </li>
                                    @if ($card['audit']['run'])
                                        <li>
                                            <span class="seo-pills">
                                                <span class="seo-pill seo-pill-critical">{{ $card['audit']['severity']['critical'] }} {{ __('critiques') }}</span>
                                                <span class="seo-pill seo-pill-warning">{{ $card['audit']['severity']['warning'] }} {{ __('avertissements') }}</span>
                                            </span>
                                            <span class="seo-muted" style="white-space: nowrap;">{{ $card['audit']['new'] }} {{ __('nouveaux') }} · {{ $card['audit']['fixed'] }} {{ __('corrigés') }}</span>
                                        </li>
                                    @endif
                                </ul>
                            </div>

                            {{-- Sources de données --}}
                            <div>
                                <div class="seo-block-title">{{ __('Données importées') }}</div>
                                <ul class="seo-list">
                                    @forelse ($card['sources'] as $source)
                                        <li>
                                            <span>{{ $source['label'] }}</span>
                                            @if ($source['configured'] === false)
                                                <span class="seo-muted">{{ __('non configuré') }}</span>
                                            @elseif ($source['range'])
                                                <span class="{{ $source['stale'] ? 'seo-bad' : 'seo-muted' }}" style="white-space: nowrap;" @if ($source['stale']) title="{{ __('Aucune donnée récente : vérifier le journal de l\'extension.') }}" @endif>{{ $source['range'] }}</span>
                                            @else
                                                <span class="seo-muted">{{ __('en attente') }}</span>
                                            @endif
                                        </li>
                                    @empty
                                        <li class="seo-muted">{{ __('État des sources indisponible.') }}</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
