<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?php echo e(__('Récapitulatif')); ?> - <?php echo e($folder->reference); ?></title>
    <style>
        @page { margin: 18px 24px 22px; }

        /* Dompdf n’embarque pas Arial ni les glyphes japonais : cette police Unicode est incluse dans le PDF. */
        @font-face {
            font-family: 'M PLUS 1p';
            font-style: normal;
            font-weight: 400;
            src: url('<?php echo e(public_path('fonts/MPLUS1p-Regular.ttf')); ?>') format('truetype');
        }

        @font-face {
            font-family: 'M PLUS 1p';
            font-style: normal;
            font-weight: 700;
            src: url('<?php echo e(public_path('fonts/MPLUS1p-Bold.ttf')); ?>') format('truetype');
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

    <?php
        $configuredLogo = \App\Models\Setting::query()->first()?->site_logo_path;
        $logoPath = $configuredLogo && \Illuminate\Support\Facades\Storage::disk('public')->exists($configuredLogo)
            ? \Illuminate\Support\Facades\Storage::disk('public')->path($configuredLogo)
            : public_path('images/logo.svg');
    ?>

    <table class="header" style="width: 100%;">
        <tr>
            <td>
                <img class="brand-logo" src="<?php echo e($logoPath); ?>" alt="Takada Travel">
            </td>
            <td class="text-right">
                <div class="title"><?php echo e(__('Pré-Facture')); ?></div>
                <div style="font-weight: bold; margin-top: 5px;"><?php echo e(__('Réf :')); ?> <?php echo e($folder->reference); ?></div>
                <div style="color: #64748b; font-size: 9pt;"><?php echo e(__('Émis le :')); ?> <?php echo e($dateEmit); ?></div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td>
                <div class="info-box" style="padding-right: 15px;">
                    <h2><?php echo e(__('Agence Cliente')); ?></h2>
                    <div><strong><?php echo e(__('Nom :')); ?></strong> <?php echo e($agency->name); ?></div>
                    <div><strong><?php echo e(__('Contact :')); ?></strong> <?php echo e($agency->email ?? '---'); ?></div>
                    <div><strong><?php echo e(__('Téléphone :')); ?></strong> <?php echo e($agency->phone ?? '---'); ?></div>
                    <div style="margin-top: 5px;"><strong><?php echo e(__('Adresse Facturation :')); ?></strong></div>
                    <div style="color: #475569; font-style: italic; padding-left: 5px;">
                        <?php echo nl2br(e($agency->address)); ?>

                    </div>
                </div>
            </td>
            <td>
                <div class="info-box" style="padding-left: 15px;">
                    <h2><?php echo e(__('Détails du Dossier')); ?></h2>
                    <div><strong><?php echo e(__('Nom du dossier :')); ?></strong> <?php echo e($folder->folder_name); ?></div>
                    <div><strong><?php echo e(__('Pax Leader :')); ?></strong> <?php echo e($folder->lead_traveler_name); ?></div>
                    <div><strong><?php echo e(__('Nombre de Pax :')); ?></strong> <?php echo e($totalPax); ?> Pax - <?php echo e($folder->pax_adults); ?> <?php echo e(__('Adulte(s)')); ?>, <?php echo e($folder->pax_children); ?> <?php echo e(__('Enfant(s)')); ?></div>
                    <div><strong><?php echo e(__('Dates du Séjour :')); ?></strong> <?php echo e(__('Du')); ?> <?php echo e($folder->start_date?->format('d/m/Y')); ?> <?php echo e(__('au')); ?> <?php echo e($folder->end_date?->format('d/m/Y')); ?></div>
                    <div><strong><?php echo e(__('Mode d’envoi billetterie :')); ?></strong> <?php echo e(ucfirst($folder->ticket_dispatch_method)); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($folder->ticket_dispatch_other): ?> (<?php echo e($folder->ticket_dispatch_other); ?>) <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                </div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th><?php echo e(__('Détail de la prestation')); ?></th>
                <th class="text-center" style="width: 15%;">Date</th>
                <th class="text-center" style="width: 10%;"><?php echo e(__('Qté')); ?></th>
                <th class="text-right" style="width: 15%;"><?php echo e(__('Prix Unitaire')); ?></th>
                <th class="text-right" style="width: 15%;"><?php echo e(__('Prix Total')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
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
                ?>

                <tr style="background-color: #ffffff;">
                    <td>
                        <strong><?php echo e($item->product?->name); ?></strong>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasOptions): ?>
                            <span class="base-tariff-label" style="font-size: 8pt; color: #64748b;">(<?php echo e(__('Tarif de base')); ?>)</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transportRoutes->isNotEmpty()): ?>
                            <div class="transport-routes">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $transportRoutes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $route): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <div class="transport-route">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($route['date']): ?>
                                            <?php echo e($route['date']); ?> ·
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <span class="transport-route-journey"><?php echo e($route['journey']); ?></span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($route['pax']): ?>
                                            · <?php echo e($route['pax']); ?> pax
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td class="text-center"><?php echo e($item->service_date?->format('d/m/Y')); ?></td>
                    <td class="text-center"><?php echo e($item->quantity); ?></td>
                    <td class="text-right">
                        ¥<?php echo e(number_format($hasOptions ? $baseUnitPrice : $item->unit_price)); ?>

                    </td>
                    <td class="text-right"><strong>¥<?php echo e(number_format($hasOptions ? $baseTotal : $item->total_price)); ?></strong></td>
                </tr>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasOptions): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $optionsList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr class="option-row" style="background-color: #f8fafc;">
                            <td class="option-description"><?php echo e($opt['name']); ?></td>
                            <td class="text-center" style="color: #94a3b8;">—</td>
                            <td class="text-center"><?php echo e($opt['display_qty']); ?></td>
                            <td class="text-right">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((float) $opt['total'] === 0.0): ?>
                                    —
                                <?php else: ?>
                                    ¥<?php echo e(number_format($opt['unit_price'])); ?>

                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="text-right option-total">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((float) $opt['total'] !== 0.0): ?>
                                    +¥<?php echo e(number_format($opt['total'])); ?>

                                <?php else: ?>
                                    —
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                    <tr class="item-subtotal" style="background-color: #f1f5f9; border-bottom: 1px solid #cbd5e1;">
                        <td colspan="4" class="text-right" style="font-weight: bold;"><?php echo e(__('Sous-total')); ?> :</td>
                        <td class="text-right" style="font-weight: bold;">¥<?php echo e(number_format($item->total_price)); ?></td>
                    </tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td><?php echo e(__('Sous-total Prestations :')); ?></td>
            <td class="text-right">¥<?php echo e(number_format($itemsTotal)); ?></td>
        </tr>
        <tr>
            <td><?php echo e(__('Frais de dossier appliqués :')); ?></td>
            <td class="text-right">¥<?php echo e(number_format($folder->folder_fee)); ?></td>
        </tr>
        <tr class="total-row">
            <td><?php echo e(__('Montant Total :')); ?></td>
            <td class="text-right">¥<?php echo e(number_format($grandTotal)); ?></td>
        </tr>
    </table>

    <div class="divider" style="margin-top: 30px; margin-bottom: 15px;"></div>

    <div class="footer">
        <p><?php echo e(__('Ce document est un récapitulatif estimatif de prestations (pré-facture) et ne fait pas office de facture légale acquittée.')); ?></p>
        <p><strong>TAKADA PORTAL</strong> — <?php echo e(__('Service de réservation B2B Japon')); ?></p>
    </div>

</body>
</html>
<?php /**PATH C:\Users\marke\Herd\portail-takada\resources\views\pdf\recapitulatif.blade.php ENDPATH**/ ?>