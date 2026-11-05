<style>
    /*
     * Thèmes inspirés des supports Takada Travel (flyers, cartes de visite) :
     * - clair : blanc cassé « papier », textes encre, rouge laque réservé aux actions principales ;
     * - foncé : quasi-noir teinté bleu nuit, accents crème discrets.
     */
    :root {
        --tk-ink: #1c1f26;
        --tk-night: #0b0f17;
        --tk-cream: #e8e2b0;
        --tk-paper: #f7f6f3;
        /* Accent des écrans personnalisés : rouge laque en clair, crème en foncé */
        --tk-accent: var(--primary-700);
        --tk-accent-soft: #f3f1ec;
        --tk-accent-border: #e4e0d6;
        /* Arrondis un peu plus sobres sur l'ensemble du site */
        --radius-lg: 0.375rem;
        --radius-xl: 0.5rem;
        --radius-2xl: 0.75rem;
        --radius-3xl: 1rem;
    }

    /* Palette nuit : remplace les gris en thème foncé */
    html.dark {
        --gray-50: #f6f7f9;
        --gray-100: #eceef2;
        --gray-200: #d9dde4;
        --gray-300: #bcc2cd;
        --gray-400: #98a0ae;
        --gray-500: #6d7687;
        --gray-600: #474f60;
        --gray-700: #2a3142;
        --gray-800: #1a2030;
        --gray-900: #121725;
        --gray-950: #0b0f17;

        /* Textes, liens et éléments actifs en crème ; les fonds de boutons (600) restent rouges */
        --primary-50: #fbfaf1;
        --primary-100: #f6f3dd;
        --primary-200: #efeac6;
        --primary-300: #ebe5ba;
        --primary-400: #e8e2b0;

        --tk-accent: var(--tk-cream);
        --tk-accent-soft: color-mix(in srgb, var(--tk-cream) 6%, var(--gray-900));
        --tk-accent-border: var(--gray-700);
    }

    html:not(.dark) .fi-body {
        background-color: var(--tk-paper);
    }

    /* Bouton de bascule clair / foncé, mis en avant dans la barre du haut */
    .tk-theme-toggle {
        display: inline-flex;
        align-items: center;
        padding: 0.4rem 0.85rem;
        border-radius: 999px;
        border: 1px solid transparent;
        font-size: 0.8rem;
        font-weight: 600;
        line-height: 1;
        white-space: nowrap;
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        /* En clair : aperçu du thème nuit */
        background-color: var(--tk-night);
        color: var(--tk-cream);
        box-shadow: 0 1px 3px rgba(11, 15, 23, 0.25);
    }

    .tk-theme-toggle:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(11, 15, 23, 0.25);
    }

    .tk-theme-toggle:focus-visible {
        outline: 2px solid var(--primary-600);
        outline-offset: 2px;
    }

    /* En foncé : aperçu du thème papier */
    .dark .tk-theme-toggle {
        background-color: var(--tk-paper);
        color: var(--tk-ink);
        box-shadow: none;
    }

    .tk-theme-toggle-inner {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
    }

    .tk-theme-toggle svg {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        flex: none;
    }

    /* Le bouton de la barre du haut suffit : on retire le sélecteur de thème du menu utilisateur */
    .fi-user-menu .fi-dropdown-list:has(> .fi-theme-switcher),
    .fi-dropdown-panel .fi-dropdown-list:has(> .fi-theme-switcher) {
        display: none;
    }

    /* Variante discrète : icône seule, comme les autres boutons de la barre */
    .tk-theme-toggle.tk-theme-toggle--subtle,
    .dark .tk-theme-toggle.tk-theme-toggle--subtle {
        padding: 0.5rem;
        background-color: transparent;
        color: var(--gray-400);
        box-shadow: none;
    }

    .tk-theme-toggle.tk-theme-toggle--subtle:hover {
        transform: none;
        box-shadow: none;
        color: var(--gray-500);
        background-color: var(--gray-100);
    }

    .dark .tk-theme-toggle.tk-theme-toggle--subtle:hover {
        color: var(--gray-300);
        background-color: rgba(255, 255, 255, 0.05);
    }

    .tk-theme-toggle--subtle .tk-theme-toggle-label {
        display: none;
    }

    .tk-theme-toggle--subtle svg {
        width: 1.25rem;
        height: 1.25rem;
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
