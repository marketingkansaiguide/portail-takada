<div class="seo-kpi" @if ($kpi['previous']) title="{{ __('Période de comparaison :') }} {{ $kpi['previous'] }}" @endif>
    <div class="seo-kpi-label">{{ __($kpi['label']) }}</div>
    <div class="seo-kpi-value">{{ $kpi['value'] }}</div>
    @if ($kpi['delta'])
        <div class="seo-kpi-delta seo-{{ $kpi['tone'] }}">{{ $kpi['delta'] }}</div>
    @endif
</div>
