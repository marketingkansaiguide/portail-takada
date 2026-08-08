<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Étiquettes Seven Tickets</title>
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
            padding: 6mm; /* Espace intérieur pour que le texte ne touche pas les bords */
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            /* border: 1px dashed #ccc; Décommente cette ligne pour tester le cadrage sur papier blanc */
        }

        /* Typographie des informations brutes */
        .info-line {
            font-size: 13px;
            line-height: 1.4;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .folder-name { font-size: 15px; font-weight: bold; margin-bottom: 5px; }
        .product-name { font-size: 14px; font-weight: bold; }
        .date-pax { font-size: 12px; font-weight: bold; margin-top: 4px; }
        
        /* Masquer les boutons d'interface à l'impression */
        @media print {
            .no-print { display: none !important; }
        }
        
        .print-btn {
            position: fixed; top: 10px; right: 10px;
            padding: 10px 20px; background-color: #0ea5e9; color: white;
            border: none; border-radius: 5px; cursor: pointer; font-weight: bold;
        }
    </style>
</head>
<body>
    <button class="no-print print-btn" onclick="window.print()">🖨️ Imprimer les étiquettes</button>

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
                    
                    // Déclinaison (Option)
                    $optionName = '';
                    $optionId = $customVals['option_id'] ?? $item->product_option_id ?? null;
                    if ($optionId) {
                        $opt = \App\Models\ProductOption::find($optionId);
                        if ($opt) $optionName = $opt->name;
                    }

                    // Information complémentaire (ex: remarques, infos spécifiques de la résa)
                    $additionalInfo = $customVals['additional_info'] ?? $customVals['remarks'] ?? '';
                    
                    // Nombres de passagers
                    $adults = $item->folder->pax_adults ?? 1;
                    $children = $item->folder->pax_children ?? 0;
                @endphp

                <div class="label">
                    <div class="info-line folder-name">{{ $item->folder->folder_name ?? 'Dossier Inconnu' }}</div>
                    <div class="info-line product-name">{{ $item->product->name ?? 'Prestation Inconnue' }}</div>
                    
                    @if($optionName)
                        <div class="info-line">{{ $optionName }}</div>
                    @endif
                    
                    @if($additionalInfo)
                        <div class="info-line"><i>{{ Str::limit($additionalInfo, 40) }}</i></div>
                    @endif
                    
                    <div class="info-line date-pax">
                        {{ $item->service_date ? $item->service_date->format('d/m/Y') : 'Date non définie' }} 
                        | Adulte(s): {{ $adults }} 
                        @if($children > 0) | Enfant(s): {{ $children }} @endif
                    </div>
                </div>
            @endphp
            @endforeach
        </div>
    @endforeach
    
    <script>
        // Lance automatiquement la fenêtre d'impression à l'ouverture du fichier
        window.onload = function() {
            setTimeout(() => { window.print(); }, 500);
        };
    </script>
</body>
</html>