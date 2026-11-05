{{-- Vue « jour par jour » des prestations (back-office dossier), inspirée du tableau Excel des équipes. --}}
@php
    $fmtPrice = fn ($value) => number_format((float) $value, 0, ',', ' ');
    $locale = app()->getLocale();
@endphp

<div
    class="tk-dbd"
    x-bind:class="{ 'tk-show-free': showFree }"
    x-data="{
        mode: 'day',
        showFree: (() => { try { return localStorage.getItem('tk-dbd-free') === '1' } catch (e) { return false } })(),
        toggleFree() {
            this.showFree = ! this.showFree;
            try { localStorage.setItem('tk-dbd-free', this.showFree ? '1' : '0') } catch (e) {}
        },
        init() {
            this.apply();
            clearInterval(window.tkDbdWatch);
            window.tkDbdWatch = setInterval(() => {
                if (this.current && ! document.querySelector('.tk-dbd-modal')) this.close();
                const li = document.querySelector('.tk-dbd-modal');
                if (li) this.tag(li);
            }, 600);
        },
        setMode(mode) {
            this.mode = mode;
            try { localStorage.setItem('tk-dbd-mode', mode) } catch (e) {}
            this.apply();
        },
        apply() {
            const section = this.$el.closest('.fi-section');
            if (section) section.classList.toggle('tk-dbd-on', this.mode === 'day');
        },
        item(repeater, key) {
            return [...document.querySelectorAll('li.fi-fo-repeater-item')].find((li) =>
                li.getAttribute('x-sortable-item') === key
                && (li.getAttribute('wire:key') || '').includes('.' + repeater + '.')
            );
        },
        current: null,
        open(repeater, key, name = '', sub = '') {
            const li = this.item(repeater, key);
            if (! li) return;
            this.close();
            li.classList.add('tk-dbd-open', 'tk-dbd-modal');
            li.dispatchEvent(new CustomEvent('expand'));
            this.tag(li);
            li.scrollTop = 0;
            this.current = { repeater, key, name, sub };
            document.documentElement.style.overflow = 'hidden';
        },
        tag(li) {
            const root = li.querySelector(':scope > .fi-fo-repeater-item-content > .fi-grid');
            if (! root) return;
            [...root.children].forEach((col) => {
                const grid = col.querySelector('.fi-grid');
                if (! grid) return;
                if (col.querySelector('[id$=&quot;.service_date&quot;]')) grid.classList.add('tk-h-date');
                else if (col.querySelector('[id$=&quot;.invoice_received_at&quot;]')) grid.classList.add('tk-h-facture');
                else if (! col.querySelector('.fi-fo-repeater') && col.querySelectorAll('.fi-select-input').length >= 2) grid.classList.add('tk-h-prestation');
            });
        },
        close() {
            document.querySelectorAll('.tk-dbd-modal').forEach((el) => el.classList.remove('tk-dbd-modal', 'tk-dbd-open'));
            this.current = null;
            document.documentElement.style.overflow = '';
        },
        save() {
            const form = document.querySelector('.tk-dbd-modal')?.closest('form') || this.$el.closest('form');
            this.close();
            if (form) form.requestSubmit();
        },
        act(repeater, key, action) {
            const li = this.item(repeater, key);
            const button = li && [...li.querySelectorAll('.fi-fo-repeater-item-header-end-actions [wire\\:click]')]
                .find((el) => (el.getAttribute('wire:click') || '').includes(`mountAction('${action}'`));
            if (button) button.click(); else this.open(repeater, key);
        },
    }"
    x-on:keydown.escape.window="current && ! document.querySelector('.fi-modal-open, .tk-dbd-modal [aria-expanded=true]') && close()"
>
    <style>
        .tk-dbd { --tk-dbd-line: var(--gray-200); --tk-dbd-head: var(--gray-50); --tk-dbd-muted: var(--gray-400); }
        html.dark .tk-dbd { --tk-dbd-line: var(--gray-700); --tk-dbd-head: var(--gray-800); --tk-dbd-muted: var(--gray-500); }
        .tk-dbd-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: .9rem; }
        .tk-dbd-kpis { display: flex; flex-wrap: wrap; gap: .4rem 1rem; font-size: .8rem; color: var(--gray-500); }
        .tk-dbd-kpis b { color: var(--gray-950); font-weight: 700; }
        html.dark .tk-dbd-kpis b { color: var(--gray-50); }
        .tk-dbd-seg { display: inline-flex; border: 1px solid var(--tk-dbd-line); border-radius: .375rem; overflow: hidden; font-size: .8rem; font-weight: 600; }
        .tk-dbd-seg button { padding: .4rem .85rem; color: var(--gray-600); background: transparent; }
        html.dark .tk-dbd-seg button { color: var(--gray-300); }
        .tk-dbd-seg button.is-on { background: var(--primary-600); color: #fff; }
        .tk-dbd-link { font-size: .75rem; color: var(--gray-500); text-decoration: underline; margin-right: .5rem; }
        .tk-dbd-scroll { overflow: visible; }
        .tk-dbd table { width: 100%; border-collapse: collapse; font-size: .78rem; table-layout: auto; }
        .tk-dbd th { background: var(--tk-dbd-head); text-align: left; font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); padding: .45rem .4rem; border: 1px solid var(--tk-dbd-line); font-weight: 600; }
        .tk-dbd td { border: 1px solid var(--tk-dbd-line); padding: .4rem .4rem; vertical-align: middle; }
        .tk-dbd td.tk-j { text-align: center; font-weight: 700; width: 2rem; background: var(--tk-dbd-head); }
        .tk-dbd td.tk-d { width: 5.4rem; white-space: nowrap; font-weight: 600; background: var(--tk-dbd-head); }
        .tk-dbd tr.is-we td.tk-d, .tk-dbd tr.is-we td.tk-j { color: var(--primary-700); }
        html.dark .tk-dbd tr.is-we td.tk-d, html.dark .tk-dbd tr.is-we td.tk-j { color: var(--primary-400); }
        .tk-dbd tr.is-day td { border-top: 2px solid var(--gray-300); }
        html.dark .tk-dbd tr.is-day td { border-top-color: var(--gray-600); }
        .tk-dbd tr.is-free td { color: var(--tk-dbd-muted); font-style: italic; }
        .tk-dbd tr.is-item { cursor: pointer; }
        .tk-dbd tr.is-item:hover td:not(.tk-j):not(.tk-d) { background: color-mix(in srgb, var(--primary-500) 5%, transparent); }
        .tk-dbd .tk-name { font-weight: 500; color: var(--gray-950); }
        .tk-dbd td.tk-p { min-width: 9rem; }
        .tk-dbd td.tk-p:has(.tk-route) { min-width: 11rem; }
        html.dark .tk-dbd .tk-name { color: var(--gray-50); }
        .tk-dbd .is-cancelled .tk-name { text-decoration: line-through; color: var(--tk-dbd-muted); }
        .tk-dbd .tk-opt { display: block; font-size: .72rem; color: var(--gray-500); margin-top: .1rem; }
        .tk-dbd td.tk-st { min-width: 6.5rem; }
        .tk-dbd .tk-route { display: block; margin-top: .2rem; padding-left: .5rem; border-left: 2px solid var(--gray-300); font-size: .72rem; line-height: 1.35; color: var(--gray-600); }
        .tk-dbd .tk-route b { font-weight: 600; color: var(--gray-800); }
        html.dark .tk-dbd .tk-route { color: var(--gray-400); border-color: var(--gray-600); }
        html.dark .tk-dbd .tk-route b { color: var(--gray-200); }
        .tk-dbd .tk-buy { color: var(--gray-500); }
        .tk-dbd .tk-c { text-align: center; white-space: nowrap; }
        .tk-dbd .tk-r { text-align: right; white-space: nowrap; }
        .tk-dbd .tk-flag { display: inline-block; font-size: .72rem; font-style: normal; color: var(--gray-600); background: var(--tk-accent-soft, #f3f1ec); border: 1px solid var(--tk-accent-border, #e4e0d6); border-radius: .25rem; padding: 0 .4rem; margin-right: .4rem; }
        .tk-dbd .tk-lock { font-size: .72rem; color: var(--gray-500); white-space: nowrap; }
        .tk-dbd .tk-badge { display: inline-flex; align-items: center; padding: .1rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 600; white-space: normal; line-height: 1.25; border: 1px solid; }
        .tk-dbd .tk-success { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
        .tk-dbd .tk-warning { background: #fffbeb; color: #b45309; border-color: #fde68a; }
        .tk-dbd .tk-danger { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
        .tk-dbd .tk-info { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .tk-dbd .tk-gray { background: #f8fafc; color: #475569; border-color: #e2e8f0; }
        html.dark .tk-dbd .tk-badge { background: transparent; }
        html.dark .tk-dbd .tk-success { color: #4ade80; border-color: #166534; }
        html.dark .tk-dbd .tk-warning { color: #fbbf24; border-color: #92400e; }
        html.dark .tk-dbd .tk-danger { color: #f87171; border-color: #991b1b; }
        html.dark .tk-dbd .tk-info { color: #60a5fa; border-color: #1e40af; }
        html.dark .tk-dbd .tk-gray { color: #94a3b8; border-color: #334155; }
        .tk-dbd .tk-alert-invoice { display: block; color: #dc2626; font-weight: 700; font-size: .72rem; }
        .tk-dbd .tk-alert-supplier { display: inline-block; margin-top: .15rem; background: #fee2e2; color: #b91c1c; border: 1px solid #f87171; border-radius: 999px; padding: 0 .45rem; font-weight: 700; font-size: .65rem; white-space: normal; }
        .tk-dbd .tk-acts { display: inline-flex; gap: .15rem; }
        .tk-dbd .tk-acts button { font-size: .7rem; font-weight: 600; color: var(--gray-600); border: 1px solid var(--tk-dbd-line); border-radius: .25rem; padding: .2rem; background: transparent; white-space: nowrap; line-height: 0; }
        .tk-dbd .tk-acts svg { width: .95rem; height: .95rem; }
        html.dark .tk-dbd .tk-acts button { color: var(--gray-300); }
        .tk-dbd .tk-acts button:hover { border-color: var(--gray-400); }
        .tk-dbd .tk-acts button.is-del { color: #b91c1c; }
        .tk-dbd .tk-acts button.is-mail { color: #2563eb; }
        .tk-dbd .tk-acts button.is-fax { color: #16a34a; }
        .tk-dbd tfoot td { font-weight: 700; background: var(--tk-dbd-head); }
        .tk-dbd-empty { padding: 1rem; text-align: center; color: var(--gray-500); font-size: .85rem; border: 1px dashed var(--tk-dbd-line); border-radius: .5rem; }
        /* Mode jour par jour : les fiches du repeater restent repliées et masquées, sauf celles ouvertes depuis le tableau ou pas encore enregistrées. */
        /* Les prestations (et prestations internes) enregistrées ne s'affichent que dans le tableau : leur fiche n'apparaît qu'en popin. */
        li.fi-fo-repeater-item[x-sortable-item^="record-"][wire\:key*=".folderItems.record-"]:not(.tk-dbd-open),
        li.fi-fo-repeater-item[x-sortable-item^="record-"][wire\:key*=".internalItems.record-"]:not(.tk-dbd-open) { display: none; }
        /* Popin : la fiche du repeater est affichée par-dessus la page, sans la déplacer dans le DOM (les champs restent liés au formulaire). */
        .tk-dbd-backdrop { position: fixed; inset: 0; z-index: 35; background: rgba(12, 10, 9, .55); }
        .tk-dbd-mbar, .fi-fo-repeater-item.tk-dbd-modal { left: calc(50% - min(520px, 47vw)); width: min(1040px, 94vw); }
        .tk-dbd-mbar { position: fixed; z-index: 36; top: 4vh; height: 3.25rem; display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 0 1rem 0 1.25rem; background: #fff; border-bottom: 1px solid var(--gray-200); border-radius: .75rem .75rem 0 0; }
        html.dark .tk-dbd-mbar { background: var(--gray-900); border-color: var(--gray-700); }
        .tk-dbd-mbar strong { font-size: .95rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--gray-950); }
        html.dark .tk-dbd-mbar strong { color: var(--gray-50); }
        .tk-dbd-mbar .tk-mbtns { display: flex; gap: .5rem; flex-shrink: 0; }
        .tk-dbd-mbar button { font-size: .8rem; font-weight: 600; padding: .4rem .8rem; border-radius: .375rem; border: 1px solid var(--gray-300); color: var(--gray-700); background: transparent; }
        html.dark .tk-dbd-mbar button { color: var(--gray-200); border-color: var(--gray-600); }
        .tk-dbd-mbar button.is-primary { background: var(--primary-600); border-color: var(--primary-600); color: #fff; }
        .fi-fo-repeater-item.tk-dbd-modal { display: block !important; position: fixed !important; z-index: 37; top: calc(4vh + 3.25rem); max-height: calc(92vh - 3.25rem); overflow-y: auto; margin: 0 !important; background: #fff; border-radius: 0 0 .75rem .75rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, .4); }
        html.dark .fi-fo-repeater-item.tk-dbd-modal { background: var(--gray-900); }
        .tk-dbd-mbar .tk-mtitle { min-width: 0; display: flex; flex-direction: column; line-height: 1.2; }
        .tk-dbd-mbar .tk-mtitle span { font-size: .75rem; color: var(--gray-500); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        /* ---------- Popin : fiche d'une prestation ----------
           La fiche reste le composant Filament d'origine (champs liés au formulaire) ;
           on la met en page uniquement en CSS : fond papier, blocs blancs titrés,
           actions remontées dans la barre du haut, plus de pliage. */
        .tk-dbd-mbar .tk-mbtns { width: 16.5rem; justify-content: flex-end; }
        .tk-dbd-modal > .fi-fo-repeater-item-header {
            position: fixed; z-index: 38; top: calc(4vh + .7rem); right: calc(50vw - min(520px, 47vw) + 18.25rem);
            display: flex; align-items: center; gap: .25rem; padding: 0 .75rem 0 0; margin: 0; height: 1.9rem;
            background: transparent; border: 0; border-right: 1px solid var(--gray-200); cursor: default; pointer-events: none;
        }
        html.dark .tk-dbd-modal > .fi-fo-repeater-item-header { border-color: var(--gray-700); }
        .tk-dbd-modal > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-end-actions { pointer-events: auto; gap: .35rem; }
        .tk-dbd-modal > .fi-fo-repeater-item-header > .fi-fo-repeater-item-header-label,
        .tk-dbd-modal > .fi-fo-repeater-item-header > .fi-fo-repeater-item-header-start-actions,
        .tk-dbd-modal > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-collapsible-actions { display: none !important; }
        /* Listes déroulantes dans la popin : la ligne ouverte passe au-dessus des suivantes, panneau opaque et lisible */
        .tk-dbd-modal .fi-fo-repeater-item:focus-within, .tk-dbd-modal .fi-fo-field:focus-within { position: relative; z-index: 40; }
        .tk-dbd-modal .fi-dropdown-panel { z-index: 60; opacity: 1; background: #fff; box-shadow: 0 8px 24px rgba(0,0,0,.14); }
        html.dark .tk-dbd-modal .fi-dropdown-panel { background: var(--gray-900); }
        .tk-dbd-modal .fi-dropdown-panel .fi-select-input-option, .tk-dbd-modal .fi-dropdown-panel li { opacity: 1; color: var(--gray-950); }
        html.dark .tk-dbd-modal .fi-dropdown-panel .fi-select-input-option, html.dark .tk-dbd-modal .fi-dropdown-panel li { color: var(--gray-50); }
        /* Liste déroulante ouverte près du bas de la popin : on réserve la place pour qu'elle ne soit pas coupée ni ne décale le contenu */
        .tk-dbd-modal { scrollbar-gutter: stable; }
        .tk-dbd-modal:has(.fi-dropdown-panel[style*="display: block"]) { padding-bottom: 14rem; }

        .tk-dbd-modal > .fi-fo-repeater-item-content { display: block !important; padding: 1rem 1.25rem 1.25rem; background: var(--tk-paper, #f7f6f3); border: 0; }
        html.dark .tk-dbd-modal > .fi-fo-repeater-item-content { background: var(--gray-950); }
        .tk-dbd-modal > .fi-fo-repeater-item-content > .fi-grid { gap: .75rem !important; }

        /* Chaque groupe de champs devient un bloc blanc */
        .tk-dbd-modal > .fi-fo-repeater-item-content > .fi-grid > .fi-grid-col:has(.fi-fo-field-label) {
            background: #fff; border: 1px solid var(--gray-200); border-radius: .5rem; padding: .85rem 1rem 1rem;
        }
        html.dark .tk-dbd-modal > .fi-fo-repeater-item-content > .fi-grid > .fi-grid-col:has(.fi-fo-field-label) { background: var(--gray-900); border-color: var(--gray-700); }
        .tk-dbd-modal .fi-fo-field-label-content { font-size: .75rem; font-weight: 500; color: var(--gray-600); }
        html.dark .tk-dbd-modal .fi-fo-field-label-content { color: var(--gray-400); }

        /* Titres de blocs (pseudo-éléments placés comme cellules de grille pleine largeur) */
        .tk-dbd-modal .tk-h-prestation::before, .tk-dbd-modal .tk-h-facture::before, .tk-dbd-modal .tk-h-date::before, .tk-dbd-modal .tk-h-date::after { display: block; grid-column: 1 / -1; font-size: .875rem; font-weight: 600; color: var(--gray-950); line-height: 1.2; }
        html.dark .tk-dbd-modal .tk-h-prestation::before, html.dark .tk-dbd-modal .tk-h-facture::before, html.dark .tk-dbd-modal .tk-h-date::before, html.dark .tk-dbd-modal .tk-h-date::after { color: var(--gray-50); }
        .tk-dbd-modal .tk-h-prestation::before { content: 'Prestation'; order: -2; }
        .tk-dbd-modal .tk-h-facture::before { content: 'Facture fournisseur'; }
        .tk-dbd-modal .tk-h-date::before { content: 'Date et voyageurs'; order: 0; }
        .tk-dbd-modal .tk-h-date::after { content: 'Prix'; order: 3; padding-top: .9rem; margin-top: .15rem; border-top: 1px solid var(--gray-200); }
        html.dark .tk-dbd-modal .tk-h-date::after { border-color: var(--gray-700); }

        /* Bloc Prestation : produit en premier et plus large */
        .tk-dbd-modal .tk-h-prestation { grid-template-columns: 5fr 4fr 4fr !important; }
        .tk-dbd-modal .tk-h-prestation > .fi-grid-col { grid-column: auto !important; }
        .tk-dbd-modal .tk-h-prestation > .fi-grid-col:nth-child(2) { order: -1; }

        /* Bloc Date et voyageurs + Prix : grille régulière de 4 colonnes */
        .tk-dbd-modal .tk-h-date { grid-template-columns: repeat(4, minmax(0, 1fr)) !important; row-gap: .85rem !important; }
        .tk-dbd-modal .tk-h-date > .fi-grid-col { grid-column: auto !important; order: 1; }
        .tk-dbd-modal .tk-h-date > .fi-grid-col:not(:has(input)) { grid-column: 1 / -1 !important; order: 2; margin-top: -.4rem; font-size: .75rem; }
        .tk-dbd-modal .tk-h-date > .fi-grid-col:has([id$=".purchase_total_price"]),
        .tk-dbd-modal .tk-h-date > .fi-grid-col:has([id$=".unit_price"]),
        .tk-dbd-modal .tk-h-date > .fi-grid-col:has([id$=".total_price"]) { order: 4; }
        .tk-dbd-modal input[id$=".purchase_total_price"], .tk-dbd-modal input[id$=".unit_price"], .tk-dbd-modal input[id$=".total_price"] { text-align: right; font-variant-numeric: tabular-nums; }
        .tk-dbd-modal input[id$=".total_price"]:not([id$="purchase_total_price"]) { font-weight: 600; }

        .tk-dbd-modal .fi-fo-field:has(.fi-fo-repeater) > .fi-fo-field-label-col .fi-fo-field-label-content { font-size: .875rem; font-weight: 600; color: var(--gray-950); }
        html.dark .tk-dbd-modal .fi-fo-field:has(.fi-fo-repeater) > .fi-fo-field-label-col .fi-fo-field-label-content { color: var(--gray-50); }
        /* Sections Filament imbriquées (trajets, réponses) : à plat dans leur bloc */
        .tk-dbd-modal .fi-section { box-shadow: none; }
        .tk-dbd-modal > .fi-fo-repeater-item-content > .fi-grid > .fi-grid-col:has(.fi-section) { background: transparent !important; border: 0 !important; padding: 0 !important; }
        .tk-dbd-modal .fi-section { border-radius: .5rem; }

        @media (max-width: 760px) {
            .tk-dbd-modal .tk-h-prestation, .tk-dbd-modal .tk-h-date { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
            .tk-dbd-modal > .fi-fo-repeater-item-header { position: static; border: 0; padding: .4rem 1rem; justify-content: flex-end; height: auto; }
        }

        /* Options tarifaires imbriquées : une ligne compacte (champ à gauche, actions à droite) */
        .tk-dbd-modal .fi-fo-repeater-item[wire\:key*=".selected_options."] { display: flex !important; flex-direction: row-reverse; align-items: center; background: var(--tk-paper, #f7f6f3); }
        html.dark .tk-dbd-modal .fi-fo-repeater-item[wire\:key*=".selected_options."] { background: var(--gray-800); }
        .tk-dbd-modal .fi-fo-repeater-item[wire\:key*=".selected_options."] > .fi-fo-repeater-item-header { border: 0; padding: .25rem .6rem; gap: .25rem; background: transparent; }
        .tk-dbd-modal .fi-fo-repeater-item[wire\:key*=".selected_options."] > .fi-fo-repeater-item-content { flex: 1; min-width: 0; padding: .55rem .75rem; border: 0; }
        /* Options : toujours dépliées, l'en-tête ne bascule plus rien (seules les actions restent cliquables) */
        .tk-dbd-modal .fi-fo-repeater-item[wire\:key*=".selected_options."] > .fi-fo-repeater-item-content { display: block !important; }
        .tk-dbd-modal .fi-fo-repeater-item[wire\:key*=".selected_options."] > .fi-fo-repeater-item-header { pointer-events: none; }
        .tk-dbd-modal .fi-fo-repeater-item[wire\:key*=".selected_options."] > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-end-actions,
        .tk-dbd-modal .fi-fo-repeater-item[wire\:key*=".selected_options."] > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-end-actions * { pointer-events: auto; }
        .tk-dbd-modal .fi-fo-repeater-item[wire\:key*=".selected_options."] > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-collapsible-actions { display: none !important; }
        /* Jours libres consécutifs regroupés */
        .tk-dbd tr.is-free-range td { padding-top: .3rem; padding-bottom: .3rem; font-size: .75rem; }
        .tk-dbd tr.is-free-range td.tk-j { white-space: nowrap; font-size: .7rem; }
        .tk-dbd:not(.tk-show-free) tr.tk-free-hide { display: none; }
        .tk-dbd-freebox { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; color: var(--gray-600); cursor: pointer; user-select: none; }
        .tk-dbd-freebox input { width: 1rem; height: 1rem; border-radius: .25rem; accent-color: var(--primary-600); }
        html.dark .tk-dbd-freebox { color: var(--gray-300); }
    </style>

    <div class="tk-dbd-bar">
        <div class="tk-dbd-kpis" x-show="mode === 'day'">
            <span><b>{{ $dayCount }}</b> {{ __('jours') }}</span>
            <span><b>{{ $total }}</b> {{ __('prestations') }}</span>
            @if ($counts->get('success'))<span><b>{{ $counts->get('success') }}</b> {{ __('confirmées') }}</span>@endif
            @if ($counts->get('warning'))<span><b>{{ $counts->get('warning') }}</b> {{ __('en attente') }}</span>@endif
            @if ($counts->get('info'))<span><b>{{ $counts->get('info') }}</b> {{ __('en cours') }}</span>@endif
            @if ($cancelledCount)<span style="color:#b91c1c"><b style="color:inherit">{{ $cancelledCount }}</b> {{ __('annulées / sans dispo') }}</span>@endif
        </div>
        <div x-show="mode !== 'day'"></div>
        <div style="display:flex;align-items:center">
            <label class="tk-dbd-freebox"><input type="checkbox" x-bind:checked="showFree" x-on:change="toggleFree()"> {{ __('Afficher les jours libres') }}</label>

        </div>
    </div>

    <div x-show="mode === 'day'">
        @if (empty($groups))
            <div class="tk-dbd-empty">{{ __('Aucune prestation pour le moment. Renseignez les dates du séjour ou ajoutez une prestation.') }}</div>
        @else
            <div class="tk-dbd-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('J') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Prestation') }}</th>
                            <th class="tk-c">{{ __('Qté') }}</th>
                            <th>{{ __('Fournisseur') }}</th>
                            <th>{{ __('Statut') }}</th>
                            <th class="tk-r">{{ __('Achat ¥') }}</th>
                            <th class="tk-r">{{ __('Vente ¥') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $day)
                            @if ($day['type'] === 'free' && $day['count'] > 1)
                                @php
                                    $fromLabel = \Illuminate\Support\Str::ucfirst($day['from']['date']->copy()->locale($locale)->isoFormat('ddd DD/MM'));
                                    $toLabel = \Illuminate\Support\Str::ucfirst($day['to']['date']->copy()->locale($locale)->isoFormat('ddd DD/MM'));
                                @endphp
                                <tr class="is-day is-free is-free-range tk-free-hide">
                                    <td class="tk-j">{{ $day['from']['number'] }}–{{ $day['to']['number'] }}</td>
                                    <td class="tk-d" style="white-space:normal">{{ $fromLabel }} → {{ $toLabel }}</td>
                                    <td colspan="7">— {{ $day['count'] }} {{ __('journées libres') }}</td>
                                </tr>
                                @continue
                            @endif
                            @php
                                if ($day['type'] === 'free') {
                                    $day = $day['from'];
                                }
                                $isWeekend = $day['date'] && $day['date']->isWeekend();
                                $flags = [];
                                if ($day['is_start']) $flags[] = '✈️ ' . __('Arrivée');
                                if ($day['is_end']) $flags[] = '✈️ ' . __('Départ');
                                $dayLabel = $day['date'] ? \Illuminate\Support\Str::ucfirst($day['date']->copy()->locale($locale)->isoFormat('ddd DD/MM')) : __('Sans date');
                                $rowCount = max(1, count($day['rows']));
                            @endphp

                            @if (empty($day['rows']))
                                <tr class="is-day is-free {{ $isWeekend ? 'is-we' : '' }} {{ empty($flags) ? 'tk-free-hide' : '' }}">
                                    <td class="tk-j">{{ $day['number'] }}</td>
                                    <td class="tk-d">{{ $dayLabel }}</td>
                                    <td colspan="7">
                                        @foreach ($flags as $flag)<span class="tk-flag">{{ $flag }}</span>@endforeach
                                        — {{ __('journée libre') }}
                                    </td>
                                </tr>
                            @else
                                @foreach ($day['rows'] as $i => $row)
                                    <tr
                                        class="is-item {{ $i === 0 ? 'is-day' : '' }} {{ $isWeekend ? 'is-we' : '' }} {{ $row['cancelled'] ? 'is-cancelled' : '' }}"
                                        x-on:click="open(@js($row['repeater']), @js($row['key']), @js($row['name']), @js(($day['date'] ? $dayLabel : __('Sans date')) . ' · ' . $row['status_name']))"
                                        title="{{ __('Ouvrir la fiche de la prestation') }}"
                                    >
                                        @if ($i === 0)
                                            <td class="tk-j" rowspan="{{ $rowCount }}">{{ $day['number'] ?? '—' }}</td>
                                            <td class="tk-d" rowspan="{{ $rowCount }}">{{ $dayLabel }}</td>
                                        @endif
                                        <td class="tk-p">
                                            @if ($i === 0)
                                                @foreach ($flags as $flag)<span class="tk-flag">{{ $flag }}</span>@endforeach
                                            @endif
                                            <span class="tk-name">{{ $row['name'] }}</span>
                                            @if ($row['internal'])
                                                <span class="tk-lock">🔒 {{ __('interne') }}</span>
                                            @endif
                                            @if (filled($row['options']) && ! (! empty($row['routes']) && $row['options'] === __('Sans option')))
                                                <span class="tk-opt">{{ $row['options'] }}</span>
                                            @endif
                                            @foreach ($row['routes'] ?? [] as $route)
                                                <span class="tk-route"><b>{{ $route['from'] }} → {{ $route['to'] }}</b>@if ($route['details'])<span> · {{ $route['details'] }}</span>@endif</span>
                                            @endforeach
                                        </td>
                                        <td class="tk-c">{{ $row['quantity'] }}</td>
                                        <td>
                                            {{ $row['supplier'] ?? ($row['internal'] ? '' : '—') }}
                                            @if ($row['awaiting_invoice'])
                                                <span class="tk-alert-invoice">⚠️ {{ __('En attente de facture') }}</span>
                                            @endif
                                            @if ($row['must_select_supplier'])
                                                <span class="tk-alert-supplier">⚠️ {{ __('SÉLECTIONNER UN FOURNISSEUR') }}</span>
                                            @endif
                                        </td>
                                        <td class="tk-st"><span class="tk-badge tk-{{ $row['status_tone'] }}">{{ $row['status_name'] }}</span></td>
                                        <td class="tk-r tk-buy">{{ $fmtPrice($row['purchase']) }}</td>
                                        <td class="tk-r">{{ $row['internal'] ? '—' : $fmtPrice($row['price']) }}</td>
                                        <td class="tk-r" x-on:click.stop>
                                            <span class="tk-acts">
                                                @if (! $row['internal'])
                                                    @if ($row['has_mail'])
                                                    <button type="button" class="is-mail"title="{{ __('Aperçu de l\'email fournisseur') }}" x-on:click="act(@js($row['repeater']), @js($row['key']), 'generateSupplierEmail')"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg></button>
                                                    @endif
                                                    @if ($row['has_fax'])
                                                    <button type="button" class="is-fax"title="{{ __('Ouvrir un nouveau FAX dans Google Sheets') }}" x-on:click="act(@js($row['repeater']), @js($row['key']), 'generateGoogleSheetFax')"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg></button>
                                                    @endif
                                                @endif
                                                <button type="button" class="is-del"title="{{ __('Supprimer') }}" x-on:click="act(@js($row['repeater']), @js($row['key']), 'delete')"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="tk-r">{{ __('Totaux (hors annulations)') }}</td>
                            <td class="tk-r tk-buy">{{ $fmtPrice($purchaseTotal) }}</td>
                            <td class="tk-r">{{ $fmtPrice($billable) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

    <template x-teleport="body">
        <div x-show="current" x-cloak>
            <div class="tk-dbd-backdrop" x-on:click="close()"></div>
            <div class="tk-dbd-mbar">
                <div class="tk-mtitle">
                    <strong x-text="current ? current.name : ''"></strong>
                    <span x-text="current ? current.sub : ''"></span>
                </div>
                <div class="tk-mbtns">
                    <button type="button" class="is-primary" x-on:click="save()">{{ __('Enregistrer le dossier') }}</button>
                    <button type="button" x-on:click="close()">{{ __('Fermer') }} ✕</button>
                </div>
            </div>
        </div>
    </template>
</div>
