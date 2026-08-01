<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>Plaquette d'étiquettes Check-ins</title>
    <style>
        /* Format A4 Paysage avec marges exactes */
        @page {
            size: A4 landscape;
            margin: 10.5mm 23.5mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', 'Arial', 'Hiragino Kaku Gothic Pro', 'Meiryo', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            color: #000000;
        }

        /* Barre d'actions supérieure (Masquée à l'impression) */
        .controls-header {
            background: #ffffff;
            border-bottom: 1px solid #cccccc;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .btn-print {
            background-color: #000000;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-print:hover {
            background-color: #333333;
        }

        .btn-back {
            color: #000000;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .edit-notice {
            background-color: #f0f0f0;
            border: 1px solid #cccccc;
            color: #000000;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
        }

        /* Conteneur d'impression */
        .print-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            padding-bottom: 30px;
        }

        /* Feuille A4 Paysage (250mm x 189mm utilisables) */
        .page-sheet {
            width: 250mm;
            height: 189mm;
            background: #ffffff;
            display: grid;
            grid-template-columns: 120mm 120mm;
            grid-template-rows: repeat(3, 63mm);
            column-gap: 10mm;
            row-gap: 0;
            page-break-after: always;
            page-break-inside: avoid;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .page-sheet:last-child {
            page-break-after: avoid;
        }

        /* Carte Étiquette (120mm x 63mm) */
        .label-card {
            width: 120mm;
            height: 63mm;
            border: 1px dashed #cccccc;
            padding: 3.5mm 5mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: #ffffff;
            color: #000000;
        }

        /* 1. Nom de l'hôtel */
        .hotel-name {
            font-size: 17px;
            font-weight: bold;
            color: #000000;
            line-height: 1.2;
            margin-bottom: 3px;
            text-transform: uppercase;
            word-break: break-word;
        }

        /* 2. Adresse (Sans aucune troncature) */
        .address-text {
            font-size: 17px;
            color: #000000;
            line-height: 1.25;
            white-space: pre-line;
            word-break: break-word;
            margin-bottom: 3px;
        }

        /* 3. Nom Réservation Hôtel */
        .booking-name {
            font-size: 17px;
            font-weight: bold;
            color: #000000;
            line-height: 1.2;
            margin-bottom: 3px;
            word-break: break-word;
        }

        /* 4. Nom Pax Leader */
        .pax-name {
            font-size: 17px;
            font-weight: bold;
            color: #000000;
            line-height: 1.2;
            margin-bottom: 3px;
            word-break: break-word;
        }

        /* 5. Date de Check-in */
        .checkin-date {
            font-size: 17px;
            font-weight: bold;
            color: #000000;
            padding-top: 3px;
            border-top: 1px solid #000000;
            text-align: left;
        }

        /* Interaction d'édition à l'écran */
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

        /* Impression propre */
        @media print {
            body {
                background: white;
            }

            .no-print {
                display: none !important;
            }

            .print-container {
                padding: 0;
                gap: 0;
            }

            .page-sheet {
                box-shadow: none;
                margin: 0;
            }

            .label-card {
                border-style: solid;
                border-color: #cccccc;
            }

            [contenteditable="true"]:hover,
            [contenteditable="true"]:focus {
                outline: none !important;
                background-color: transparent !important;
            }
        }
    </style>
</head>
<body>

    <!-- Barre d'actions non imprimable -->
    <div class="controls-header no-print">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div class="edit-notice">
                <strong>Texte modifiable :</strong> Vous pouvez cliquer directement sur les textes ci-dessous pour modifier vos étiquettes avant impression.
            </div>
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <span style="font-size: 13px; color: #333333; font-weight: 500;">
                {{ $folders->count() }} étiquette(s) sélectionnée(s) ({{ ceil($folders->count() / 6) }} page(s))
            </span>
            <button onclick="window.print()" class="btn-print">
                Imprimer la plaquette
            </button>
        </div>
    </div>

    <!-- Conteneur d'impression -->
    <div class="print-container">
        @foreach($folders->chunk(6) as $chunk)
            <div class="page-sheet">
                @foreach($chunk as $folder)
                    <div class="label-card">
                        <div>
                            <!-- 1. Nom de l'hôtel -->
                            <div class="hotel-name" contenteditable="true">
                                {{ $folder->first_hotel_name }}
                            </div>

                            <!-- 2. Adresse -->
                            <div class="address-text" contenteditable="true">{{ $folder->dispatch_address }}</div>

                            <!-- 3. Nom Réservation Hôtel (masqué si vide) -->
                            @if(!empty($folder->hotel_booking_name))
                                <div class="pax-name" contenteditable="true">
                                    {{ $folder->hotel_booking_name }} 様
                                </div>
                            @endif

                            <!-- 4. Nom Pax Leader -->
                            <div class="pax-name" contenteditable="true">
                                {{ $folder->lead_traveler_name }} 様
                            </div>
                        </div>

                        <!-- 5. Date check-in -->
                        <div class="checkin-date" contenteditable="true">
                            {{ $folder->formatted_jp_checkin_date }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

</body>
</html>