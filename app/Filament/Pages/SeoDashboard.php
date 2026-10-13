<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SeoSites\SeoSiteResource;
use App\Models\SeoSite;
use App\Services\SeoSuiviApi;
use App\Support\AdminAccess;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Tableau de bord SEO multi-sites : agrège l'API lecture seule de l'extension
 * « Takada SEO Suivi » installée sur chaque site WordPress (voir SeoSuiviApi).
 */
class SeoDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass-circle';
    protected static string|UnitEnum|null $navigationGroup = 'SEO';
    protected static ?string $navigationLabel = 'Tableau de bord SEO';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = null;
    protected static ?string $slug = 'seo';

    protected string $view = 'filament.pages.seo-dashboard';

    public string $preset = 'last_28';

    public string $compare = 'previous';

    protected bool $forceFresh = false;

    public static function canAccess(): bool
    {
        return AdminAccess::allows('seo.dashboard');
    }

    public function getTitle(): string
    {
        return __('Tableau de bord SEO');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label(__('Actualiser'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->forceFresh = true),
            Action::make('sites')
                ->label(__('Gérer les sites'))
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(SeoSiteResource::getUrl('index'))
                ->visible(fn (): bool => SeoSiteResource::canViewAny()),
        ];
    }

    protected function getViewData(): array
    {
        $sites = SeoSite::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $results = app(SeoSuiviApi::class)->dashboard($sites, $this->preset, $this->compare, $this->forceFresh);
        $this->forceFresh = false;

        $cards = array_map(fn (array $r) => $this->card($r), $results);

        return [
            'presets' => SeoSuiviApi::PRESETS,
            'compares' => SeoSuiviApi::COMPARES,
            'cards' => $cards,
            'totals' => $this->totals($results),
            'periodLabel' => $this->periodLabel($results),
        ];
    }

    /** @param array{site: SeoSite, info: ?array, summary: ?array, error: ?string, fetched_at: ?string} $r */
    private function card(array $r): array
    {
        $site = $r['site'];
        $summary = $r['summary'] ?? [];
        $metrics = $this->ok($summary['kpis'] ?? null)['metrics'] ?? [];
        $leads = $this->ok($summary['leads']['totals'] ?? null);

        $kpis = [
            $this->kpi('Clics Google', $metrics['clicks'] ?? null, 'count'),
            $this->kpi('Impressions', $metrics['impressions'] ?? null, 'count'),
            $this->kpi('CTR', $metrics['ctr'] ?? null, 'ratio'),
            $this->kpi('Position moyenne', $metrics['position'] ?? null, 'position'),
            $this->kpi('Sessions (GA4)', $metrics['sessions'] ?? null, 'count'),
            $this->kpi('Conversions', $metrics['key_events'] ?? null, 'count'),
            $this->kpi('Devis', $leads === [] ? null : ['a' => $leads['a'] ?? null, 'b' => $leads['b'] ?? null], 'count'),
            $this->kpi('Clics Bing', $metrics['bing_clicks'] ?? null, 'count'),
        ];

        $alerts = $this->ok($summary['alerts'] ?? null);
        $alertCounts = ['critical' => 0, 'warning' => 0, 'info' => 0];
        foreach ($alerts as $alert) {
            $level = $alert['level'] ?? 'info';
            $alertCounts[$level] = ($alertCounts[$level] ?? 0) + 1;
        }

        $monitorNow = $this->ok($summary['monitor']['status_now'] ?? null);
        $availability = $this->ok($summary['monitor']['availability_7d'] ?? null);
        $pcts = array_values(array_filter(array_column($availability, 'pct'), fn ($v) => $v !== null));

        $audit = $this->ok($summary['audit']['issues'] ?? null);
        $auditBySeverity = ['critical' => 0, 'warning' => 0, 'info' => 0];
        foreach ($audit['codes'] ?? [] as $code) {
            $severity = $code['severity'] ?? 'info';
            $auditBySeverity[$severity] = ($auditBySeverity[$severity] ?? 0) + (int) ($code['count'] ?? 0);
        }

        $actionCounts = $this->ok($summary['actions']['counts'] ?? null);
        $series = $this->ok($summary['timeseries_clicks_week'] ?? null);
        $movers = $this->ok($summary['movers_pages'] ?? null);

        return [
            'name' => $site->name,
            'url' => $site->baseUrl(),
            'host' => $site->host(),
            'admin_url' => $site->wpAdminUrl(),
            'error' => $r['error'],
            'fetched_at' => $r['fetched_at'] ? Carbon::parse($r['fetched_at'])->format('d/m H:i') : null,
            'plugin_version' => $r['info']['plugin_version'] ?? null,
            'period' => isset($summary['periods']['label_a'])
                ? $summary['periods']['label_a'].' vs '.($summary['periods']['label_b'] ?? '')
                : null,
            'kpis' => $kpis,
            'spark' => [
                'a' => array_map('floatval', $series['a'] ?? []),
                'b' => array_map('floatval', $series['b'] ?? []),
            ],
            'alerts' => [
                'counts' => $alertCounts,
                'items' => array_slice($alerts, 0, 5),
            ],
            'monitor' => [
                'total' => count($monitorNow),
                'down' => count(array_filter($monitorNow, fn ($m) => ($m['ok'] ?? null) === false)),
                'availability' => $pcts === [] ? null : round(min($pcts), 2),
            ],
            'audit' => [
                'run' => (int) ($audit['run_id'] ?? 0) > 0,
                'pages' => (int) ($audit['pages'] ?? 0),
                'severity' => $auditBySeverity,
                'new' => (int) ($audit['new_total'] ?? 0),
                'fixed' => (int) ($audit['fixed_total'] ?? 0),
                'finished_at' => $this->date($audit['finished_at'] ?? null),
            ],
            'actions' => [
                'todo' => (int) ($actionCounts['todo'] ?? 0),
                'done' => (int) ($actionCounts['done'] ?? 0),
                'top' => array_slice($this->ok($summary['actions']['top'] ?? null), 0, 3),
            ],
            'movers' => [
                'up' => array_slice($movers['clicks_up'] ?? [], 0, 3),
                'down' => array_slice($movers['clicks_down'] ?? [], 0, 3),
            ],
            'sources' => $this->sources($r['info']['sources'] ?? []),
        ];
    }

    /** @return array<int, array{label: string, configured: ?bool, range: ?string, stale: bool}> */
    private function sources(array $sources): array
    {
        $labels = [
            'gsc' => 'Search Console',
            'ga4' => 'GA4',
            'bing' => 'Bing',
            'inspection' => 'Indexation',
            'pagespeed' => 'PageSpeed',
            'rank' => 'Positions',
        ];

        $out = [];
        foreach ($sources as $source) {
            $key = $source['source'] ?? '';
            if (! isset($labels[$key])) {
                continue;
            }
            $max = $source['coverage_max'] ?? null;
            $out[] = [
                'label' => $labels[$key],
                'configured' => $source['configured'] ?? null,
                'range' => ($source['coverage_min'] ?? null) && $max
                    ? $this->date($source['coverage_min']).' → '.$this->date($max)
                    : null,
                // Search Console et GA4 sont importés chaque jour avec 2 à 3 jours de décalage.
                'stale' => in_array($key, ['gsc', 'ga4', 'bing'], true) && $max !== null
                    && Carbon::parse($max)->lt(now()->subDays(6)),
            ];
        }

        return $out;
    }

    private function totals(array $results): array
    {
        $sum = ['clicks' => ['a' => 0, 'b' => 0], 'impressions' => ['a' => 0, 'b' => 0], 'sessions' => ['a' => 0, 'b' => 0], 'key_events' => ['a' => 0, 'b' => 0], 'leads' => ['a' => 0, 'b' => 0]];
        $ok = 0;

        foreach ($results as $r) {
            $metrics = $this->ok($r['summary']['kpis'] ?? null)['metrics'] ?? null;
            if (! $metrics) {
                continue;
            }
            $ok++;
            foreach (['clicks', 'impressions', 'sessions', 'key_events'] as $m) {
                $sum[$m]['a'] += (float) ($metrics[$m]['a'] ?? 0);
                $sum[$m]['b'] += (float) ($metrics[$m]['b'] ?? 0);
            }
            $leads = $this->ok($r['summary']['leads']['totals'] ?? null);
            $sum['leads']['a'] += (float) ($leads['a'] ?? 0);
            $sum['leads']['b'] += (float) ($leads['b'] ?? 0);
        }

        $ctr = fn (string $p) => $sum['impressions'][$p] > 0 ? $sum['clicks'][$p] / $sum['impressions'][$p] : null;

        return [
            'sites' => count($results),
            'ok' => $ok,
            'kpis' => [
                $this->kpi('Clics Google', $sum['clicks'], 'count'),
                $this->kpi('Impressions', $sum['impressions'], 'count'),
                $this->kpi('CTR', ['a' => $ctr('a'), 'b' => $ctr('b')], 'ratio'),
                $this->kpi('Sessions (GA4)', $sum['sessions'], 'count'),
                $this->kpi('Conversions', $sum['key_events'], 'count'),
                $this->kpi('Devis', $sum['leads'], 'count'),
            ],
        ];
    }

    private function periodLabel(array $results): ?string
    {
        foreach ($results as $r) {
            $p = $r['summary']['periods'] ?? null;
            if (isset($p['label_a'], $p['label_b'])) {
                return ucfirst($p['label_a']).', comparé à la période '.$p['label_b'];
            }
        }

        return null;
    }

    /**
     * Indicateur affichable : valeur de la période A et variation par rapport à B.
     * $kind : count (variation en %), ratio (CTR, variation en points), position (plus bas = mieux).
     *
     * @return array{label: string, value: string, delta: ?string, tone: string, previous: ?string}
     */
    private function kpi(string $label, ?array $metric, string $kind): array
    {
        $a = $metric['a'] ?? null;
        $b = $metric['b'] ?? null;

        $format = fn ($v) => match ($kind) {
            'ratio' => $this->number($v * 100, 2).' %',
            'position' => $this->number($v, 1),
            default => $this->number($v, 0),
        };

        $delta = null;
        $tone = 'neutral';

        if ($a !== null && $b !== null) {
            if ($kind === 'count' && (float) $b != 0.0) {
                $pct = ($a - $b) / $b * 100;
                $delta = ($pct >= 0 ? '+' : '').$this->number($pct, 1).' %';
                $tone = abs($pct) < 0.5 ? 'neutral' : ($pct > 0 ? 'good' : 'bad');
            } elseif ($kind === 'ratio') {
                $pts = ($a - $b) * 100;
                $delta = ($pts >= 0 ? '+' : '').$this->number($pts, 2).' pt';
                $tone = abs($pts) < 0.01 ? 'neutral' : ($pts > 0 ? 'good' : 'bad');
            } elseif ($kind === 'position') {
                $diff = $a - $b;
                $delta = ($diff >= 0 ? '+' : '').$this->number($diff, 1);
                $tone = abs($diff) < 0.05 ? 'neutral' : ($diff < 0 ? 'good' : 'bad');
            }
        }

        return [
            'label' => $label,
            'value' => $a === null ? '—' : $format((float) $a),
            'previous' => $b === null ? null : $format((float) $b),
            'delta' => $delta,
            'tone' => $tone,
        ];
    }

    /** Ignore les blocs renvoyés en erreur par l'extension ({"error": "..."}). */
    private function ok(mixed $block): array
    {
        return is_array($block) && ! isset($block['error']) ? $block : [];
    }

    private function number(float|int|null $v, int $decimals): string
    {
        return $v === null ? '—' : number_format((float) $v, $decimals, ',', ' ');
    }

    private function date(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }
}
