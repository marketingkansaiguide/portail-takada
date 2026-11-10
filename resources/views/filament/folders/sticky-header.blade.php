{{-- En-tête collant de la page dossier. Données : App\Filament\Resources\Folders\FolderStickyHeader. --}}
@php
    $money = fn (float $v): string => number_format($v, 0, '.', ' ') . ' ¥';
@endphp

<style>
    .tk-dsh {
        box-sizing: border-box;
        min-width: 0;
        max-width: 100%;
        margin: 0 0 1rem;
        padding: .5rem .875rem .375rem;
        background: #fff;
        border: 1px solid var(--gray-200, #e5e7eb);
        border-radius: .5rem;
        box-shadow: 0 4px 10px -6px rgba(15, 23, 42, .18);
        color: var(--gray-900, #111827);
        font-size: .8rem;
    }
    .tk-dsh-line { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; min-width: 0; }
    .tk-dsh-ident { display: flex; align-items: baseline; gap: .5rem; flex: 1 1 auto; min-width: 0; flex-wrap: wrap; }
    .tk-dsh-ref { font-weight: 700; white-space: nowrap; }
    .tk-dsh-name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 16rem; }
    .tk-dsh-meta, .tk-dsh-sep { color: var(--gray-500, #6b7280); white-space: nowrap; }
    .tk-dsh-totals { display: flex; gap: .875rem; flex-wrap: wrap; color: var(--gray-500, #6b7280); }
    .tk-dsh-totals b { color: var(--gray-900, #111827); font-weight: 700; }
    .tk-dsh-margin.is-pos b { color: #16a34a; }
    .tk-dsh-margin.is-neg b { color: #dc2626; }
    .tk-dsh-save {
        background: var(--primary-600, #a32035); color: #fff; border: 0; border-radius: .5rem;
        padding: .375rem .875rem; font-weight: 600; font-size: .8rem; cursor: pointer; white-space: nowrap;
    }
    .tk-dsh-save:hover { filter: brightness(.92); }

    .tk-dsh-nav {
        display: flex; gap: .25rem; margin-top: .375rem; padding-top: .375rem;
        border-top: 1px solid var(--gray-100, #f3f4f6);
        overflow-x: auto; overflow-y: hidden; white-space: nowrap;
        max-width: 100%; scrollbar-width: none;
    }
    .tk-dsh-nav::-webkit-scrollbar { display: none; }
    .tk-dsh-link {
        flex: 0 0 auto; padding: .25rem .5rem; border: 0; background: none; cursor: pointer;
        color: var(--gray-700, #374151); font-weight: 500; font-size: .8rem;
        border-bottom: 2px solid transparent; border-radius: .25rem .25rem 0 0;
    }
    .tk-dsh-link:hover { color: var(--primary-600, #a32035); }
    .tk-dsh-link.is-active {
        color: var(--primary-600, #a32035); font-weight: 700; border-bottom-color: var(--primary-600, #a32035);
    }

    html.dark .tk-dsh { background: var(--gray-900, #111827); border-color: var(--gray-700, #374151); color: #f3f4f6; }
    html.dark .tk-dsh-totals, html.dark .tk-dsh-meta, html.dark .tk-dsh-sep { color: var(--gray-400, #9ca3af); }
    html.dark .tk-dsh-totals b { color: #f3f4f6; }
    html.dark .tk-dsh-nav { border-top-color: var(--gray-700, #374151); }
    html.dark .tk-dsh-link { color: var(--gray-300, #d1d5db); }
    html.dark .tk-dsh-link.is-active { color: #f87171; border-bottom-color: #f87171; }

    @media (max-width: 640px) {
        .tk-dsh-name { max-width: 10rem; }
        .tk-dsh-save { margin-left: auto; }
    }
</style>

<div
    class="tk-dsh"
    x-data="{
        present: {},
        active: '',
        links: [
            { key: 'informations', label: 'Informations' },
            { key: 'voyageurs', label: 'Voyageurs' },
            { key: 'vol', label: 'Vol & séjour' },
            { key: 'facturation', label: 'Facturation' },
            { key: 'documents', label: 'Documents' },
            { key: 'prestations', label: 'Prestations', count: {{ $countItems + $countInternal }} },
            { key: 'suivi', label: 'Suivi & messagerie' },
        ],
        init() {
            // Le collant doit être la colonne de grille posée directement dans la grille
            // du formulaire (les wrappers Filament autour du Placeholder sont trop petits).
            let col = this.$el;
            while (col.parentElement && !col.parentElement.classList.contains('fi-sc')) { col = col.parentElement; }
            if (col.parentElement) {
                col.style.position = 'sticky';
                col.style.top = '64px';
                col.style.zIndex = '25';
            }
            this.links.forEach(l => {
                this.present[l.key] = !!document.querySelector('[data-tk-anchor=' + l.key + ']');
            });
            if (window.tkHeaderObserver) { window.tkHeaderObserver.disconnect(); }
            window.tkHeaderRefresh = () => this.refresh();
            window.tkHeaderObserver = new IntersectionObserver(() => this.refresh(), { rootMargin: '-140px 0px -60% 0px', threshold: 0 });
            document.querySelectorAll('[data-tk-anchor]').forEach(el => window.tkHeaderObserver.observe(el));
            if (!window.__tkHeaderScroll) {
                window.__tkHeaderScroll = true;
                window.addEventListener('scroll', () => window.tkHeaderRefresh && window.tkHeaderRefresh(), { passive: true });
            }
            this.refresh();
        },
        // Section active = la dernière dont le haut est passé sous la barre.
        refresh() {
            if (window.__tkScrolling) { return; }
            // Ligne de lecture = bas de la barre collée (position fixe, pas sa place dans le flux).
            const line = 64 + this.$el.offsetHeight + 12;
            let best = '';
            document.querySelectorAll('[data-tk-anchor]').forEach(el => {
                if (el.getBoundingClientRect().top <= line) { best = el.dataset.tkAnchor; }
            });
            this.active = best;
        },
        go(key) {
            const el = document.querySelector('[data-tk-anchor=' + key + ']');
            if (!el) { return; }
            const bar = this.$el.offsetHeight;
            // Pendant le défilement animé, le suivi de position est suspendu (sinon la position
            // cible est recalculée en cours de route et le défilement dérive).
            window.__tkScrolling = true;
            clearTimeout(window.__tkScrollTimer);
            window.__tkScrollTimer = setTimeout(() => {
                window.__tkScrolling = false;
                window.tkHeaderRefresh && window.tkHeaderRefresh();
            }, 1000);
            window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - 64 - bar - 12, behavior: 'smooth' });
            this.active = key;
        },
        save() {
            this.$el.closest('form').requestSubmit();
        },
    }"
>
    @if (! empty($urgentNote))
        <div class="tk-dsh-urgent" role="alert" style="margin-bottom:.375rem;padding:.375rem .625rem;border:1px solid #f87171;background:#fef2f2;color:#991b1b;border-radius:.375rem;font-weight:600">⚠ {{ __('URGENT billetterie') }} : {{ $urgentNote }}</div>
    @endif
    @if (! empty($generalNotes))
        <details class="tk-dsh-notes" @if (mb_strlen($generalNotes) < 160 && substr_count($generalNotes, "\n") < 2) open @endif style="margin-bottom:.375rem;padding:.375rem .625rem;border:1px solid var(--gray-200,#e5e7eb);border-radius:.375rem;background:#f9fafb">
            <summary style="cursor:pointer;font-weight:600">{{ __('Consignes du commercial') }}</summary>
            <div style="white-space:pre-wrap;margin-top:.25rem">{{ $generalNotes }}</div>
        </details>
    @endif
    <div class="tk-dsh-line">
        <div class="tk-dsh-ident">
            <span class="tk-dsh-ref">{{ $reference }}</span>
            <span class="tk-dsh-name" title="{{ $name }}">{{ $name }}</span>
            <span class="tk-dsh-sep">·</span>
            <span class="tk-dsh-meta">{{ $dates }}</span>
            <span class="tk-dsh-sep">·</span>
            <span class="tk-dsh-meta">{{ $pax }} pax</span>
        </div>
        <div class="tk-dsh-totals">
            <span>Vente <b>{{ $money($sale) }}</b></span>
            <span class="tk-dsh-sep">·</span>
            <span>Achat <b>{{ $money($cost) }}</b></span>
            <span class="tk-dsh-sep">·</span>
            <span class="tk-dsh-margin {{ $margin >= 0 ? 'is-pos' : 'is-neg' }}">Marge <b>{{ $money($margin) }}</b></span>
        </div>
        <button type="button" class="tk-dsh-save" @click="save()" wire:loading.attr="disabled">Enregistrer</button>
    </div>

    <nav class="tk-dsh-nav" aria-label="Sections du dossier">
        <template x-for="l in links" :key="l.key">
            <button
                type="button"
                class="tk-dsh-link"
                :class="{ 'is-active': active === l.key }"
                x-show="present[l.key]"
                @click="go(l.key)"
                x-text="l.count !== undefined ? l.label + ' (' + l.count + ')' : l.label"
            ></button>
        </template>
    </nav>
</div>
