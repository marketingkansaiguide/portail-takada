<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Récapitulatif') }} - {{ $folder->reference }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1e293b; font-size: 10pt; line-height: 1.4; }
        .header { width: 100%; margin-bottom: 20px; }
        .title { font-size: 22pt; font-weight: bold; color: #1e3a8a; text-transform: uppercase; text-align: right; }
        .company { font-size: 18pt; font-weight: bold; color: #0f172a; text-transform: uppercase; }
        .divider { height: 2px; background-color: #e2e8f0; margin: 15px 0 25px 0; }
        .info-table { width: 100%; margin-bottom: 30px; }
        .info-table td { width: 50%; vertical-align: top; }
        .info-box h2 { font-size: 11pt; color: #1e3a8a; text-transform: uppercase; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; margin-top: 0; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th { background-color: #1e3a8a; color: #ffffff; padding: 8px; font-size: 9pt; text-transform: uppercase; text-align: left; }
        .items-table td { padding: 8px; border: 1px solid #e2e8f0; font-size: 9.5pt; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals-table { width: 40%; margin-left: 60%; border-collapse: collapse; }
        .totals-table td { padding: 6px; border-bottom: 1px solid #e2e8f0; }
        .total-row td { font-size: 12pt; font-weight: bold; color: #1e3a8a; background-color: #eff6ff; border: 1px solid #bfdbfe; padding: 8px; }
        .footer { margin-top: 40px; text-align: center; font-size: 8.5pt; color: #94a3b8; }
    </style>
</head>
<body>

    <table class="header" style="width: 100%;">
        <tr>
            <td>
                <div class="company">TAKADA PORTAL</div>
                <div style="color: #64748b; font-size: 9pt;">{{ __('Réservation B2B Japon') }}</div>
            </td>
            <td class="text-right">
                <div class="title">{{ __('Pré-Facture') }}</div>
                <div style="font-weight: bold; margin-top: 5px;">{{ __('Réf :') }} {{ $folder->reference }}</div>
                <div style="color: #64748b; font-size: 9pt;">{{ __('Émis le :') }} {{ $dateEmit }}</div>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

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
                @endphp

                @if(!$hasOptions)
                    <!-- Prestation simple sans option -->
                    <tr style="background-color: #ffffff;">
                        <td><strong>{{ $item->product?->name }}</strong></td>
                        <td class="text-center">{{ $item->service_date?->format('d/m/Y') }}</td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-right">¥{{ number_format($item->unit_price) }}</td>
                        <td class="text-right"><strong>¥{{ number_format($item->total_price) }}</strong></td>
                    </tr>
                @else
                    <!-- Prestation AVEC options : Décomposition explicite (Base + Options) -->
                    <tr style="background-color: #ffffff;">
                        <td>
                            <strong>{{ $item->product?->name }}</strong>
                            <br><span style="font-size: 8pt; color: #64748b;">({{ __('Tarif de base') }})</span>
                        </td>
                        <td class="text-center">{{ $item->service_date?->format('d/m/Y') }}</td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-right">¥{{ number_format($baseUnitPrice) }}</td>
                        <td class="text-right">¥{{ number_format($baseTotal) }}</td>
                    </tr>

                    @foreach($optionsList as $opt)
                        <tr style="background-color: #f8fafc; color: #475569; font-size: 8.5pt;">
                            <td style="padding-left: 20px;">
                                <span style="color: #94a3b8;">-</span> <em>{{ __('Option :') }}</em> <strong>{{ $opt['name'] }}</strong>
                                <span style="color: #64748b; font-size: 8pt;">({{ $opt['calc_label'] }})</span>
                            </td>
                            <td class="text-center" style="color: #94a3b8;">---</td>
                            <td class="text-center">{{ $opt['display_qty'] }}</td>
                            <td class="text-right">¥{{ number_format($opt['unit_price']) }}</td>
                            <td class="text-right">+¥{{ number_format($opt['total']) }}</td>
                        </tr>
                    @endforeach

                    <!-- Sous-total explicite de l'activité -->
                    <tr style="background-color: #f1f5f9; border-bottom: 2px solid #cbd5e1; font-size: 9pt;">
                        <td colspan="4" class="text-right" style="padding: 6px 8px; font-weight: bold; color: #1e3a8a;">
                            {{ __('Sous-total') }} {{ $item->product?->name }} :
                        </td>
                        <td class="text-right" style="padding: 6px 8px; font-weight: bold; color: #1e3a8a;">
                            ¥{{ number_format($item->total_price) }}
                        </td>
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
