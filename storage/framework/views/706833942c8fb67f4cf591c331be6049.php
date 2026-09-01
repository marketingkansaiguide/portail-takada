<?php $__env->startSection('title', __('Nouvelle demande de contact')); ?>
<?php $__env->startSection('heading', __('Nouvelle demande de contact')); ?>

<?php $__env->startSection('content'); ?>
    <p style="margin:0 0 18px;"><?php echo e(__('Une nouvelle demande a été envoyée depuis le portail B2B.')); ?></p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0; background:#f7faf9; border-left:4px solid #096a61; border-radius:0 8px 8px 0;">
        <tr><td style="padding:17px 18px 7px; color:#6b7280; font-size:12px; font-weight:bold; width:38%;"><?php echo e(__('CONTACT')); ?></td><td style="padding:17px 18px 7px 0; color:#1f2937; font-size:14px; font-weight:600;"><?php echo e($data['name']); ?></td></tr>
        <tr><td style="padding:7px 18px; color:#6b7280; font-size:12px; font-weight:bold;"><?php echo e(__('AGENCE')); ?></td><td style="padding:7px 18px 7px 0; color:#1f2937; font-size:14px; font-weight:600;"><?php echo e($data['agency_name'] ?: __('Non renseignée')); ?></td></tr>
        <tr><td style="padding:7px 18px; color:#6b7280; font-size:12px; font-weight:bold;"><?php echo e(__('E-MAIL')); ?></td><td style="padding:7px 18px 7px 0; color:#1f2937; font-size:14px; font-weight:600;"><a href="mailto:<?php echo e($data['email']); ?>" style="color:#096a61;"><?php echo e($data['email']); ?></a></td></tr>
        <tr><td style="padding:7px 18px 17px; color:#6b7280; font-size:12px; font-weight:bold;"><?php echo e(__('SUJET')); ?></td><td style="padding:7px 18px 17px 0; color:#1f2937; font-size:14px; font-weight:600;"><?php echo e($data['subject']); ?></td></tr>
    </table>
    <div style="margin:22px 0; padding:18px; background:#f7faf9; border-radius:8px; border:1px solid #e4eeeb; color:#374151; white-space:pre-line;"><?php echo e($data['message']); ?></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layouts.portal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\marke\Herd\portail-takada\resources\views\emails\contact-request.blade.php ENDPATH**/ ?>