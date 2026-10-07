<style>
    /*
     * Les écrans administratifs historiques contiennent des couleurs définies
     * directement dans leurs attributs style. Ces règles les adaptent au mode
     * sombre. Ce fichier est chargé par les panels admin et agence ; la portée
     * « .dark » couvre aussi les modales et les panneaux latéraux.
     * Les survols JavaScript réécrivent les styles au format rgb(...), d'où les
     * variantes rgb de certaines couleurs.
     * Chaque couleur est listée avec et sans espace après les deux-points.
     */

    /* Textes foncés -> texte clair */
    .dark :is(
        [style*="color: #111827"], [style*="color:#111827"],
        [style*="color: #1f2937"], [style*="color:#1f2937"],
        [style*="color: #1e293b"], [style*="color:#1e293b"],
        [style*="color: #0f172a"], [style*="color:#0f172a"],
        [style*="color: #334155"], [style*="color:#334155"],
        [style*="color: #374151"], [style*="color:#374151"],
        [style*="color: #475569"], [style*="color:#475569"],
        [style*="color: #4b5563"], [style*="color:#4b5563"]
    ) {
        color: #f1f5f9 !important;
    }

    /* Textes secondaires -> gris clair */
    .dark :is(
        [style*="color: #64748b"], [style*="color:#64748b"],
        [style*="color: #6b7280"], [style*="color:#6b7280"],
        [style*="color: #94a3b8"], [style*="color:#94a3b8"],
        [style*="color: #9ca3af"], [style*="color:#9ca3af"],
        [style*="color: gray"], [style*="color:gray"]
    ) {
        color: #cbd5e1 !important;
    }

    /* Ambre / orange foncé */
    .dark :is(
        [style*="color: #92400e"], [style*="color:#92400e"],
        [style*="color: #78350f"], [style*="color:#78350f"],
        [style*="color: #a16207"], [style*="color:#a16207"],
        [style*="color: #b45309"], [style*="color:#b45309"],
        [style*="color: #d97706"], [style*="color:#d97706"]
    ) {
        color: #fde68a !important;
    }

    /* Orange */
    .dark :is(
        [style*="color: #c2410c"], [style*="color:#c2410c"],
        [style*="color: #9a3412"], [style*="color:#9a3412"],
        [style*="color: #ea580c"], [style*="color:#ea580c"]
    ) {
        color: #fdba74 !important;
    }

    /* Rouges */
    .dark :is(
        [style*="color: #991b1b"], [style*="color:#991b1b"],
        [style*="color: #9f1239"], [style*="color:#9f1239"],
        [style*="color: #b91c1c"], [style*="color:#b91c1c"],
        [style*="color: #dc2626"], [style*="color:#dc2626"],
        [style*="color: #ef4444"], [style*="color:#ef4444"],
        [style*="color:red"], [style*="color: red"]
    ) {
        color: #fca5a5 !important;
    }

    /* Teal / émeraude */
    .dark :is(
        [style*="color: #115e59"], [style*="color:#115e59"],
        [style*="color: #0f766e"], [style*="color:#0f766e"],
        [style*="color: #096a61"], [style*="color:#096a61"]
    ):not([style*="background-color: #096a61"]):not([style*="background-color: #0f766e"]) {
        color: #5eead4 !important;
    }

    .dark :is(
        [style*="color: #166534"], [style*="color:#166534"],
        [style*="color: #15803d"], [style*="color:#15803d"],
        [style*="color: #16a34a"], [style*="color:#16a34a"],
        [style*="color: #047857"], [style*="color:#047857"],
        [style*="color: #059669"], [style*="color:#059669"]
    ) {
        color: #86efac !important;
    }

    /* Bleus */
    .dark :is(
        [style*="color: #1e3a8a"], [style*="color:#1e3a8a"],
        [style*="color: #1d4ed8"], [style*="color:#1d4ed8"],
        [style*="color: #2563eb"], [style*="color:#2563eb"],
        [style*="color: #0284c7"], [style*="color:#0284c7"],
        [style*="color: #0369a1"], [style*="color:#0369a1"]
    ) {
        color: #93c5fd !important;
    }

    /* Fonds blancs / gris très clairs -> ardoise */
    .dark :is(
        [style*="background: #ffffff"], [style*="background:#ffffff"],
        [style*="background-color: #ffffff"], [style*="background-color:#ffffff"],
        [style*="background: #fff;"], [style*="background:#fff;"],
        [style$="background: #fff"], [style$="background:#fff"],
        [style*="background: white"], [style*="background:white"],
        [style*="background-color: white"], [style*="background-color:white"],
        [style*="background: #f8fafc"], [style*="background:#f8fafc"],
        [style*="background-color: #f8fafc"], [style*="background-color:#f8fafc"],
        [style*="background: #f1f5f9"], [style*="background:#f1f5f9"],
        [style*="background-color: #f1f5f9"], [style*="background-color:#f1f5f9"],
        [style*="background: #f9fafb"], [style*="background:#f9fafb"],
        [style*="background-color: #f9fafb"], [style*="background-color:#f9fafb"],
        [style*="background: #f3f4f6"], [style*="background:#f3f4f6"],
        [style*="background-color: #f3f4f6"], [style*="background-color:#f3f4f6"],
        [style*="background-color: #fafafa"], [style*="background-color:#fafafa"],
        [style*="background: rgba(255,255,255,.35)"],
        [style*="background: rgba(255,255,255,.78)"],
        [style*="background: rgb(255, 255, 255)"],
        [style*="background-color: rgb(255, 255, 255)"]
    ) {
        background-color: #1e293b !important;
    }

    .dark :is(
        [style*="background: #e5e7eb"], [style*="background:#e5e7eb"],
        [style*="background-color: #e5e7eb"], [style*="background-color:#e5e7eb"],
        [style*="background: #e2e8f0"], [style*="background:#e2e8f0"],
        [style*="background-color: #e2e8f0"], [style*="background-color:#e2e8f0"]
    ) {
        background-color: #334155 !important;
    }

    /* Fond ambre clair */
    .dark :is(
        [style*="background: #fef3c7"], [style*="background:#fef3c7"],
        [style*="background-color: #fef3c7"], [style*="background-color:#fef3c7"],
        [style*="background: #fffbeb"], [style*="background:#fffbeb"],
        [style*="background-color: #fffbeb"], [style*="background-color:#fffbeb"],
        [style*="background: #fff7ed"], [style*="background:#fff7ed"],
        [style*="background-color: #fff7ed"], [style*="background-color:#fff7ed"]
    ) {
        background-color: #451a03 !important;
    }

    /* Fond rouge clair */
    .dark :is(
        [style*="background: #fef2f2"], [style*="background:#fef2f2"],
        [style*="background-color: #fef2f2"], [style*="background-color:#fef2f2"],
        [style*="background: #fee2e2"], [style*="background:#fee2e2"],
        [style*="background-color: #fee2e2"], [style*="background-color:#fee2e2"]
    ) {
        background-color: #4c0519 !important;
    }

    /* Fond teal / vert clair */
    .dark :is(
        [style*="background: #f0fdfa"], [style*="background:#f0fdfa"],
        [style*="background-color: #f0fdfa"], [style*="background-color:#f0fdfa"],
        [style*="background: #ecfdf5"], [style*="background:#ecfdf5"],
        [style*="background-color: #ecfdf5"], [style*="background-color:#ecfdf5"],
        [style*="background: #f0fdf4"], [style*="background:#f0fdf4"],
        [style*="background-color: #f0fdf4"], [style*="background-color:#f0fdf4"],
        [style*="background: rgb(240, 253, 250)"], [style*="background-color: rgb(240, 253, 250)"]
    ) {
        background-color: #134e4a !important;
    }

    /* Fond bleu clair */
    .dark :is(
        [style*="background: #f0f9ff"], [style*="background:#f0f9ff"],
        [style*="background: #e0f2fe"], [style*="background:#e0f2fe"],
        [style*="background: #eff6ff"], [style*="background:#eff6ff"],
        [style*="background-color: #eff6ff"], [style*="background-color:#eff6ff"]
    ) {
        background-color: #0c2d48 !important;
    }

    /* Bordures claires -> visibles sur fond sombre */
    .dark :is(input, select, textarea)[style],
    .dark :is(
        [style*="solid #cbd5e1"], [style*="solid #d1d5db"],
        [style*="solid #e2e8f0"], [style*="solid #e5e7eb"],
        [style*="solid #f1f5f9"], [style*="solid #f3f4f6"],
        [style*="solid #99f6e4"], [style*="solid #a7f3d0"],
        [style*="solid #fde68a"], [style*="solid #fecaca"],
        [style*="solid #dbeafe"], [style*="dashed #cbd5e1"],
        [style*="dashed #e2e8f0"], [style*="solid #ffedd5"],
        [style*="solid #fed7aa"], [style*="solid #bae6fd"],
        [style*="solid #e0f2fe"], [style*="solid #bfdbfe"],
        [style*="solid #bbf7d0"], [style*="solid #86efac"],
        [style*="solid #ccfbf1"], [style*="solid #fef3c7"],
        [style*="border-color: #e2e8f0"], [style*="border-color: #cbd5e1"],
        [style*="border-color: rgb(203, 213, 225)"]
    ) {
        border-color: #475569 !important;
    }
</style>
