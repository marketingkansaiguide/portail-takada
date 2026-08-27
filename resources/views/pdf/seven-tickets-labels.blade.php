<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Étiquettes Seven Tickets') }}</title>
    <style>
        /* Configuration stricte de la page A4 et des marges d'impression */
        @page {
            size: A4 portrait;
            margin: 11mm 14mm;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #fff;
            color: #000;
        }

        /* Conteneur principal qui gère le saut de page toutes les 10 étiquettes */
        .page-container {
            display: flex;
            flex-wrap: wrap;
            align-content: flex-start;
            width: 182mm; /* 210mm - (14mm * 2) */
            height: 275mm; /* 297mm - (11mm * 2) */
            page-break-after: always;
        }

        /* Format strict de l'étiquette : 91mm x 55mm sans espace inter-étiquettes */
        .label {
            width: 91mm;
            height: 55mm;
            box-sizing: border-box;
            padding: 5mm 6mm; /* Espace intérieur pour que le texte ne touche pas les bords */
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 1.5mm;
            text-align: center;
            border: 0.25mm solid #cbd5e1;
            border-radius: 1mm;
        }

        .controls-header {
            background: #ffffff;
            border-bottom: 1px solid #cccccc;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .edit-notice {
            background-color: #f0f0f0;
            border: 1px solid #cccccc;
            color: #000000;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
        }

        /* Typographie des informations brutes */
        .info-line {
            font-size: 13px;
            line-height: 1.25;
            max-width: 100%;
            overflow-wrap: anywhere;
        }

        .folder-name { font-size: 10px; font-weight: 600; }
        .product-name { font-size: 16px; font-weight: bold; }
        .option-name { font-size: 13px; font-weight: 600; }
        .additional-info { font-size: 12px; font-style: italic; }
        .date-pax { font-size: 12px; font-weight: bold; }
        
        [contenteditable="true"] {
            border-radius: 2px;
            padding: 1px 2px;
            transition: background-color 0.2s ease;
        }

        [contenteditable="true"]:hover {
            outline: 1px dashed #000000;
            background-color: #f5f5f5;
            cursor: text;
        }

        [contenteditable="true"]:focus {
            outline: 1.5px solid #000000;
            background-color: #ffffff;
        }

        /* Masquer les boutons d'interface à l'impression */
        @media print {
            .no-print { display: none !important; }

            [contenteditable="true"]:hover,
            [contenteditable="true"]:focus {
                outline: none !important;
                background-color: transparent !important;
            }
        }
        
        .print-btn {
            background-color: #000000;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="controls-header no-print">
        <div class="edit-notice">
            <strong>{{ __('Texte modifiable :') }}</strong> {{ __('Vous pouvez cliquer directement sur les textes ci-dessous pour modifier vos étiquettes avant impression.') }}
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <span style="font-size: 13px; color: #333333; font-weight: 500;">
                {{ $records->count() }} {{ __('étiquette(s) sélectionnée(s)') }} ({{ ceil($records->count() / 10) }} {{ __('page(s)') }})
            </span>
            <button onclick="window.print()" class="print-btn">🖨️ {{ __('Imprimer les étiquettes') }}</button>
        </div>
    </div>

    @php
        $labelsPerPage = 10;
        $chunks = $records->chunk($labelsPerPage);
    @endphp

    @foreach($chunks as $chunk)
        <div class="page-container">
            @foreach($chunk as $item)
                @php
                    // Extraction des options et informations complémentaires
                    $customVals = is_string($item->custom_values) ? json_decode($item->custom_values, true) : ($item->custom_values ?? []);
                    $customVals = is_array($customVals) ? $customVals : [];
                    
                    // Toutes les déclinaisons/options sélectionnées
                    $selectedOptions = is_string($item->selected_options)
                        ? json_decode($item->selected_options, true)
                        : ($item->selected_options ?? []);
                    $selectedOptions = is_array($selectedOptions) ? $selectedOptions : [];
                    $optionNames = [];

                    foreach ($selectedOptions as $selectedOption) {
                        $optionId = is_array($selectedOption)
                            ? ($selectedOption['product_option_id'] ?? null)
                            : $selectedOption;

                        if (!$optionId) {
                            continue;
                        }

                        $option = \App\Models\ProductOption::find($optionId);
                        if (!$option) {
                            continue;
                        }

                        $optionName = $option->name;
                        if ($option->billing_type === 'manual' && is_array($selectedOption)) {
                            $optionQuantity = (int) ($selectedOption['quantity'] ?? 1);
                            if ($optionQuantity > 1) {
                                $optionName .= ' × ' . $optionQuantity;
                            }
                        }

                        $optionNames[] = $optionName;
                    }

                    // Compatibilité avec les anciennes prestations qui ne
                    // stockaient qu'une seule option dans custom_values.
                    if (empty($optionNames)) {
                        $legacyOptionId = $customVals['option_id'] ?? $item->product_option_id ?? null;
                        if ($legacyOptionId) {
                            $legacyOption = \App\Models\ProductOption::find($legacyOptionId);
                            if ($legacyOption) {
                                $optionNames[] = $legacyOption->name;
                            }
                        }
                    }

                    // Information complémentaire (ex: remarques, infos spécifiques de la résa)
                    $additionalInfo = $customVals['additional_info'] ?? $customVals['remarks'] ?? '';
                    if (is_array($additionalInfo)) {
                        $additionalInfo = implode(' - ', array_filter($additionalInfo, fn ($value) => is_scalar($value)));
                    }
                    
                    // Nombre de passagers propre à cette prestation
                    $pax = $item->getPaxBreakdown();
                    $adults = $pax['adults'];
                    $children = $pax['children'];
                    $teenagers = $pax['teenagers'];
                @endphp

                <div class="label">
                    <div class="info-line folder-name" contenteditable="true">{{ $item->folder->folder_name ?? 'Dossier Inconnu' }}</div>
                    <div class="info-line product-name" contenteditable="true">{{ $item->product->name ?? 'Prestation Inconnue' }}</div>
                    
                    @foreach($optionNames as $optionName)
                        <div class="info-line option-name" contenteditable="true">{{ $optionName }}</div>
                    @endforeach
                    
                    @if($additionalInfo)
                        <div class="info-line additional-info" contenteditable="true">{{ $additionalInfo }}</div>
                    @endif
                    
                    <div class="info-line date-pax" contenteditable="true">
                    {{ __('À partir de') }} {{ $item->service_date ? $item->service_date->format('d/m/Y') : __('date non définie') }}
                    </div>

                    <div class="info-line date-pax" contenteditable="true">
                        @if($pax['has_age_breakdown'])
                            Adultes : {{ $adults }}@if($teenagers > 0) | Adolescents : {{ $teenagers }}@endif | Enfants : {{ $children }}
                        @else
                            {{ $pax['total'] }} PAX
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
    
</body>
</html>
