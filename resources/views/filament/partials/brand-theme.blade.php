<style>
    /*
     * Identité Takada Travel (flyers, cartes de visite) :
     * - thème clair « papier » : fond blanc cassé, gris chauds, rouge Takada ;
     * - thème foncé « marine » : bleu marine #003170, textes et accents crème #eee8aa.
     * Le rouge des boutons reste le même dans les deux thèmes, comme le logo 旅.
     */
    :root {
        --tk-red: #c91f37;
        --tk-navy: #003170;
        --tk-cream: #eee8aa;
        --tk-paper: #f4f2ed;
        /* Accent des écrans personnalisés : rouge en clair, crème en foncé */
        --tk-accent: var(--primary-600);
        --tk-accent-soft: var(--primary-50);
        --tk-accent-border: var(--primary-200);
    }

    /* Palette marine : remplace les gris en thème foncé */
    html.dark {
        --gray-50: #f4f6fa;
        --gray-100: #e6ebf4;
        --gray-200: #d0d9ea;
        --gray-300: #b4c2db;
        --gray-400: #96a9cc;
        --gray-500: #8098c2;
        --gray-600: #4b6596;
        --gray-700: #2c4c82;
        --gray-800: #133d7a;
        --gray-900: #003170;
        --gray-950: #00214d;

        /* Textes, liens et éléments actifs en crème ; les fonds de boutons (600) restent rouges */
        --primary-50: #fdfcf3;
        --primary-100: #faf8e3;
        --primary-200: #f6f2cf;
        --primary-300: #f2edbd;
        --primary-400: #eee8aa;

        --tk-accent: var(--tk-cream);
        --tk-accent-soft: color-mix(in srgb, var(--tk-cream) 12%, var(--gray-900));
        --tk-accent-border: var(--gray-600);
    }

    html:not(.dark) .fi-body {
        background-color: var(--tk-paper);
    }

    /* Titres de page : rouge en clair, crème en foncé, comme sur les supports commerciaux */
    .fi-header-heading {
        color: var(--tk-red);
    }

    .dark .fi-header-heading {
        color: var(--tk-cream);
    }

    /* Bouton de bascule clair / foncé, mis en avant dans la barre du haut */
    .tk-theme-toggle {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.4rem 0.85rem;
        border-radius: 999px;
        border: 1px solid transparent;
        font-size: 0.8rem;
        font-weight: 700;
        line-height: 1;
        white-space: nowrap;
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        /* En clair : aperçu du thème marine */
        background-color: var(--tk-navy);
        color: var(--tk-cream);
        box-shadow: 0 2px 6px rgba(0, 49, 112, 0.25);
    }

    .tk-theme-toggle:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(0, 49, 112, 0.3);
    }

    .tk-theme-toggle:focus-visible {
        outline: 2px solid var(--tk-red);
        outline-offset: 2px;
    }

    /* En foncé : aperçu du thème papier */
    .dark .tk-theme-toggle {
        background-color: var(--tk-paper);
        color: var(--tk-red);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
    }

    .tk-theme-toggle-inner {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
    }

    .tk-theme-toggle-inner[style*="display: none"] {
        display: none;
    }

    .tk-theme-toggle svg {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        flex: none;
    }

    @media (max-width: 640px) {
        .tk-theme-toggle-label {
            display: none;
        }

        .tk-theme-toggle {
            padding: 0.45rem;
        }
    }
</style>
