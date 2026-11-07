@include('filament.commercial.styles')
@php
    $f = \App\Support\FolderFinanceSummary::for($folder);
    $yen = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' ¥';
    $cost = function (array $b) use ($yen) {
        return match ($b['state']) {
            'none' => __('Aucune prestation'),
            'unknown' => __('Non renseigné'),
            'partial' => $yen($b['cost']) . ' · ' . __('calcul incomplet (:n prix d’achat manquants)', ['n' => $b['missing_costs']]),
            default => $yen($b['cost']),
        };
    };
    $margin = fn (array $b) => $b['state'] === 'complete' ? $yen($b['margin']) : '—';
@endphp
<table class="tk-c-table" style="max-width:48rem">
    <thead><tr><th></th><th>{{ __('Prévisionnel (en préparation)') }}</th><th>{{ __('Commandé') }}</th></tr></thead>
    <tbody>
        <tr><td>{{ __('Prestations') }}</td><td>{{ $f['planned']['count'] }}</td><td>{{ $f['ordered']['count'] }}</td></tr>
        <tr><td>{{ __('Vente') }}</td><td>{{ $f['planned']['count'] ? $yen($f['planned']['sale']) : '—' }}</td><td>{{ $f['ordered']['count'] ? $yen($f['ordered']['sale']) : __('Aucune commande') }}</td></tr>
        <tr><td>{{ __('Coûts d’achat') }}</td><td>{{ $cost($f['planned']) }}</td><td>{{ $cost($f['ordered']) }}</td></tr>
        <tr><td>{{ __('Marge') }}</td><td>{{ $margin($f['planned']) }}</td><td>{{ $margin($f['ordered']) }}</td></tr>
    </tbody>
</table>
<div class="tk-c-muted" style="font-size:.8rem;margin-top:.5rem">
    {{ __('Frais de dossier : aucun pour les dossiers internes.') }}
    @if ($f['global_sale'] !== null && $f['global_sale'] > 0) {{ __('Total enregistré sur le dossier :') }} <strong>{{ $yen($f['global_sale']) }}</strong>@endif
</div>
