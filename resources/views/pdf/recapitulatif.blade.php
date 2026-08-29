<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Récapitulatif') }} - {{ $folder->reference }}</title>
    <style>
        @page { margin: 18px 24px 22px; }

        /* Dompdf n’embarque pas Arial ni les glyphes japonais : cette police Unicode est incluse dans le PDF. */
        @font-face {
            font-family: 'M PLUS 1p';
            font-style: normal;
            font-weight: 400;
            src: url('{{ public_path('fonts/MPLUS1p-Regular.ttf') }}') format('truetype');
        }

        @font-face {
            font-family: 'M PLUS 1p';
            font-style: normal;
            font-weight: 700;
            src: url('{{ public_path('fonts/MPLUS1p-Bold.ttf') }}') format('truetype');
        }

        /* M PLUS a une hauteur de glyphe supérieure à Arial : ces valeurs préservent le format compact d’origine. */
        body { margin: 0; padding: 0; font-family: 'M PLUS 1p', 'DejaVu Sans', sans-serif; color: #1e293b; font-size: 8.8pt; line-height: 1.12; }
        .header { width: 100%; margin-bottom: 14px; }
        .title { font-size: 19pt; font-weight: bold; color: #1e3a8a; text-transform: uppercase; text-align: right; }
        .brand-logo { display: block; width: 180px; height: auto; }
        .divider { height: 2px; background-color: #e2e8f0; margin: 10px 0 16px 0; }
        .info-table { width: 100%; margin-bottom: 18px; }
        .info-table td { width: 50%; vertical-align: top; }
        .info-box h2 { font-size: 10pt; color: #1e3a8a; text-transform: uppercase; border-bottom: 1px solid #cbd5e1; padding-bottom: 2px; margin-top: 0; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .items-table th { background-color: #1e3a8a; color: #ffffff; padding: 4px 5px; font-size: 8pt; line-height: 1.1; text-transform: uppercase; text-align: left; vertical-align: middle; }
        .items-table td { padding: 4px 5px; border: 1px solid #e2e8f0; font-size: 8.4pt; line-height: 1.1; vertical-align: middle; }
        .items-table .base-tariff-label { margin-left: 4px; white-space: nowrap; }
        .items-table .option-row td { padding-top: 2px; padding-bottom: 2px; color: #64748b; font-size: 7.4pt; line-height: 1.05; vertical-align: middle; }
        .items-table .option-description { padding-left: 14px; }
        .items-table .option-total { color: #475569; }
        .items-table .item-subtotal td { padding-top: 2px; padding-bottom: 2px; color: #1e3a8a; font-size: 7.6pt; line-height: 1.05; }
        .items-table .transport-routes { margin-top: 3px; color: #64748b; font-size: 7.2pt; line-height: 1.1; }
        .items-table .transport-route { margin-top: 1px; }
        .items-table .transport-route-journey { color: #334155; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals-table { width: 40%; margin-left: 60%; border-collapse: collapse; }
        .totals-table td { padding: 3px 4px; border-bottom: 1px solid #e2e8f0; line-height: 1.1; vertical-align: middle; }
        .total-row td { font-size: 10.5pt; font-weight: bold; color: #1e3a8a; background-color: #eff6ff; border: 1px solid #bfdbfe; padding: 4px 5px; line-height: 1.1; vertical-align: middle; }
        .footer { margin-top: 20px; text-align: center; font-size: 7.5pt; color: #94a3b8; }
    </style>
</head>
<body>

    @php
        $configuredLogo = \App\Models\Setting::query()->first()?->site_logo_path;
        $logoPath = $configuredLogo && \Illuminate\Support\Facades\Storage::disk('public')->exists($configuredLogo)
            ? \Illuminate\Support\Facades\Storage::disk('public')->path($configuredLogo)
            : public_path('images/logo.svg');
    @endphp

    <table class="header" style="width: 100%;">
        <tr>
            <td>
                <img class="brand-logo" src="{{ $logoPath }}" alt="Takada Travel">
            </td>
            <td class="text-right">
                <div class="title">{{ __('Pré-Facture') }}</div>
                <div style="font-weight: bold; margin-top: 5px;">{{ __('Réf :') }} {{ $folder->reference }}</div>
                <div style="color: #64748b; font-size: 9pt;">{{ __('Émis le :') }} {{ $dateEmit }}</div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td>
                <div class="info-box" style="padding-right: 15px;">
                    <h2>{{ __('Agence Cliente') }}</h2>
                    <div><strong>{{ __('Nom :') }}</strong> {{ $agency->name }}</div>
                    <div><strong>{{ __('Contact :') }}</strong> {{ $agency->email ?? '---' }}</div>
                    <div><strong>{{ __('Téléphone :') }}</strong> {{ $agency->phone ?? '---' }}</div>
                    <div style="margin-top: 5px;"><strong>{{ __('Adresse Facturation :') }}</strong></div>
                    <div style="color: #475569; font-style: italic; padding-left: 5px;">
                        {!! nl2br(e($agency->address)) !!}
                    </div>
                </div>
            </td>
            <td>
                <div class="info-box" style="padding-left: 15px;">
                    <h2>{{ __('Détails du Dossier') }}</h2>
                    <div><strong>{{ __('Nom du dossier :') }}</strong> {{ $folder->folder_name }}</div>
                    <div><strong>{{ __('Pax Leader :') }}</strong> {{ $folder->lead_traveler_name }}</div>
                    <div><strong>{{ __('Nombre de Pax :') }}</strong> {{ $totalPax }} Pax - {{ $folder->pax_adults }} {{ __('Adulte(s)') }}, {{ $folder->pax_children }} {{ __('Enfant(s)') }}</div>
                    <div><strong>{{ __('Dates du Séjour :') }}</strong> {{ __('Du') }} {{ $folder->start_date?->format('d/m/Y') }} {{ __('au') }} {{ $folder->end_date?->format('d/m/Y') }}</div>
                    <div><strong>{{ __('Mode d’envoi billetterie :') }}</strong> {{ ucfirst($folder->ticket_dispatch_method) }} @if($folder->ticket_dispatch_other) ({{ $folder->ticket_dispatch_other }}) @endif</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>{{ __('Détail de la prestation') }}</th>
                <th class="text-center" style="width: 15%;">Date</th>
                <th class="text-center" style="width: 10%;">{{ __('Qté') }}</th>
                <th class="text-right" style="width: 15%;">{{ __('Prix Unitaire') }}</th>
                <th class="text-right" style="width: 15%;">{{ __('Prix Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                @php
                    $optionsTotal = 0;
                    $optionsList = [];

                    if (!empty($item->selected_options) && is_array($item->selected_options)) {
                        foreach ($item->selected_options as $optData) {
                            if (!empty($optData['product_option_id'])) {
                                $optModel = \App\Models\ProductOption::find($optData['product_option_id']);
                                if ($optModel) {
                                    $optQty = (int) ($optData['quantity'] ?? 1);
                                    $modifier = (float) $optModel->price_modifier;

                                    if ($optModel->billing_type === 'per_pax') {
                                        $optTotal = $modifier * $item->quantity;
                                        $calcLabel = '¥' . number_format($modifier) . ' / pax';
                                        $displayQty = $item->quantity;
                                    } elseif ($optModel->billing_type === 'manual') {
                                        $optTotal = $modifier * $optQty;
                                        $calcLabel = '¥' . number_format($modifier) . ' x ' . $optQty;
                                        $displayQty = $optQty;
                                    } else {
                                        $optTotal = $modifier;
                                        $calcLabel = 'Forfait fixe';
                                        $displayQty = 1;
                                    }

                                    $optionsTotal += $optTotal;
                                    $optionsList[] = [
                                        'name' => $optModel->name,
                                        'display_qty' => $displayQty,
                                        'unit_price' => $modifier,
                                        'calc_label' => $calcLabel,
                                        'total' => $optTotal,
                                    ];
                                }
                            }
                        }
                    } elseif ($item->productOption) {
                        $optModel = $item->productOption;
                        $modifier = (float) $optModel->price_modifier;
                        $optTotal = $modifier;
                        $optionsTotal += $optTotal;
                        $optionsList[] = [
                            'name' => $optModel->name,
                            'display_qty' => 1,
                            'unit_price' => $modifier,
                            'calc_label' => __('Option par défaut'),
                            'total' => $optTotal,
                        ];
                    }

                    $hasOptions = count($optionsList) > 0;
                    $baseTotal = $item->total_price - $optionsTotal;
                    $baseUnitPrice = $item->quantity > 0 ? ($baseTotal / $item->quantity) : 0;

                    $transportRoutes = $item->product?->product_type === 'transport'
                        ? collect($item->custom_values['transport_routes'] ?? [])
                            ->filter(fn ($route): bool => is_array($route))
                            ->map(function (array $route): ?array {
                                $departure = trim((string) ($route['departure_station'] ?? ''));
                                $arrival = trim((string) ($route['arrival_station'] ?? ''));

                                if ($departure === '' && $arrival === '') {
                                    return null;
                                }

                                $date = ! empty($route['departure_date'])
                                    ? \Carbon\Carbon::parse($route['departure_date'])->format('d/m/Y')
                                    : null;
                                $departureLabel = trim(implode(' ', array_filter([
                                    $route['departure_time'] ?? null,
                                    $departure,
                                ])));
                                $arrivalLabel = trim(implode(' ', array_filter([
                                    $route['arrival_time'] ?? null,
                                    $arrival,
                                ])));

            return [
                'date' => $date,
                'journey' => trim($departureLabel . ' → ' . $arrivalLabel),
                'pax' => filled($route['pax_count'] ?? null) ? (int) $route['pax_count'] : null,
            ];
                            })
                            ->filter()
                            ->values()
                        : collect();
                @endphp

                <tr style="background-color: #ffffff;">
                    <td>
                        <strong>{{ $item->product?->name }}</strong>

                        @if($hasOptions)
                            <span class="base-tariff-label" style="font-size: 8pt; color: #64748b;">({{ __('Tarif de base') }})</span>
                        @endif

                        @if($transportRoutes->isNotEmpty())
                            <div class="transport-routes">
                                @foreach($transportRoutes as $route)
                                    <div class="transport-route">
                                        @if($route['date'])
                                            {{ $route['date'] }} ·
                                        @endif
                                        <span class="transport-route-journey">{{ $route['journey'] }}</span>
                                        @if($route['pax'])
                                            · {{ $route['pax'] }} pax
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="text-center">{{ $item->service_date?->format('d/m/Y') }}</td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-right">
                        ¥{{ number_format($hasOptions ? $baseUnitPrice : $item->unit_price) }}
                    </td>
                    <td class="text-right"><strong>¥{{ number_format($hasOptions ? $baseTotal : $item->total_price) }}</strong></td>
                </tr>

                @if($hasOptions)
                    @foreach($optionsList as $opt)
                        <tr class="option-row" style="background-color: #f8fafc;">
                            <td class="option-description">{{ $opt['name'] }}</td>
                            <td class="text-center" style="color: #94a3b8;">—</td>
                            <td class="text-center">{{ $opt['display_qty'] }}</td>
                            <td class="text-right">
                                @if((float) $opt['total'] === 0.0)
                                    —
                                @else
                                    ¥{{ number_format($opt['unit_price']) }}
                                @endif
                            </td>
                            <td class="text-right option-total">
                                @if((float) $opt['total'] !== 0.0)
                                    +¥{{ number_format($opt['total']) }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    <tr class="item-subtotal" style="background-color: #f1f5f9; border-bottom: 1px solid #cbd5e1;">
                        <td colspan="4" class="text-right" style="font-weight: bold;">{{ __('Sous-total') }} :</td>
                        <td class="text-right" style="font-weight: bold;">¥{{ number_format($item->total_price) }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>{{ __('Sous-total Prestations :') }}</td>
            <td class="text-right">¥{{ number_format($itemsTotal) }}</td>
        </tr>
        <tr>
            <td>{{ __('Frais de dossier appliqués :') }}</td>
            <td class="text-right">¥{{ number_format($folder->folder_fee) }}</td>
        </tr>
        <tr class="total-row">
            <td>{{ __('Montant Total :') }}</td>
            <td class="text-right">¥{{ number_format($grandTotal) }}</td>
        </tr>
    </table>

    <div class="divider" style="margin-top: 30px; margin-bottom: 15px;"></div>

    <div class="footer">
        <p>{{ __('Ce document est un récapitulatif estimatif de prestations (pré-facture) et ne fait pas office de facture légale acquittée.') }}</p>
        <p><strong>TAKADA PORTAL</strong> — {{ __('Service de réservation B2B Japon') }}</p>
    </div>

</body>
</html>
