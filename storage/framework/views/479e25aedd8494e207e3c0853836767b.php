<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Étiquettes Billets de Train</title>
    <style>
        /* Format A4 Portrait selon tes dimensions exactes */
        @page {
            size: A4 portrait;
            margin-top: 18.5mm;
            margin-bottom: 18.5mm;
            margin-left: 19mm;
            margin-right: 19mm;
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

        .edit-notice {
            background-color: #f0f0f0;
            border: 1px solid #cccccc;
            color: #000000;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
        }

        /* Conteneur d'impression - Format Portrait */
        .print-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            padding-bottom: 30px;
        }

        /* Feuille A4 Portrait (172mm de largeur imprimable) */
        .page-sheet {
            width: 172mm; /* Largeur disponible */
            min-height: 260mm; /* Hauteur disponible approximative */
            background: #ffffff;
            /* La grille CSS gère parfaitement le placement mathématique sans espaces inline parasites */
            display: grid;
            grid-template-columns: repeat(4, 40mm);
            grid-auto-rows: 40mm;
            column-gap: 4mm; /* Écart horizontal demandé */
            row-gap: 4mm;    /* Écart vertical demandé */
            page-break-after: always;
            page-break-inside: avoid;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .page-sheet:last-child {
            page-break-after: avoid;
        }

        /* L'étiquette de 40x40mm (centrée mathématiquement sur les deux axes) */
        .label-card {
            width: 40mm;
            height: 40mm;
            border: 1px dashed #cccccc;
            padding: 2mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            background: #ffffff;
            color: #000000;
        }

        /* Le titre : Nom du dossier */
        .label-title {
            font-size: 10pt; /* Police agrandie pour optimiser l'espace */
            font-weight: bold;
            color: #000000;
            line-height: 1.1;
            margin-bottom: 2mm;
            width: 100%;
            word-break: break-word; /* Pas de troncature */
        }

        /* La zone de détails */
        .label-info {
            font-size: 8pt; /* Police agrandie */
            color: #333333;
            line-height: 1.1;
            margin-bottom: 1.5mm;
            width: 100%;
            word-break: break-word; /* Pas de troncature */
        }
        
        .label-info:last-child {
            margin-bottom: 0;
        }

        /* Les sous-titres (Date, Départ, Arrivée) */
        .label-subtitle {
            color: #555555;
            display: block; 
            font-size: 6pt;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 0.5mm;
            pointer-events: none; /* Rendu non modifiable */
            user-select: none;
        }

        /* Interaction d'édition à l'écran (reproduit depuis labels.blade.php) */
        [contenteditable="true"] {
            border-radius: 2px;
            padding: 1px 2px;
            transition: background-color 0.2s ease;
            display: inline-block;
            width: 100%;
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
                /* On garde la bordure pointillée pour faciliter la découpe, 
                   tu peux la passer à 'solid' ou 'transparent' selon ton besoin */
                border-style: dashed;
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
                <strong>Texte modifiable :</strong> Vous pouvez cliquer directement sur les textes ci-dessous pour ajuster vos étiquettes (ex: raccourcir un nom de gare long) avant impression.
            </div>
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <button onclick="window.print()" class="btn-print">
                Imprimer la plaquette
            </button>
        </div>
    </div>

    <!-- Conteneur d'impression -->
    <div class="print-container">
        
        <?php
            $allRoutes = collect();
            foreach($items as $item) {
                $routes = $item->custom_values['transport_routes'] ?? [];
                if(!empty($routes)) {
                    foreach($routes as $route) {
                        $allRoutes->push([
                            'folder_name' => $item->folder->folder_name ?? 'Inconnu',
                            'date' => !empty($route['departure_date']) ? \Carbon\Carbon::parse($route['departure_date'])->format('d/m/Y') : 'À définir',
                            'departure' => $route['departure_station'] ?? 'N/A',
                            'arrival' => $route['arrival_station'] ?? 'N/A'
                        ]);
                    }
                } else {
                    $allRoutes->push([
                        'folder_name' => $item->folder->folder_name ?? 'Inconnu',
                        'date' => $item->service_date ? \Carbon\Carbon::parse($item->service_date)->format('d/m/Y') : 'À définir',
                        'departure' => 'Aucune gare trouvée',
                        'arrival' => ''
                    ]);
                }
            }
            // Avec un format A4 de 4 colonnes, on estime qu'on peut mettre environ 6 lignes (24 étiquettes) par page.
            // (La hauteur max disponible étant de 260mm / 44mm d'encombrement par ligne = ~5.9 lignes).
            // Si tu souhaites imprimer plus/moins d'étiquettes par page, modifie le chiffre '24' ci-dessous.
            $chunks = $allRoutes->chunk(24);
        ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $chunks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $chunk): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="page-sheet">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $chunk; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $routeData): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="label-card">
                        
                        <!-- 1. Nom du dossier -->
                        <div class="label-title" contenteditable="true">
                            <?php echo e($routeData['folder_name']); ?>

                        </div>
                        
                        <!-- 2. Date -->
                        <div class="label-info">
                            <span class="label-subtitle">Date du trajet</span>
                            <span contenteditable="true"><?php echo e($routeData['date']); ?></span>
                        </div>
                        
                        <!-- 3. Départ -->
                        <div class="label-info">
                            <span class="label-subtitle">Gare de départ</span>
                            <span contenteditable="true"><?php echo e($routeData['departure']); ?></span>
                        </div>
                        
                        <!-- 4. Arrivée (Si renseignée) -->
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($routeData['arrival'])): ?>
                            <div class="label-info">
                                <span class="label-subtitle">Gare d'arrivée</span>
                                <span contenteditable="true"><?php echo e($routeData['arrival']); ?></span>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

</body>
</html><?php /**PATH C:\Users\marke\Herd\portail-takada\resources\views/pdf/train-labels.blade.php ENDPATH**/ ?>