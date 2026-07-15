<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Récapitulatif - <?php echo e($folder->reference); ?></title>
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
        .items-table tr:nth-child(even) td { background-color: #f8fafc; }
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
                <div style="color: #64748b; font-size: 9pt;">Réservation B2B Japon</div>
            </td>
            <td class="text-right">
                <div class="title">Pré-Facture</div>
                <div style="font-weight: bold; margin-top: 5px;">Réf : <?php echo e($folder->reference); ?></div>
                <div style="color: #64748b; font-size: 9pt;">Émis le : <?php echo e($dateEmit); ?></div>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <table class="info-table">
        <tr>
            <td>
                <div class="info-box" style="padding-right: 15px;">
                    <h2>Agence Cliente</h2>
                    <div><strong>Nom :</strong> <?php echo e($agency->name); ?></div>
                    <div><strong>Contact :</strong> <?php echo e($agency->email ?? '---'); ?></div>
                    <div><strong>Téléphone :</strong> <?php echo e($agency->phone ?? '---'); ?></div>
                    <div style="margin-top: 5px;"><strong>Adresse Facturation :</strong></div>
                    <div style="color: #475569; font-style: italic; padding-left: 5px;">
                        <?php echo nl2br(e($agency->address)); ?>

                    </div>
                </div>
            </td>
            <td>
                <div class="info-box" style="padding-left: 15px;">
                    <h2>Détails du Dossier</h2>
                    <div><strong>Nom du dossier :</strong> <?php echo e($folder->folder_name); ?></div>
                    <div><strong>Pax Leader :</strong> <?php echo e($folder->lead_traveler_name); ?></div>
                    <div><strong>Nombre de Pax :</strong> <?php echo e($totalPax); ?> Voyageurs (<?php echo e($folder->pax_adults); ?> Adultes, <?php echo e($folder->pax_children); ?> Enfants)</div>
                    <div><strong>Dates du Séjour :</strong> Du <?php echo e($folder->start_date?->format('d/m/Y')); ?> au <?php echo e($folder->end_date?->format('d/m/Y')); ?></div>
                    <div><strong>Mode d'envoi billetterie :</strong> <?php echo e(ucfirst($folder->ticket_dispatch_method)); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($folder->ticket_dispatch_other): ?> (<?php echo e($folder->ticket_dispatch_other); ?>) <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                </div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Titre de la prestation</th>
                <th class="text-center" style="width: 15%;">Date</th>
                <th class="text-center" style="width: 10%;">Qté</th>
                <th class="text-right" style="width: 15%;">Prix Unitaire</th>
                <th class="text-right" style="width: 15%;">Prix Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <tr>
                    <td>
                        <strong><?php echo e($item->product?->name); ?></strong>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->productOption): ?>
                            <br><span style="font-size: 8.5pt; color: #64748b;">Option : <?php echo e($item->productOption->name); ?></span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td class="text-center"><?php echo e($item->service_date?->format('d/m/Y')); ?></td>
                    <td class="text-center"><?php echo e($item->quantity); ?></td>
                    <td class="text-right">¥<?php echo e(number_format($item->unit_price)); ?></td>
                    <td class="text-right">¥<?php echo e(number_format($item->total_price)); ?></td>
                </tr>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>Sous-total Prestations :</td>
            <td class="text-right">¥<?php echo e(number_format($itemsTotal)); ?></td>
        </tr>
        <tr>
            <td>Frais de dossier appliqués :</td>
            <td class="text-right">¥<?php echo e(number_format($folder->folder_fee)); ?></td>
        </tr>
        <tr class="total-row">
            <td>Montant Total :</td>
            <td class="text-right">¥<?php echo e(number_format($grandTotal)); ?></td>
        </tr>
    </table>

    <div class="divider" style="margin-top: 30px; margin-bottom: 15px;"></div>

    <div class="footer">
        <p>Ce document est un récapitulatif estimatif de prestations (pré-facture) et ne fait pas office de facture légale acquittée.</p>
        <p><strong>TAKADA PORTAL</strong> — Service de réservation B2B Japon</p>
    </div>

</body>
</html><?php /**PATH C:\Users\marke\Herd\portail-takada\resources\views\pdf\recapitulatif.blade.php ENDPATH**/ ?>