<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Étiquettes Billets de Train</title>
    <style>
        /* Configuration de la page d'impression A4 Portrait */
        @page {
            size: A4 portrait;
            margin-top: 18.5mm;
            margin-bottom: 18.5mm;
            margin-left: 19mm;
            margin-right: 19mm;
        }
        
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: transparent;
            font-size: 0; /* Annule l'espacement natif HTML entre les blocs inline */
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        .label {
            /* L'utilisation d'inline-flex permet de garder le flux de la grille tout en centrant en interne */
            display: inline-flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            
            width: 40mm;
            height: 40mm;
            margin-right: 4mm;
            margin-bottom: 4mm;
            padding: 2mm;
            border: 1px dashed #cbd5e1;
            border-radius: 2px;
            vertical-align: top;
            background-color: #fff;
            page-break-inside: avoid;
        }

        /* Retrait de la marge droite pour la 4ème étiquette de chaque ligne */
        .label:nth-child(4n) {
            margin-right: 0;
        }

        .label-title {
            font-size: 10pt; 
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 2mm;
            line-height: 1.2;
            width: 100%;
        }

        .label-info {
            font-size: 8pt; 
            color: #334155;
            margin-bottom: 1.5mm;
            line-height: 1.2;
            width: 100%;
        }

        .label-info:last-child {
            margin-bottom: 0; 
        }

        .label-info strong {
            color: #0f172a;
            display: block; 
            font-size: 6.5pt; 
            text-transform: uppercase;
            margin-bottom: 0.5mm;
            /* On s'assure que le titre du champ ne soit pas modifiable par erreur */
            pointer-events: none; 
            user-select: none;
        }

        /* Indication visuelle pour les champs modifiables (écran uniquement) */
        [contenteditable="true"] {
            outline: none;
            border-radius: 2px;
            padding: 1px 2px;
            transition: background-color 0.2s;
        }
        [contenteditable="true"]:hover, [contenteditable="true"]:focus {
            background-color: #f1f5f9;
            box-shadow: 0 0 0 1px #cbd5e1;
            cursor: text;
        }

        /* On cache les effets de survol/focus lors de l'impression */
        @media print {
            [contenteditable="true"]:hover, [contenteditable="true"]:focus {
                background-color: transparent;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php
            $routes = $item->custom_values['transport_routes'] ?? [];
        ?>
        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($routes)): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $routes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $route): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="label">
                    <div class="label-title" contenteditable="true"><?php echo e($item->folder->folder_name ?? 'Inconnu'); ?></div>
                    <div class="label-info">
                        <strong>Date du trajet</strong> 
                        <span contenteditable="true"><?php echo e(!empty($route['departure_date']) ? \Carbon\Carbon::parse($route['departure_date'])->format('d/m/Y') : 'À définir'); ?></span>
                    </div>
                    <div class="label-info">
                        <strong>Gare de départ</strong> 
                        <span contenteditable="true"><?php echo e($route['departure_station'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="label-info">
                        <strong>Gare d'arrivée</strong> 
                        <span contenteditable="true"><?php echo e($route['arrival_station'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <?php else: ?>
            <!-- Cas de secours si une prestation train n'a pas de tableau de trajets défini -->
            <div class="label">
                <div class="label-title" contenteditable="true"><?php echo e($item->folder->folder_name ?? 'Inconnu'); ?></div>
                <div class="label-info">
                    <strong>Date du trajet</strong> 
                    <span contenteditable="true"><?php echo e($item->service_date ? \Carbon\Carbon::parse($item->service_date)->format('d/m/Y') : 'À définir'); ?></span>
                </div>
                <div class="label-info">
                    <span contenteditable="true"><em>Aucune gare trouvée</em></span>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

    <script>
        // Si vous avez besoin de modifier le texte, annulez simplement 
        // la boîte de dialogue d'impression, modifiez le texte, 
        // puis refaites Ctrl+P / Cmd+P.
        window.onload = function() {
            setTimeout(() => {
                window.print();
            }, 300);
        }
    </script>
</body>
</html><?php /**PATH C:\Users\marke\Herd\portail-takada\resources\views/pdf/train-labels.blade.php ENDPATH**/ ?>