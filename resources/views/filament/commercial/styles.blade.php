<style>
    .tk-c-bar { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem .9rem; padding:.6rem .9rem; margin-bottom:.9rem; background:#fff; border:1px solid var(--gray-200,#e5e7eb); border-radius:.6rem; font-size:.85rem; }
    .tk-c-bar .ref { font-weight:700; }
    .tk-c-bar .name { font-weight:600; }
    .tk-c-muted { color:var(--gray-500,#6b7280); }
    .tk-c-badge { display:inline-block; padding:.1rem .5rem; border-radius:999px; font-size:.72rem; font-weight:600; background:#f3f4f6; color:#374151; white-space:nowrap; }
    .tk-c-badge.ok { background:#dcfce7; color:#166534; } .tk-c-badge.warn { background:#fef3c7; color:#92400e; }
    .tk-c-badge.bad { background:#fee2e2; color:#991b1b; } .tk-c-badge.info { background:#dbeafe; color:#1e40af; }
    .tk-c-badge.plan { background:#fff; color:#6b7280; border:1px dashed #9ca3af; }
    .tk-c-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(11rem,1fr)); gap:.6rem; margin-bottom:.9rem; }
    .tk-c-card { background:#fff; border:1px solid var(--gray-200,#e5e7eb); border-radius:.6rem; padding:.6rem .8rem; }
    .tk-c-card .n { font-size:1.35rem; font-weight:700; line-height:1.1; } .tk-c-card .l { font-size:.75rem; color:var(--gray-500,#6b7280); }
    .tk-c-alert { border:1px solid #fcd34d; background:#fffbeb; color:#92400e; border-radius:.6rem; padding:.55rem .8rem; margin-bottom:.9rem; font-size:.85rem; }
    .tk-c-alert.urgent { border-color:#f87171; background:#fef2f2; color:#991b1b; }
    .tk-flat-repeater .fi-fo-repeater-item { border:0 !important; box-shadow:none !important; background:transparent !important; padding:0 !important; }
    .tk-flat-repeater .fi-fo-repeater-item-header { display:none; }
    .tk-c-alert ul { margin:.25rem 0 0 1.1rem; list-style:disc; }
    .tk-c-table { width:100%; border-collapse:collapse; font-size:.82rem; }
    .tk-c-table th { text-align:left; font-weight:600; color:var(--gray-500,#6b7280); padding:.3rem .5rem; border-bottom:1px solid var(--gray-200,#e5e7eb); }
    .tk-c-table td { padding:.35rem .5rem; border-bottom:1px solid var(--gray-100,#f3f4f6); vertical-align:top; }
    .tk-c-two { display:grid; grid-template-columns:repeat(auto-fit,minmax(20rem,1fr)); gap:.9rem; margin-bottom:.9rem; }
    .tk-c-box { background:#fff; border:1px solid var(--gray-200,#e5e7eb); border-radius:.6rem; padding:.6rem .8rem; }
    .tk-c-box h4 { font-weight:700; margin:0 0 .35rem; font-size:.85rem; }
    .tk-c-day { display:grid; grid-template-columns:6.5rem 1fr; gap:.6rem; padding:.45rem 0; border-bottom:1px solid var(--gray-100,#f3f4f6); }
    .tk-c-day .d { font-weight:600; font-size:.82rem; }
    .tk-c-ent { display:flex; gap:.5rem; align-items:baseline; flex-wrap:wrap; font-size:.83rem; margin-bottom:.15rem; }
    .tk-c-ent.planned { color:#6b7280; border-left:2px dashed #9ca3af; padding-left:.4rem; }
    .tk-c-ent.ordered { border-left:2px solid #16a34a; padding-left:.4rem; }
    .tk-c-ent.client { color:#1e40af; border-left:2px solid #93c5fd; padding-left:.4rem; }
    .tk-c-ent.stay { border-left:2px solid #6b7280; padding-left:.4rem; }
    .tk-c-ent.info { border-left:2px solid #3b82f6; padding-left:.4rem; }
</style>

<script>
    window.tkOpenTarget = function (tabLabel, key) {
        const tab = [...document.querySelectorAll('[role=tab]')].find(t => t.innerText.trim().startsWith(tabLabel));
        if (tab) tab.click();
        if (! key) return;
        window.dispatchEvent(new CustomEvent('expand-section', {detail: {id: 'stays-section'}}));
        setTimeout(() => {
            const panel = document.querySelector('[role=tabpanel].fi-active') || document;
            const item = panel.querySelector('[x-sortable-item="' + key + '"]');
            if (item) { item.dispatchEvent(new CustomEvent('expand')); item.scrollIntoView({block: 'center', behavior: 'smooth'}); }
        }, 350);
    };
    window.tkAddStay = function () {
        window.dispatchEvent(new CustomEvent('expand-section', {detail: {id: 'stays-section'}}));
        setTimeout(() => {
            const panel = document.querySelector('[role=tabpanel].fi-active') || document;
            const add = [...panel.querySelectorAll('button')].find(b => b.innerText.trim() === 'Ajouter un hébergement');
            if (add) { add.click(); add.scrollIntoView({block: 'center', behavior: 'smooth'}); }
        }, 350);
    };
</script><style>
    /* En-tête collé au défilement et onglets sans défilement horizontal (7 libellés courts, tiennent à 1280 px). */
    .tk-c-stuck { position: sticky !important; top: var(--tk-top, 4rem); z-index: 20; background: var(--gray-50, #f9fafb); padding-bottom: .25rem; }
    .tk-c-shell .fi-tabs { position: sticky; top: calc(var(--tk-top, 4rem) + var(--tk-header-h, 0px)); z-index: 19; background: #fff; }
    .tk-c-tabs .fi-tabs { overflow-x: visible !important; flex-wrap: wrap; max-width: 100%; }
    .tk-c-tabs .fi-tabs-item { padding-inline: .6rem; white-space: nowrap; }
</style>

<script>
    // En-tête et barre d'onglets collés au défilement : la cellule de la grille qui porte l'en-tête devient sticky
    // (son conteneur englobe l'en-tête et les onglets), et la barre d'onglets se cale juste dessous.
    window.tkStick = function () {
        const bar = document.querySelector('.tk-c-bar');
        if (! bar) return;
        let cell = bar;
        while (cell.parentElement && ! (cell.parentElement.classList.contains('fi-sc') && cell.parentElement.children.length > 1)) cell = cell.parentElement;
        if (! cell.parentElement) return;
        cell.classList.add('tk-c-stuck');
        const h = Math.round(cell.getBoundingClientRect().height);
        document.documentElement.style.setProperty('--tk-header-h', h + 'px');
    };
    (function () {
        let t = null;
        const run = () => { clearTimeout(t); t = setTimeout(window.tkStick, 60); };
        new MutationObserver(run).observe(document.body, {childList: true, subtree: true});
        window.addEventListener('resize', run);
        run();
    })();
</script>