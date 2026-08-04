<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Alerte Ouverture Ventes - Cartes IC</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f5f7; margin: 0; padding: 20px;">
    <div style="max-width: 680px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; padding: 30px; border: 1px solid #e5e7eb;">
        
        <div style="text-align: center; margin-bottom: 25px;">
            <span style="font-size: 40px;">💳</span>
            <h1 style="color: #b45309; font-size: 20px; margin-top: 10px;">Ouverture des ventes atteinte - Cartes IC</h1>
            <p style="color: #6b7280; font-size: 13px; margin-top: 5px;">
                Récapitulatif des prestations dont la date d'ouverture des ventes (J-) est atteinte.
            </p>
        </div>

        <p style="color: #374151; font-size: 14px; line-height: 1.6;">
            Bonjour l'équipe Takada,
        </p>

        <p style="color: #374151; font-size: 14px; line-height: 1.6;">
            La date d'ouverture des ventes a été atteinte pour <strong><?php echo e(count($items)); ?> prestation(s)</strong> de Cartes IC en attente de validation :
        </p>

        <div style="overflow-x: auto; margin: 20px 0;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; color: #1f2937;">
                <thead>
                    <tr style="background-color: #fef3c7; color: #92400e; text-align: left; border-bottom: 2px solid #fcd34d;">
                        <th style="padding: 10px; border-right: 1px solid #fcd34d;">Réf. / Dossier</th>
                        <th style="padding: 10px; border-right: 1px solid #fcd34d;">Pax Leader</th>
                        <th style="padding: 10px; text-align: center; border-right: 1px solid #fcd34d;">Qté</th>
                        <th style="padding: 10px; border-right: 1px solid #fcd34d;">Date Prestation</th>
                        <th style="padding: 10px;">Livraison / Envoi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $effectiveDate = $item->service_date ? $item->service_date->format('d/m/Y') : ($item->folder?->start_date ? $item->folder->start_date->format('d/m/Y') : '---');
                        ?>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 10px; border-right: 1px solid #f3f4f6;">
                                <strong><?php echo e($item->folder?->reference); ?></strong><br>
                                <span style="font-size: 11px; color: #6b7280;"><?php echo e($item->folder?->folder_name); ?></span>
                            </td>
                            <td style="padding: 10px; border-right: 1px solid #f3f4f6;">
                                <?php echo e($item->folder?->lead_traveler_name); ?>

                            </td>
                            <td style="padding: 10px; text-align: center; font-weight: bold; color: #d97706; border-right: 1px solid #f3f4f6;">
                                <?php echo e($item->quantity); ?> carte(s)
                            </td>
                            <td style="padding: 10px; border-right: 1px solid #f3f4f6;">
                                <?php echo e($effectiveDate); ?>

                            </td>
                            <td style="padding: 10px;">
                                <span style="font-size: 12px; font-weight: bold;"><?php echo e($item->folder?->dispatch_method_label); ?></span><br>
                                <span style="font-size: 11px; color: #6b7280;"><?php echo e($item->folder?->dispatch_address); ?></span>
                            </td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="<?php echo e(route('filament.admin.pages.ic-cards')); ?>" 
               style="background-color: #f59e0b; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; font-size: 14px; display: inline-block;">
               Accéder à la gestion des Cartes IC ↗
            </a>
        </div>

        <hr style="border: none; border-top: 1px solid #f3f4f6; margin: 30px 0 15px 0;">
        <p style="font-size: 11px; color: #9ca3af; text-align: center;">
            Notification automatique générée par le Portail Takada Travel.
        </p>
    </div>
</body>
</html><?php /**PATH C:\Users\marke\Herd\portail-takada\resources\views/emails/ic-card-opening-alert.blade.php ENDPATH**/ ?>