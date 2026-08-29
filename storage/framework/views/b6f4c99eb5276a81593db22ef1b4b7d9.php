<!doctype html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo e(__('Site temporairement indisponible')); ?></title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 2rem; background: #f8fafc; color: #172033; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        main { width: min(100%, 38rem); padding: 3rem; text-align: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 1.25rem; box-shadow: 0 1.5rem 3.5rem rgb(15 23 42 / 8%); }
        .mark { display: inline-grid; width: 3.25rem; height: 3.25rem; place-items: center; margin-bottom: 1.25rem; border-radius: 999px; background: #ecfdf5; color: #047857; font-size: 1.45rem; }
        h1 { margin: 0; font-size: clamp(1.6rem, 4vw, 2.25rem); line-height: 1.2; }
        p { margin: 1rem 0 0; color: #475569; font-size: 1.05rem; line-height: 1.65; }
    </style>
</head>
<body>
    <main>
        <div class="mark" aria-hidden="true">✦</div>
        <h1><?php echo e(__('Le portail est temporairement en maintenance.')); ?></h1>
        <p><?php echo e(__('Nous effectuons quelques améliorations. Merci de revenir dans quelques instants.')); ?></p>
    </main>
</body>
</html>
<?php /**PATH C:\Users\marke\Herd\portail-takada\resources\views\maintenance.blade.php ENDPATH**/ ?>