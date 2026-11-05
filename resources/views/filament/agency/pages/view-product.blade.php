<x-filament-panels::page>
    <style>
        .gallery-thumb { flex-shrink: 0; width: 84px; height: 112px; border-radius: 0.75rem; overflow: hidden; cursor: pointer; border: 3px solid transparent; opacity: 0.6; transition: all 0.2s ease-in-out; }
        .gallery-thumb:hover { opacity: 1; }
        .gallery-thumb.active { border-color: var(--primary-600); opacity: 1; transform: scale(1.05); }

        .calendar-cell { min-height: 60px; border-radius: 0.5rem; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 0.25rem 0; border: 1px solid transparent; transition: all 0.15s ease-in-out; overflow: hidden; }
        .calendar-cell-available { background: #ffffff; border-color: #e2e8f0; cursor: pointer; }
        .calendar-cell-available:hover { border-color: var(--primary-600); background: var(--tk-accent-soft); }
        .calendar-cell-selected { background: var(--primary-600) !important; border-color: var(--primary-600) !important; box-shadow: 0 4px 6px -1px color-mix(in srgb, var(--primary-600) 40%, transparent); }
        .calendar-cell-disabled { background: #f8fafc; border-color: transparent; cursor: not-allowed; opacity: 0.4; }
        .calendar-day-number { font-weight: 700; font-size: 1rem; line-height: 1.1; }
        .calendar-price-text { font-size: 0.65rem; font-weight: 700; line-height: 1.1; margin-top: 0.2rem; white-space: nowrap; }

        .pax-tabs-row::-webkit-scrollbar { display: none; }
        .pax-tabs-row { -ms-overflow-style: none; scrollbar-width: none; }
        
        .pax-tab-item {
            flex: 1;
            min-width: 80px;
            padding: 0.5rem;
            border-radius: 0.5rem;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #475569;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
        }
        .pax-tab-item:hover {
            border-color: var(--primary-600);
            background: #f8fafc;
            color: var(--tk-accent);
        }
        .pax-tab-item-active {
            border-color: var(--primary-600) !important;
            background: var(--tk-accent-soft) !important;
            color: var(--tk-accent) !important;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .animate-spin-custom { animation: spin 1s linear infinite; }

        /* Étapes compactes : la fiche reste volontairement sur une seule colonne. */
        .product-config-section { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.85rem 1rem; margin-bottom: 0.75rem; }
        .product-config-section-title { display: flex; align-items: center; gap: 0.45rem; margin: 0 0 0.6rem; color: var(--tk-accent); font-size: 0.72rem; font-weight: 800; letter-spacing: 0.055em; text-transform: uppercase; }
        .product-config-section-title::before { content: ''; width: 0.45rem; height: 0.45rem; border-radius: 999px; background: var(--primary-600); flex: 0 0 auto; }
        .product-variant-group { padding: 0; border: 0; background: transparent; }
        .product-required-fields { background: #f8fafc; border-color: #cbd5e1; }
        .product-price-summary { background: var(--tk-accent-soft); border-color: var(--tk-accent-border); }
        .product-final-step { border-color: var(--tk-accent-border); background: var(--tk-accent-soft); }
        /* Mode sombre */
        .dark .calendar-cell-available { background: var(--gray-800); border-color: var(--gray-700); }
        .dark .calendar-cell-available:hover { background: var(--tk-accent-soft); }
        .dark .calendar-cell-disabled { background: var(--gray-950); }
        .dark .pax-tab-item { background: var(--gray-800); border-color: var(--gray-600); color: var(--gray-300); }
        .dark .pax-tab-item:hover { background: var(--gray-950); color: var(--tk-accent); }
        .dark .pax-tab-item-active { background: var(--tk-accent-soft) !important; color: var(--tk-accent) !important; border-color: var(--tk-accent) !important; }
        .dark .product-config-section { background: var(--gray-900); border-color: var(--gray-700); }
        .dark .product-config-section-title { color: var(--tk-accent); }
        .dark .product-required-fields { background: var(--gray-800); border-color: var(--gray-700); }
        .dark .product-price-summary { background: var(--tk-accent-soft); border-color: var(--gray-600); }
        .dark .product-final-step { background: var(--gray-900); border-color: var(--gray-700); }
        /* Mise en page : une colonne sur mobile / tablette, contenu + réservation côte à côte sur grand écran */
        .product-layout { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.5rem; }
        .product-layout-main, .product-layout-side { min-width: 0; }
        .product-title { font-size: clamp(1.75rem, 6vw, 2.5rem); line-height: 1.15; overflow-wrap: anywhere; }
        .product-gallery { height: clamp(240px, 62vw, 540px); }
        .product-card-lg { padding: clamp(1.1rem, 4vw, 1.75rem); }
        .product-card-side { padding: clamp(0.85rem, 3vw, 1.25rem); }
        @media (min-width: 1280px) {
            /* Colonnes presque égales : la réservation garde de la place et reste courte */
            .product-layout { grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); gap: 2rem; align-items: start; }
            .product-gallery { height: auto; aspect-ratio: 4 / 3; max-height: 60vh; }
            /* La colonne de gauche accompagne le défilement quand elle tient dans l'écran (classe posée par le script ci-dessous) */
            .product-layout-main.is-sticky { position: sticky; top: 5rem; }
            .product-layout-side > div { position: static !important; }
        }
        /* Étapes numérotées dans le bloc de réservation */
        .product-card-side { counter-reset: booking-step; }
        .product-card-side .product-config-section-title::before {
            counter-increment: booking-step; content: counter(booking-step);
            width: 1.15rem; height: 1.15rem; display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.65rem; font-weight: 800; letter-spacing: 0; color: #ffffff; background: var(--primary-600);
        }
        .product-final-step .fi-btn { white-space: nowrap; }
        /* Barre de réservation fixe sur mobile / tablette */
        .product-mobile-bar { display: none; }
        @media (max-width: 1279px) {
            .product-layout { padding-bottom: 4.5rem; }
            .product-mobile-bar {
                display: flex; align-items: center; justify-content: space-between; gap: 1rem;
                position: fixed; left: 0; right: 0; bottom: 0; z-index: 30;
                padding: 0.65rem 1rem calc(0.65rem + env(safe-area-inset-bottom));
                background: #ffffff; border-top: 1px solid #e2e8f0; box-shadow: 0 -4px 12px rgba(15, 23, 42, 0.06);
            }
            .dark .product-mobile-bar { background: var(--gray-900); border-color: var(--gray-700); }
        }
        @media (max-width: 640px) { .product-config-two-columns { grid-template-columns: 1fr !important; } .product-config-two-columns > * { grid-column: span 1 !important; } }

    </style>

    <div class="product-layout" style="font-family: system-ui, sans-serif;">
        
        {{-- COLONNE GAUCHE : VISUELS ET DESCRIPTION --}}
        <div class="product-layout-main"
             x-data="{ sticky: false }"
             x-init="
                const update = () => { sticky = window.innerWidth >= 1280 && $el.offsetHeight < window.innerHeight - 110 };
                update();
                new ResizeObserver(update).observe($el);
                window.addEventListener('resize', update);
             "
             x-bind:class="{ 'is-sticky': sticky }">
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                
                <div wire:ignore style="display: flex; flex-direction: column; gap: 2rem;">
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem; color: var(--tk-accent); font-weight: 700; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em;">
                            <span>{{ $product->category->name ?? 'Prestation Japonaise' }}</span>
                            @if(isset($product->is_public) && !$product->is_public)
                                <span style="background: #fee2e2; color: #991b1b; font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: 0.25rem;">{{ __('Tarif Négocié / Privé') }}</span>
                            @endif
                        </div>
                        <h1 class="product-title" style="font-weight: 800; color: #1e293b; letter-spacing: -0.02em; margin: 0;">{{ $product->name }}</h1>
                        
                    </div>

                    {{-- GESTION ULTRA-ROBUSTE DES IMAGES --}}
                    @php 
                        $rawImages = $product->images;
                        if (is_string($rawImages)) {
                            $decoded = json_decode($rawImages, true);
                            $rawImages = is_array($decoded) ? $decoded : ($rawImages ? [$rawImages] : []);
                        }
                        if (!is_array($rawImages)) {
                            $rawImages = [];
                        }

                        $images = [];
                        foreach ($rawImages as $img) {
                            if (!$img) continue;
                            $imgClean = str_replace('\\', '/', trim($img));
                            if (str_starts_with($imgClean, 'http://') || str_starts_with($imgClean, 'https://')) {
                                $images[] = $imgClean;
                            } else {
                                $imgClean = ltrim($imgClean, '/');
                                if (str_starts_with($imgClean, 'storage/')) {
                                    $images[] = asset($imgClean);
                                } else {
                                    $images[] = asset('storage/' . $imgClean);
                                }
                            }
                        }

                        // Versions redimensionnées (GD) des photos locales, créées une fois puis réutilisées.
                        // Les originaux ne sont jamais modifiés ; une image plus petite que $max reste telle quelle.
                        $variant = function (string $url, int $max): string {
                            $path = parse_url($url, PHP_URL_PATH) ?? '';
                            if (! str_starts_with($path, '/storage/')) {
                                return $url;
                            }

                            $relative = substr($path, strlen('/storage/'));
                            $source = storage_path('app/public/'.$relative);
                            $target = storage_path('app/public/variants/'.$max.'/'.$relative);

                            if (is_file($target)) {
                                return asset('storage/variants/'.$max.'/'.$relative);
                            }

                            $size = is_file($source) ? @getimagesize($source) : false;
                            if (! $size || max($size[0], $size[1]) <= $max) {
                                return $url;
                            }

                            $load = match ($size[2]) {
                                IMAGETYPE_JPEG => 'imagecreatefromjpeg',
                                IMAGETYPE_PNG => 'imagecreatefrompng',
                                IMAGETYPE_WEBP => 'imagecreatefromwebp',
                                default => null,
                            };
                            if ($load === null || ! function_exists('imagecreatetruecolor')) {
                                return $url;
                            }

                            $scale = $max / max($size[0], $size[1]);
                            $width = (int) round($size[0] * $scale);
                            $height = (int) round($size[1] * $scale);

                            $src = @$load($source);
                            if (! $src) {
                                return $url;
                            }

                            $dst = imagecreatetruecolor($width, $height);
                            if (in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                                imagealphablending($dst, false);
                                imagesavealpha($dst, true);
                            }
                            imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);

                            if (! is_dir(dirname($target))) {
                                @mkdir(dirname($target), 0755, true);
                            }

                            $saved = match ($size[2]) {
                                IMAGETYPE_JPEG => imagejpeg($dst, $target, 85),
                                IMAGETYPE_PNG => imagepng($dst, $target, 6),
                                IMAGETYPE_WEBP => imagewebp($dst, $target, 85),
                                default => false,
                            };
                            imagedestroy($src);
                            imagedestroy($dst);

                            return $saved ? asset('storage/variants/'.$max.'/'.$relative) : $url;
                        };

                        $galleryUrls = array_map(fn (string $url): string => $variant($url, 1200), $images);
                        $thumbUrls = array_map(fn (string $url): string => $variant($url, 300), $images);
                    @endphp

                    @if(count($images) > 0)
                        <div x-data="{ mainImage: '{{ $galleryUrls[0] }}' }" style="display: flex; flex-direction: column; gap: 1rem;">
                            <div class="product-gallery" style="border-radius: 1.25rem; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05); background: #f1f5f9;">
                                <img :src="mainImage" src="{{ $galleryUrls[0] }}" style="width: 100%; height: 100%; object-fit: cover; transition: opacity 0.3s ease-in-out;" alt="{{ $product->name }}">
                            </div>
                            @if(count($images) > 1)
                                <div style="display: flex; gap: 0.75rem; overflow-x: auto; padding-bottom: 0.5rem;">
                                    @foreach($images as $i => $imgUrl)
                                        <div @click="mainImage = '{{ $galleryUrls[$i] }}'" class="gallery-thumb" :class="{ 'active': mainImage === '{{ $galleryUrls[$i] }}' }">
                                            <img src="{{ $thumbUrls[$i] }}" style="width: 100%; height: 100%; object-fit: cover;">
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="product-gallery-empty" style="border-radius: 1rem; background: #f1f5f9; display: flex; align-items: center; justify-content: center; gap: 0.6rem; color: #94a3b8; font-weight: 500; font-size: 0.85rem; padding: 1.75rem 1rem;">
                            <svg style="width: 1.4rem; height: 1.4rem; flex: none;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" /></svg>
                            {{ __('Aucun visuel disponible') }}
                        </div>
                    @endif

                    {{-- DESCRIPTIF DU PRODUIT --}}
                    <div class="product-card-lg" style="background: white; border-radius: 1.25rem; border: 1px solid #f1f5f9; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                        <h2 style="font-size: 1.15rem; font-weight: 700; color: #1e293b; margin-top:0; margin-bottom: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                            <span style="width: 8px; height: 8px; background: var(--primary-600); border-radius: 50%; flex: none;"></span>
                            {{ __('Présentation de la prestation') }}
                        </h2>
                        <div style="color: #475569; line-height: 1.7; font-size: 0.95rem; white-space: pre-line;">{{ $product->description }}</div>
                    </div>

                </div>
            </div>
        </div>

        {{-- COLONNE DROITE : FORMULAIRE ET CONFIGURATION --}}
        <div class="product-layout-side">
            <div style="position: sticky; top: 2rem; display: flex; flex-direction: column; gap: 1rem;">
                
                {{-- BLOC 1 : PANNEAU AGENCE PARTEANIRE --}}
                @if($this->activeAgencyId)
                    <div class="product-card-side" style="background: white; border-radius: 1.25rem; border: 1px solid #f1f5f9; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.03);">
                        {{-- Contraintes de réservation : affichées avant le formulaire pour être lues avant de le remplir --}}
                        @if($product->days_before_opening || $product->days_before_request_deadline || $product->is_lottery)
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 0.5rem; margin-bottom: 1rem;">
                                @if($product->days_before_opening)
                                    <div style="background: var(--tk-accent-soft); border-left: 3px solid var(--primary-600); padding: 0.65rem; border-radius: 0 0.5rem 0.5rem 0; display: flex; align-items: flex-start; gap: 0.5rem; color: var(--tk-accent); font-size: 0.8rem; line-height: 1.35;">
                                        <svg style="width: 20px; height: 20px; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2v12a2 2 0 002 2z" /></svg>
                                        <span><strong>{{ __('Ouverture des réservations') }}</strong><br>{{ __('À partir de J-:days', ['days' => $product->days_before_opening]) }}</span>
                                    </div>
                                @endif

                                @if($product->days_before_request_deadline)
                                    <div style="background: #fff7ed; border-left: 3px solid #ea580c; padding: 0.65rem; border-radius: 0 0.5rem 0.5rem 0; display: flex; align-items: flex-start; gap: 0.5rem; color: #9a3412; font-size: 0.8rem; line-height: 1.35;">
                                        <svg style="width: 20px; height: 20px; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 3.5a2 2 0 00-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z" /></svg>
                                        <span><strong>{{ __('Date limite de demande :') }}</strong><br>{{ __('Jusqu’à J-:days', ['days' => $product->days_before_request_deadline]) }}</span>
                                    </div>
                                @endif

                                @if($product->is_lottery)
                                    <div style="background: #fef2f2; border-left: 3px solid #e11d48; padding: 0.65rem; border-radius: 0 0.5rem 0.5rem 0; display: flex; align-items: flex-start; gap: 0.5rem; color: #9f1239; font-size: 0.8rem; line-height: 1.35;">
                                        <svg style="width: 20px; height: 20px; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                        <span><strong>{{ __('Disponibilité limitée') }}</strong><br>{{ __('Sous réserve de confirmation') }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif

                        
                        {{-- CAS PRODUIT DE TYPE TRANSPORT --}}
                        @if($product->isTransport())
                            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                                
                                <div style="margin-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">
                                    <h3 style="font-size: 0.95rem; font-weight: 600; color: #0f172a; margin: 0 0 0.25rem 0; display: flex; align-items: center; gap: 0.5rem;">
                                        <span style="font-size: 1.1rem;">🚄</span> {{ __('Itinéraire de Transport (Multi-Trajets)') }}
                                    </h3>
                                    <p style="font-size: 0.8rem; color: #64748b; margin: 0; line-height: 1.4;">
                                        {{ __('Indiquez pour chaque ticket la gare de départ, d’arrivée, la date, l’heure et le nombre de passagers.') }}
                                    </p>
                                </div>

                                @if(!empty($customValues['transport_routes']))
                                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                                        @foreach($customValues['transport_routes'] as $index => $route)
                                            <div wire:key="route-step-{{ $index }}" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 0.75rem; padding: 1rem; position: relative; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                                                
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                        <span style="color: #64748b; font-size: 0.9rem;">⇅</span>
                                                        <span style="font-size: 0.85rem; font-weight: 600; color: #1e293b;">
                                                            {{ (!empty($route['departure_station']) || !empty($route['arrival_station'])) ? (($route['departure_station'] ?: __('Départ')) . ' ➔ ' . ($route['arrival_station'] ?: __('Arrivée')) . (!empty($route['departure_date']) ? ' ('.$route['departure_date'].')' : '')) : __('Nouveau trajet') }}
                                                        </span>
                                                    </div>
                                                    
                                                    @if(count($customValues['transport_routes']) > 1)
                                                        <button type="button" wire:click="removeTransportRoute({{ $index }})" title="Supprimer ce trajet" style="color: #ef4444; background: transparent; border: none; cursor: pointer; padding: 0.2rem; display: flex; align-items: center;">
                                                            <svg style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                        </button>
                                                    @endif
                                                </div>

                                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; margin-bottom: 0.85rem;">
                                                    
                                                    {{-- Gare / Station de départ --}}
                                                    <div x-data="{
                                                        search: @entangle('customValues.transport_routes.' . $index . '.departure_station').live,
                                                        open: false,
                                                        stations: @js($this->stationsList),
                                                        get filtered() {
                                                            if (!this.search || this.search.length < 1) {
                                                                return this.stations.slice(0, 10);
                                                            }
                                                            let q = this.search.toLowerCase();
                                                            return this.stations.filter(s => 
                                                                s.name.toLowerCase().includes(q) || 
                                                                (s.label && s.label.toLowerCase().includes(q))
                                                            ).slice(0, 15);
                                                        },
                                                        select(st) {
                                                            this.search = st.name;
                                                            this.open = false;
                                                        }
                                                    }" style="position: relative; grid-column: span 1 / span 1;">
                                                        <label style="font-size: 0.8rem; font-weight: 700; color: #374151; display: block; margin-bottom: 0.25rem;">
                                                            {{ __('Gare / Station de départ') }}<span style="color: #dc2626;">*</span>
                                                        </label>
                                                        
                                                        <div style="position: relative;">
                                                            <input type="text" 
                                                                   x-model="search"
                                                                   @focus="open = true"
                                                                   @click.away="open = false"
                                                                   placeholder="{{ __('Sélectionner...') }}"
                                                                   style="width: 100%; padding: 0.5rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size: 0.85rem; outline: none; background: #ffffff;" />
                                                        </div>

                                                        <div x-show="open && filtered.length > 0" 
                                                             style="position: absolute; z-index: 50; width: 100%; margin-top: 2px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 0.375rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); max-height: 200px; overflow-y: auto;">
                                                            <template x-for="st in filtered" :key="st.name">
                                                                <div @click="select(st)" 
                                                                     style="padding: 0.45rem 0.65rem; font-size: 0.8rem; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-weight: 600; color: #1e293b;"
                                                                     onmouseover="this.style.background='var(--tk-accent-soft)'" 
                                                                     onmouseout="this.style.background='#ffffff'">
                                                                    <span x-text="st.label"></span>
                                                                </div>
                                                            </template>
                                                        </div>

                                                        @php $depMaps = $this->getStationMapUrl($route['departure_station'] ?? null); @endphp
                                                        @if($depMaps)
                                                            <a href="{{ $depMaps }}" target="_blank" style="color: #2563eb; text-decoration: underline; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 3px; margin-top: 4px; font-weight: 600;">
                                                                📍 Voir sur Google Maps
                                                            </a>
                                                        @endif
                                                        @error("customValues.transport_routes.{$index}.departure_station")
                                                            <span style="color: #dc2626; font-size: 0.75rem;">{{ $message }}</span>
                                                        @enderror
                                                    </div>

                                                    {{-- Gare / Station d'arrivée --}}
                                                    <div x-data="{
                                                        search: @entangle('customValues.transport_routes.' . $index . '.arrival_station').live,
                                                        open: false,
                                                        stations: @js($this->stationsList),
                                                        get filtered() {
                                                            if (!this.search || this.search.length < 1) {
                                                                return this.stations.slice(0, 10);
                                                            }
                                                            let q = this.search.toLowerCase();
                                                            return this.stations.filter(s => 
                                                                s.name.toLowerCase().includes(q) || 
                                                                (s.label && s.label.toLowerCase().includes(q))
                                                            ).slice(0, 15);
                                                        },
                                                        select(st) {
                                                            this.search = st.name;
                                                            this.open = false;
                                                        }
                                                    }" style="position: relative; grid-column: span 1 / span 1;">
                                                        <label style="font-size: 0.8rem; font-weight: 700; color: #374151; display: block; margin-bottom: 0.25rem;">
                                                            {{ __('Gare / Station d’arrivée') }}<span style="color: #dc2626;">*</span>
                                                        </label>

                                                        <div style="position: relative;">
                                                            <input type="text" 
                                                                   x-model="search"
                                                                   @focus="open = true"
                                                                   @click.away="open = false"
                                                                   placeholder="{{ __('Sélectionner...') }}"
                                                                   style="width: 100%; padding: 0.5rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size: 0.85rem; outline: none; background: #ffffff;" />
                                                        </div>

                                                        <div x-show="open && filtered.length > 0" 
                                                             style="position: absolute; z-index: 50; width: 100%; margin-top: 2px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 0.375rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); max-height: 200px; overflow-y: auto;">
                                                            <template x-for="st in filtered" :key="st.name">
                                                                <div @click="select(st)" 
                                                                     style="padding: 0.45rem 0.65rem; font-size: 0.8rem; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-weight: 600; color: #1e293b;"
                                                                     onmouseover="this.style.background='var(--tk-accent-soft)'" 
                                                                     onmouseout="this.style.background='#ffffff'">
                                                                    <span x-text="st.label"></span>
                                                                </div>
                                                            </template>
                                                        </div>

                                                        @php $arrMaps = $this->getStationMapUrl($route['arrival_station'] ?? null); @endphp
                                                        @if($arrMaps)
                                                            <a href="{{ $arrMaps }}" target="_blank" style="color: #2563eb; text-decoration: underline; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 3px; margin-top: 4px; font-weight: 600;">
                                                                📍 Voir sur Google Maps
                                                            </a>
                                                        @endif
                                                        @error("customValues.transport_routes.{$index}.arrival_station")
                                                            <span style="color: #dc2626; font-size: 0.75rem;">{{ $message }}</span>
                                                        @enderror
                                                    </div>

                                                </div>

                                                <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.6rem;">
                                                    <div>
                                                        <label style="font-size: 0.75rem; font-weight: 700; color: #374151; display: block; margin-bottom: 0.2rem;">Date du trajet<span style="color: #dc2626;">*</span></label>
                                                        <input type="date" wire:model.live="customValues.transport_routes.{{ $index }}.departure_date" style="width: 100%; padding: 0.45rem 0.5rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size: 0.8rem; background: #ffffff;" />
                                                        @error("customValues.transport_routes.{$index}.departure_date")
                                                            <span style="color: #dc2626; font-size: 0.7rem;">{{ $message }}</span>
                                                        @enderror
                                                    </div>

                                                    <div>
                                                        <label style="font-size: 0.75rem; font-weight: 700; color: #374151; display: block; margin-bottom: 0.2rem;">Heure / N° Train ou Bus</label>
                                                        <input type="text" wire:model.live="customValues.transport_routes.{{ $index }}.departure_time" placeholder="Ex: 09:30 / Hikari 502 / Indications" style="width: 100%; padding: 0.45rem 0.5rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size: 0.8rem; background: #ffffff;" />
                                                    </div>

                                                    <div>
                                                        <label style="font-size: 0.75rem; font-weight: 700; color: #374151; display: block; margin-bottom: 0.2rem;">Classe<span style="color: #ef4444;">*</span></label>
                                                        <select required="required" wire:model.live="customValues.transport_routes.{{ $index }}.option_id" style="width: 100%; padding: 0.45rem 0.4rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size: 0.8rem; background: #ffffff; color: #1e293b; font-weight: 600;">
                                                            <option value="">-- {{ __('Sélectionnez') }} --</option>
                                                            @foreach($product->productOptions as $opt)
                                                                <option value="{{ $opt->id }}">
                                                                    {{ $opt->name }}{{ (($opt->price_modifier ?? 0) > 0) ? ' (+'.number_format($opt->price_modifier, 0, '.', ' ').' ¥)' : '' }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label style="font-size: 0.75rem; font-weight: 700; color: #374151; display: block; margin-bottom: 0.2rem;">Nombre de Passagers<span style="color: #dc2626;">*</span></label>
                                                        <input type="number" min="1" wire:model.live.debounce.300ms="customValues.transport_routes.{{ $index }}.pax_count" style="width: 100%; padding: 0.45rem 0.5rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size: 0.8rem; background: #ffffff; text-align: center; font-weight: 700;" />
                                                    </div>

                                                    <div style="grid-column: 1 / -1;">
                                                        <label for="route-specific-requests-{{ $index }}" style="font-size: 0.75rem; font-weight: 700; color: #374151; display: block; margin-bottom: 0.2rem;">{{ __('Demandes spécifiques/indication complémentaire') }}</label>
                                                        <textarea id="route-specific-requests-{{ $index }}" wire:model="customValues.transport_routes.{{ $index }}.specific_requests" rows="3" maxlength="5000" style="width: 100%; padding: 0.45rem 0.5rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size: 0.8rem; background: #ffffff;"></textarea>
                                                        @error("customValues.transport_routes.{$index}.specific_requests")
                                                            <span style="color: #dc2626; font-size: 0.75rem;">{{ $message }}</span>
                                                        @enderror
                                                    </div>

                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div style="text-align: center; margin-top: 1.25rem;">
                                        <button type="button" wire:click="addTransportRoute" style="background: #ffffff; color: #374151; border: 1px solid #d1d5db; padding: 0.5rem 1.25rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 700; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.15s;" onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#9ca3af';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#d1d5db';">
                                            Ajouter un trajet
                                        </button>
                                    </div>
                                @endif

                                @php $estimate = $this->getEstimatedPrice(); @endphp
                                <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 0.75rem; padding: 1.25rem; margin-top: 1.25rem;">
                                    <h4 style="font-size: 0.95rem; font-weight: 600; color: #b45309; margin: 0 0 0.75rem 0;">
                                        {{ __('Estimation de vos frais d’émission :') }}
                                    </h4>

                                    <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.85rem;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span style="color: #78350f; font-weight: 500;">
                                                • @if($estimate['base_is_global'] ?? false) {{ __('Forfait global') }} @else Frais de service de l'agence ({{ number_format($estimate['unit_base'] ?? 0, 0, '.', ' ') }} ¥ × {{ $estimate['qty'] ?? 1 }} trajet{{ ($estimate['qty'] ?? 1) > 1 ? 's' : '' }} total) @endif
                                            </span>
                                            <span style="font-weight: 600; color: #78350f;">
                                                {{ number_format($estimate['total_base'] ?? 0, 0, '.', ' ') }} ¥
                                            </span>
                                        </div>

                                        @if(($estimate['total_options'] ?? 0) > 0)
                                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                                <span style="color: var(--tk-accent); font-weight: 600;">
                                                    • {{ __('Suppléments classes / options') }}
                                                </span>
                                                <span style="font-weight: 600; color: var(--tk-accent);">
                                                    + {{ number_format($estimate['total_options'], 0, '.', ' ') }} ¥
                                                </span>
                                            </div>
                                        @endif

                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span style="color: #b45309; font-weight: 500;">
                                                • Prix des billets (Train / Bus)
                                            </span>
                                            <span style="font-weight: 600; color: #b45309;">
                                                Sur devis (Prix coutant)
                                            </span>
                                        </div>

                                    </div>
                                </div>

                            </div>
                        @else
                            {{-- PRODUIT CLASSIQUE (SANS TRANSPORT) --}}
                            <div class="product-config-section">
                                <div class="product-config-section-title">{{ __('Date et participants') }}</div>
                                @if($product->min_child_age)
                                    <div style="margin-bottom: 0.5rem; padding: 0.5rem 0.75rem; border-radius: 0.5rem; background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; font-size: 0.85rem; font-weight: 600;">
                                        {{ __('Enfants de moins de :age ans non acceptés.', ['age' => $product->min_child_age]) }}
                                    </div>
                                @endif
                                <div class="product-config-two-columns" style="display: grid; grid-template-columns: 1fr 0.6fr; gap: 0.75rem;">
                                <div wire:key="calendar-picker" style="display: flex; flex-direction: column; gap: 0.35rem;{{ $product->usesAgeSelector() ? ' grid-column: 1 / -1;' : '' }}" 
                                     @calendar-map-updated.window="map = $event.detail.map"
                                     x-data="{ 
                                        open: false,
                                        currentDate: new Date(),
                                        map: @js($this->calendarMap),
                                        selectedDate: @entangle('serviceDate').live,
                                        days: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
                                        get currentMonthName() {
                                            let formatter = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' });
                                            let formatted = formatter.format(this.currentDate);
                                            return formatted.charAt(0).toUpperCase() + formatted.slice(1);
                                        },
                                        get monthDays() {
                                            let year = this.currentDate.getFullYear();
                                            let month = this.currentDate.getMonth();
                                            let date = new Date(year, month, 1);
                                            let daysArray = [];
                                            
                                            let firstDay = date.getDay() === 0 ? 6 : date.getDay() - 1;
                                            for(let i=0; i<firstDay; i++) daysArray.push(null);
                                            
                                            let todayStr = new Date();
                                            todayStr.setMinutes(todayStr.getMinutes() - todayStr.getTimezoneOffset());
                                            let todayFormatted = todayStr.toISOString().split('T')[0];

                                            while (date.getMonth() === month) {
                                                let d = new Date(date);
                                                let dateStr = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                                                
                                                let isPast = dateStr < todayFormatted;
                                                let info = this.map[dateStr] || { available: false, price: null, is_on_demand: false };
                                                
                                                let dayInfo = Object.assign({}, info);
                                                if (isPast) dayInfo.available = false;

                                                daysArray.push({
                                                    dateStr: dateStr,
                                                    dayNumber: d.getDate(),
                                                    info: dayInfo
                                                });
                                                date.setDate(date.getDate() + 1);
                                            }
                                            return daysArray;
                                        },
                                        nextMonth() { this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 1); },
                                        prevMonth() { this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() - 1, 1); },
                                        selectDate(day) {
                                            if (day && day.info.available) {
                                                this.selectedDate = day.dateStr;
                                                this.open = false;
                                            }
                                        }
                                     }">
                                    
                                    <label style="font-size: 0.85rem; font-weight: 700; color: #374151;">{{ __('Date d’activité') }}</label>
                                    <button type="button" @click="open = true" style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; outline: none; background: white; text-align: left; display: flex; justify-content: space-between; align-items: center; cursor: pointer;">
                                        <span x-text="selectedDate ? selectedDate.split('-').reverse().join('/') : @js(__('Sélectionner...'))" :style="!selectedDate && 'color: #9ca3af'"></span>
                                        <svg style="width: 16px; height: 16px; color: #9ca3af;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    </button>
                                    @error('serviceDate') <span style="color: #dc2626; font-size: 0.75rem; font-weight: 500;">{{ $message }}</span> @enderror

                                    <div x-show="open" style="display: none;">
                                        <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.7); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 1rem; backdrop-filter: blur(4px);">
                                            <div @click.away="open = false" style="background: white; border-radius: 1.25rem; width: 100%; max-width: 440px; padding: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); position: relative;">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                                                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 600; color: #1e293b;">{{ __('Sélectionnez une date') }}</h3>
                                                    <button type="button" @click="open = false" style="background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0'; this.style.color='#0f172a'" onmouseout="this.style.background='#f1f5f9'; this.style.color='#64748b'">
                                                        <svg style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                                    </button>
                                                </div>
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                                    <button type="button" @click="prevMonth()" style="padding: 0.5rem; border-radius: 0.5rem; background: #f8fafc; border: 1px solid #e2e8f0; cursor: pointer; color: #475569;">
                                                        <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                                                    </button>
                                                    <div x-text="currentMonthName" style="font-weight: 700; font-size: 1.1rem; color: var(--tk-accent); text-transform: capitalize;"></div>
                                                    <button type="button" @click="nextMonth()" style="padding: 0.5rem; border-radius: 0.5rem; background: #f8fafc; border: 1px solid #e2e8f0; cursor: pointer; color: #475569;">
                                                        <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                                    </button>
                                                </div>
                                                <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.25rem; margin-bottom: 0.5rem;">
                                                    <template x-for="d in days">
                                                        <div x-text="d" style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-align: center;"></div>
                                                    </template>
                                                </div>
                                                <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.25rem;">
                                                    <template x-for="day in monthDays">
                                                        <div class="calendar-cell"
                                                             :class="{
                                                                 'calendar-cell-selected': day && day.info.available && selectedDate === day.dateStr,
                                                                 'calendar-cell-available': day && day.info.available && selectedDate !== day.dateStr,
                                                                 'calendar-cell-disabled': !day || !day.info.available
                                                             }"
                                                             @click="selectDate(day)">
                                                            <span x-show="day" class="calendar-day-number" x-text="day ? day.dayNumber : ''" :style="selectedDate === (day ? day.dateStr : '') ? 'color: white;' : 'color: #1e293b;'"></span>
                                                            <template x-if="day && day.info.available">
                                                                <div style="text-align: center; width: 100%;">
                                                                    <template x-if="!day.info.is_on_demand && day.info.price">
                                                                        <span class="calendar-price-text" x-text="day.info.price + ' ¥'" :style="selectedDate === day.dateStr ? 'color: var(--primary-100);' : 'color: var(--tk-accent);'"></span>
                                                                    </template>
                                                                    <template x-if="!day.info.is_on_demand && !day.info.price">
                                                                        <span class="calendar-price-text" :style="selectedDate === day.dateStr ? 'color: var(--primary-100);' : 'color: var(--tk-accent);'">-</span>
                                                                    </template>
                                                                    <template x-if="day.info.is_on_demand">
                                                                        <span class="calendar-price-text" :style="selectedDate === day.dateStr ? 'color: #ffedd5;' : 'color: #d97706;'">Devis</span>
                                                                    </template>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                @if($product->usesAgeSelector())
                                    <div style="grid-column: span 2; display: flex; flex-direction: column; gap: 0.45rem; background: var(--tk-accent-soft); border: 1px solid var(--tk-accent-border); border-radius: 0.6rem; padding: 0.6rem 0.75rem;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
                                            <label style="font-size: 0.85rem; font-weight: 700; color: var(--tk-accent);">{{ __('Répartition des participants') }}</label>
                                            <span style="font-size: 0.75rem; color: var(--tk-accent);">@if($product->hasChildSelector()){{ __('Enfant jusqu’à') }} {{ $product->child_age_limit ?? 11 }} {{ __('ans inclus') }}@endif@if($product->hasChildSelector() && $product->hasTeenSelector()) · @endif@if($product->hasTeenSelector()){{ __('Adolescent de :min à :max ans', ['min' => ($product->child_age_limit ?? 11) + 1, 'max' => $product->teen_age_limit]) }}@endif</span>
                                        </div>
                                        <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.65rem;">
                                            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                                <label style="font-size: 0.75rem; font-weight: 700; color: #374151;">{{ __('Adultes') }}</label>
                                                <input type="number" wire:model.live.debounce.300ms="ageSelectorAdults" min="0" style="width: 100%; padding: 0.4rem; border: 1px solid var(--tk-accent-border); border-radius: 0.5rem; font-size: 0.875rem; outline: none; background: white; text-align: center;">
                                            </div>
                                            @if($product->hasChildSelector())<div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                                <label style="font-size: 0.75rem; font-weight: 700; color: #374151;">{{ __('Enfants') }}</label>
                                                <input type="number" wire:model.live.debounce.300ms="ageSelectorChildren" min="0" style="width: 100%; padding: 0.4rem; border: 1px solid var(--tk-accent-border); border-radius: 0.5rem; font-size: 0.875rem; outline: none; background: white; text-align: center;">
                                            </div>@endif
                                            @if($product->hasTeenSelector())
                                                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                                    <label style="font-size: 0.75rem; font-weight: 700; color: #374151;">{{ __('Adolescents') }}</label>
                                                    <input type="number" wire:model.live.debounce.300ms="ageSelectorTeenagers" min="0" style="width: 100%; padding: 0.4rem; border: 1px solid var(--tk-accent-border); border-radius: 0.5rem; font-size: 0.875rem; outline: none; background: white; text-align: center;">
                                                </div>
                                            @endif
                                            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                                <label style="font-size: 0.75rem; font-weight: 700; color: #374151;">{{ __('Total Pax') }}</label>
                                                <input type="number" value="{{ $quantity }}" readonly style="width: 100%; padding: 0.4rem; border: 1px solid #cbd5e1; border-radius: 0.5rem; font-size: 0.875rem; outline: none; background: #f8fafc; text-align: center; font-weight: 700; color: var(--tk-accent);">
                                            </div>
                                        </div>
                                        @error('ageSelectorAdults') <span style="color: #dc2626; font-size: 0.75rem; font-weight: 500;">{{ $message }}</span> @enderror
                                        @error('ageSelectorChildren') <span style="color: #dc2626; font-size: 0.75rem; font-weight: 500;">{{ $message }}</span> @enderror
                                        @error('ageSelectorTeenagers') <span style="color: #dc2626; font-size: 0.75rem; font-weight: 500;">{{ $message }}</span> @enderror
                                        @error('quantity') <span style="color: #dc2626; font-size: 0.75rem; font-weight: 500;">{{ $message }}</span> @enderror
                                    </div>
                                @else
                                    <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                        <label style="font-size: 0.85rem; font-weight: 700; color: #374151;">Pax</label>
                                        <input type="number" wire:model.live.debounce.500ms="quantity" min="1" style="width: 100%; padding: 0.55rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; outline: none; background: white; text-align: center;">
                                        @error('quantity') <span style="color: #dc2626; font-size: 0.75rem; font-weight: 500;">{{ $message }}</span> @enderror
                                    </div>
                                @endif
                                </div>
                            </div>

                            @php
                                $requiredGrouped = $product->productOptions
                                    ->filter(fn($o) => $o->code !== 'GUIDE')
                                    ->filter(fn($o) => $o->is_required || !empty($o->group_name))
                                    ->groupBy(fn($o) => !empty($o->group_name) ? $o->group_name : 'default_required');

                                $optionalOptions = $product->productOptions
                                    ->filter(fn($o) => $o->code !== 'GUIDE')
                                    ->filter(fn($o) => !$o->is_required && empty($o->group_name));
                            @endphp

                            @if($requiredGrouped->count() > 0)
                                <div class="product-config-section">
                                    <h4 class="product-config-section-title">{{ __('Déclinaisons') }}</h4>
                                    @foreach($requiredGrouped as $groupName => $groupOptions)
                                        @php 
                                            $displayGroupName = ($groupName === 'default_required') ? __('Déclinaison') : $groupName;
                                            $selectedInGroup = $groupOptions->first(fn($o) => !empty($selectedOptions[$o->id]['enabled'])) ?? $groupOptions->first();
                                        @endphp
                                        <div class="product-variant-group" style="margin-bottom: 0.75rem;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                                                <label style="font-size: 0.85rem; font-weight: 700; color: #374151; display: flex; align-items: center; gap: 0.35rem;">
                                                    <span>{{ $displayGroupName }}</span>
                                                    <span style="color: #dc2626; font-size: 0.85rem;">*</span>
                                                </label>
                                                @if($selectedInGroup && ($selectedInGroup->price_modifier ?? 0) > 0)
                                                    <span style="font-size: 0.8rem; color: var(--tk-accent); font-weight: 700;">
                                                        + {{ number_format($selectedInGroup->price_modifier, 0, '.', ' ') }} ¥
                                                    </span>
                                                @endif
                                            </div>

                                            <select wire:change="selectGroupVariant('{{ addslashes($groupName) }}', $event.target.value)" 
                                                    style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; outline: none; background: #ffffff; color: #1e293b; cursor: pointer; transition: border-color 0.2s;"
                                                    onfocus="this.style.borderColor='var(--primary-600)';" onblur="this.style.borderColor='#d1d5db';">
                                                @foreach($groupOptions as $option)
                                                    @php
                                                        $isSelected = !empty($selectedOptions[$option->id]['enabled']);
                                                        $adultQuantity = $product->usesAgeSelector() ? $ageSelectorAdults : $quantity;
                                                        $childQuantity = $product->usesAgeSelector() ? $ageSelectorChildren : $quantity;
                                                        $teenQuantity = $product->usesAgeSelector() ? $ageSelectorTeenagers : $quantity;
                                                        $optionBillingQty = $option->billableQuantityForDate($serviceDate, $adultQuantity, 'adult');
                                                    @endphp
                                                    <option value="{{ $option->id }}" {{ $isSelected ? 'selected' : '' }}>
                                                        {{ $option->name }}
                                                        @if($product->usesAgeSelector() && $option->billing_type === 'per_pax')
                                                            (+ {{ number_format($option->priceModifierForDate($serviceDate, 'adult', $adultQuantity), 0, '.', ' ') }} ¥ {{ __('adulte') }}@if($product->hasChildSelector()) / {{ number_format($option->priceModifierForDate($serviceDate, 'child', $childQuantity), 0, '.', ' ') }} ¥ {{ __('enfant') }}@endif@if($product->hasTeenSelector()) / {{ number_format($option->priceModifierForDate($serviceDate, 'teen', $teenQuantity), 0, '.', ' ') }} ¥ {{ __('adolescent') }}@endif{{ $optionBillingQty < $adultQuantity ? ' / ' . __('pax supplémentaires') : '' }})
                                                        @elseif($option->priceModifierForDate($serviceDate, 'adult', $adultQuantity) > 0)
                                                            (+ {{ number_format($option->priceModifierForDate($serviceDate, 'adult', $adultQuantity), 0, '.', ' ') }} ¥{{ $optionBillingQty < $adultQuantity ? ' / ' . __('pax supplémentaire') : '' }})
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if($optionalOptions->count() > 0)
                                <div class="product-config-section">
                                    <h4 class="product-config-section-title">{{ __('Options complémentaires') }}</h4>
                                    <p style="margin: -0.4rem 0 0.85rem; font-size: 0.8rem; color: #64748b;">{{ __('Facultatives') }}</p>
                                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                        @foreach($optionalOptions as $option)
                                            @php
                                                $adultQuantity = $product->usesAgeSelector() ? $ageSelectorAdults : $quantity;
                                                $childQuantity = $product->usesAgeSelector() ? $ageSelectorChildren : $quantity;
                                                $teenQuantity = $product->usesAgeSelector() ? $ageSelectorTeenagers : $quantity;
                                                $optionBillingQty = $option->billableQuantityForDate($serviceDate, $adultQuantity, 'adult');
                                            @endphp
                                            <div wire:key="option-{{ $option->id }}" style="background: #f8fafc; border: 1px solid #f1f5f9; padding: 0.75rem; border-radius: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem;">
                                                <div style="display: flex; align-items: flex-start; gap: 0.5rem;">
                                                    <input type="checkbox" wire:model.live="selectedOptions.{{ $option->id }}.enabled" id="opt_{{ $option->id }}" style="margin-top: 0.2rem; accent-color: var(--primary-600);">
                                                    <label for="opt_{{ $option->id }}" style="font-size: 0.85rem; font-weight: 600; color: #374151; cursor: pointer; flex-grow: 1;">
                                                        {{ $option->name }}
                                                        <span style="display: block; font-size: 0.8rem; color: var(--tk-accent); font-weight: 700; margin-top: 0.1rem;">
                                                            @if($product->usesAgeSelector() && $option->billing_type === 'per_pax')
                                                                + {{ number_format($option->priceModifierForDate($serviceDate, 'adult', $adultQuantity), 0, '.', ' ') }} ¥ {{ __('adulte') }}@if($product->hasChildSelector()) / {{ number_format($option->priceModifierForDate($serviceDate, 'child', $childQuantity), 0, '.', ' ') }} ¥ {{ __('enfant') }}@endif@if($product->hasTeenSelector()) / {{ number_format($option->priceModifierForDate($serviceDate, 'teen', $teenQuantity), 0, '.', ' ') }} ¥ {{ __('adolescent') }}@endif{{ $optionBillingQty < $adultQuantity ? ' / ' . __('pax supplémentaires') : '' }}
                                                            @else
                                                                + {{ number_format($option->priceModifierForDate($serviceDate, 'adult', $adultQuantity), 0, '.', ' ') }} ¥{{ $optionBillingQty < $adultQuantity ? ' / ' . __('pax supplémentaire') : '' }}
                                                            @endif
                                                        </span>
                                                    </label>
                                                </div>

                                                @if(($selectedOptions[$option->id]['enabled'] ?? false) && $option->billing_type === 'manual')
                                                    <div style="display: flex; align-items: center; gap: 0.5rem; background: white; padding: 0.35rem 0.5rem; border-radius: 0.375rem; border: 1px solid #e2e8f0; margin-left: 1.25rem;">
                                            <span style="font-size: 0.75rem; color: #64748b;">{{ __('Quantité :') }}</span>
                                                        <input type="number" wire:model.live.debounce.300ms="selectedOptions.{{ $option->id }}.quantity" min="1" style="width: 50px; border: none; padding: 0; outline: none; text-align: center; font-size: 0.85rem; font-weight: 600;">
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if(!empty($product->custom_field_definitions) && count($product->custom_field_definitions) > 0)
                                @php
                                    $globalFields = [];
                                    $paxFields = [];
                                    $hotelDirectory = \App\Models\Hotel::query()
                                        ->orderBy('name')
                                        ->get(['name', 'address', 'google_maps_url'])
                                        ->map(fn ($hotel) => ['name' => $hotel->name, 'address' => $hotel->address, 'google_maps_url' => $hotel->google_maps_url])
                                        ->all();
                                    foreach($product->custom_field_definitions as $def) {
                                        if ($def['is_per_passenger'] ?? false) {
                                            $paxFields[] = $def;
                                        } else {
                                            $globalFields[] = $def;
                                        }
                                    }
                                    $qty = intval($quantity) > 0 ? intval($quantity) : 1;
                                @endphp

                                <div class="product-config-section product-required-fields">
                                    <h4 class="product-config-section-title">{{ __('Informations requises') }}</h4>
                                    
                                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                                        
                                        @if(count($globalFields) > 0)
                                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
                                                @foreach($globalFields as $def)
                                                    @php 
                                                        $key = !empty($def['key']) ? $def['key'] : \Illuminate\Support\Str::slug($def['name'] ?? 'custom', '_');
                                                        $isRequired = $def['is_required'] ?? false;
                                                        $modelKey = "customValues.{$key}";
                                                        $hotelAddressModelKey = "customValues.{$key}_address";
                                                        $hotelMapsModelKey = "customValues.{$key}_google_maps_url";
                                                    @endphp
                                                    <div wire:key="global-field-{{ $key }}" style="grid-column: span {{ in_array($def['type'], ['textarea', 'file', 'client_hotel']) ? '2' : '1' }}; display: flex; flex-direction: column; gap: 0.25rem;">
                                                        <label style="font-size: 0.85rem; font-weight: 600; color: #374151;">
                                                            {{ $def['name'] }} {!! $isRequired ? '<span style="color:#dc2626;">*</span>' : '' !!}
                                                        </label>

                                                        @if($def['type'] === 'textarea')
                                                            <textarea wire:model="{{ $modelKey }}" placeholder="{{ $def['placeholder'] ?? '' }}" rows="2" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none; font-family:inherit;"></textarea>
                                                        @elseif($def['type'] === 'select')
                                                            <select wire:model="{{ $modelKey }}" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none; background:white;">
                                                            <option value="">{{ $def['placeholder'] ?? __('Sélectionnez...') }}</option>
                                                                @foreach($def['choices'] ?? [] as $choice)
                                                                    <option value="{{ $choice }}">{{ $choice }}</option>
                                                                @endforeach
                                                            </select>
                                                        @elseif($def['type'] === 'date')
                                                            <input type="date" wire:model="{{ $modelKey }}" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none;">
                                                        @elseif($def['type'] === 'number')
                                                            <input type="number" wire:model="{{ $modelKey }}" placeholder="{{ $def['placeholder'] ?? '' }}" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none;">
                                                        @elseif($def['type'] === 'client_hotel')
                                                            <div x-data="{
                                                                manualHotel: false,
                                                                open: false,
                                                                query: '',
                                                                selected: @entangle($modelKey).live,
                                                                address: @entangle($hotelAddressModelKey).live,
                                                                mapsUrl: @entangle($hotelMapsModelKey).live,
                                                                hotels: @js($hotelDirectory),
                                                                get results() {
                                                                    const needle = (this.query || '').toLocaleLowerCase();
                                                                    return this.hotels.filter(hotel => hotel.name.toLocaleLowerCase().includes(needle)).slice(0, 12);
                                                                },
                                                                choose(hotel) { this.selected = hotel.name; this.address = hotel.address || ''; this.mapsUrl = hotel.google_maps_url || ''; this.query = hotel.name; this.open = false; }
                                                            }" x-init="query = selected || ''; const savedHotel = hotels.find(hotel => hotel.name === selected); if (savedHotel) { address = address || savedHotel.address || ''; mapsUrl = mapsUrl || savedHotel.google_maps_url || ''; }" style="display:flex; flex-direction:column; gap:0.5rem;">
                                                                <div x-show="!manualHotel" x-effect="if (!manualHotel) query = selected || ''">
                                                                    <label style="display:block; margin-bottom:0.35rem; font-size:0.78rem; font-weight:700; color:#475569;">🏢 {{ __('Rechercher dans la base d’hébergements') }}</label>
                                                                    <div style="position:relative;">
                                                                        <input type="text" x-model="query" x-on:click.stop="open = true" x-on:focus="open = true" x-on:input="open = true" x-on:keydown.escape="open = false" placeholder="{{ __('Tapez le nom d’un hébergement…') }}" autocomplete="off" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:0.375rem; font-size:0.85rem; outline:none;">
                                                                        <div x-show="open" x-on:click.outside="open = false" style="position:absolute; z-index:30; top:calc(100% - 1px); left:0; right:0; max-height:15rem; overflow-y:auto; border:1px solid #cbd5e1; border-radius:0 0 0.5rem 0.5rem; background:#fff; box-shadow:0 10px 20px rgba(15,23,42,.12);" x-cloak>
                                                                        <template x-for="hotel in results" :key="hotel.name">
                                                                            <button type="button" x-on:click="choose(hotel)" style="display:block; width:100%; padding:0.65rem 0.75rem; border:0; border-bottom:1px solid #f1f5f9; background:#fff; text-align:left; cursor:pointer;">
                                                                                <span x-text="hotel.name" style="display:block; color:#1e293b; font-size:.85rem; font-weight:700;"></span>
                                                                                <span x-show="hotel.address" x-text="hotel.address" style="display:block; margin-top:.12rem; color:#64748b; font-size:.75rem;"></span>
                                                                            </button>
                                                                        </template>
                                                                        <p x-show="results.length === 0" style="margin:0; padding:.75rem; color:#64748b; font-size:.8rem;">{{ __('Aucun hébergement correspondant') }}</p>
                                                                        </div>
                                                                    </div>
                                                                    <a x-show="mapsUrl" x-bind:href="mapsUrl" target="_blank" rel="noopener" style="display:inline-flex; align-items:center; gap:.35rem; margin-top:.55rem; color:#2563eb; font-size:.78rem; font-weight:700; text-decoration:underline;">📍 {{ __('Vérifier sur Google Maps') }}</a>
                                                                    <button type="button" x-on:click="manualHotel = true" style="margin-top:.65rem; padding:.55rem .7rem; border:1px solid var(--tk-accent-border); border-radius:.45rem; background:var(--tk-accent-soft); color:var(--tk-accent); font-size:.8rem; font-weight:800; text-align:left; cursor:pointer;">✏️ {{ __('L’hébergement n’est pas dans la liste ? Saisie manuelle') }}</button>
                                                                </div>
                                                                <div x-show="manualHotel" style="display:none;">
                                                                    <label style="display:block; margin-bottom:0.35rem; font-size:0.78rem; font-weight:700; color:#475569;">{{ __('Nom de l’hébergement') }}</label>
                                                                    <input type="text" x-model="selected" placeholder="{{ $def['placeholder'] ?? __('Saisissez le nom de l’hébergement…') }}" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:0.375rem; font-size:0.85rem; outline:none;">
                                                                    <label style="display:block; margin:.65rem 0 .35rem; font-size:.78rem; font-weight:700; color:#475569;">{{ __('Adresse de l’hébergement') }}</label>
                                                                    <textarea x-model="address" rows="2" placeholder="{{ __('Adresse complète (optionnel)') }}" style="width:100%; padding:.6rem; border:1px solid #cbd5e1; border-radius:.375rem; font-size:.85rem; outline:none; font-family:inherit;"></textarea>
                                                                    <label style="display:block; margin:.65rem 0 .35rem; font-size:.78rem; font-weight:700; color:#475569;">{{ __('Lien Google Maps') }} <span style="font-weight:500; color:#64748b;">({{ __('optionnel') }})</span></label>
                                                                    <input type="url" x-model="mapsUrl" placeholder="https://maps.google.com/..." style="width:100%; padding:.6rem; border:1px solid #cbd5e1; border-radius:.375rem; font-size:.85rem; outline:none;">
                                                                    <a x-show="mapsUrl" x-bind:href="mapsUrl" target="_blank" rel="noopener" style="display:inline-flex; align-items:center; gap:.35rem; margin-top:.55rem; color:#2563eb; font-size:.78rem; font-weight:700; text-decoration:underline;">📍 {{ __('Vérifier sur Google Maps') }}</a>
                                                                    <button type="button" x-on:click="manualHotel = false" style="margin-top:0.55rem; padding:0; border:0; background:transparent; color:var(--tk-accent); font-size:0.78rem; font-weight:700; text-align:left; cursor:pointer;">{{ __('Revenir à la recherche dans la base') }}</button>
                                                                </div>
                                                            </div>
                                                        @elseif($def['type'] === 'toggle')
                                                            <div style="display:flex; align-items:center; gap:0.5rem; margin-top:0.25rem;">
                                                                <input type="checkbox" wire:model="{{ $modelKey }}" id="check_global_{{ $key }}" style="width:18px; height:18px; accent-color:var(--primary-600);">
                                                                <label for="check_global_{{ $key }}" style="font-size:0.85rem; color:#4b5563;">Oui, je confirme</label>
                                                            </div>
                                                        @elseif($def['type'] === 'file')
                                                            <div x-data="{ isUploading: false, isUploaded: false, fileName: '' }"
                                                                 x-on:livewire-upload-start="isUploading = true"
                                                                 x-on:livewire-upload-finish="isUploading = false; isUploaded = true"
                                                                 x-on:livewire-upload-error="isUploading = false"
                                                                 style="position: relative; margin-top: 0.25rem;">

                                                                <label style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; border: 2px dashed #cbd5e1; border-radius: 0.75rem; padding: 1.25rem 1rem; background: #ffffff; cursor: pointer; transition: all 0.2s ease-in-out;"
                                                                       onmouseover="this.style.borderColor='var(--primary-600)'; this.style.background='var(--tk-accent-soft)';"
                                                                       onmouseout="this.style.borderColor='#cbd5e1'; this.style.background='#ffffff';">

                                                                    <div x-show="!isUploading && !isUploaded" style="display: flex; flex-direction: column; align-items: center; gap: 0.35rem; text-align: center;">
                                                                        <svg style="width: 28px; height: 28px; color: var(--tk-accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                                        </svg>
                                                                        <span style="font-size: 0.85rem; font-weight: 600; color: #334155;">Cliquez ou glissez un fichier ici</span>
                                                                        <span style="font-size: 0.75rem; color: #94a3b8;">PDF, JPG, PNG (Max. 10 Mo)</span>
                                                                    </div>

                                                                    <div x-show="isUploading" style="display: flex; align-items: center; gap: 0.5rem; color: var(--tk-accent); font-size: 0.85rem; font-weight: 600;">
                                                                        <svg class="animate-spin-custom" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24">
                                                                            <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                            <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                        </svg>
                                                                        <span>Transfert en cours...</span>
                                                                    </div>

                                                                    <div x-show="isUploaded && !isUploading" style="display: flex; align-items: center; gap: 0.5rem; color: #166534; font-size: 0.85rem; font-weight: 700;">
                                                                        <svg style="width: 22px; height: 22px; color: #16a34a;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                                        </svg>
                                                <span x-text="fileName ? @js(__('Fichier prêt :')) + ' ' + fileName : @js(__('Document transmis avec succès !'))"></span>
                                                                    </div>

                                                                    <input type="file"
                                                                           wire:model="{{ $modelKey }}"
                                                                           accept=".pdf,.png,.jpg,.jpeg,.doc,.docx"
                                                                           x-on:change="fileName = $event.target.files[0] ? $event.target.files[0].name : ''"
                                                                           style="display: none;">
                                                                </label>

                                                                @error($modelKey) <span style="color:#dc2626; font-size:0.75rem; font-weight:500; margin-top:0.25rem; display:block;">{{ $message }}</span> @enderror
                                                            </div>
                                                        @else
                                                            <input type="text" wire:model="{{ $modelKey }}" placeholder="{{ $def['placeholder'] ?? '' }}" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none;">
                                                        @endif
                                                        @error($modelKey) <span style="color:#dc2626; font-size:0.75rem; font-weight:500;">{{ $message }}</span> @enderror
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if(count($paxFields) > 0)
                                            @if(count($globalFields) > 0)
                                                <div style="height: 1px; background: #e2e8f0; margin: 0.25rem 0;"></div>
                                            @endif
                                            
                                            <div x-data="{ activeTab: 0 }" style="display: flex; flex-direction: column; gap: 0.75rem;">
                                                
                                                <div class="pax-tabs-row" style="display: flex; gap: 0.4rem; overflow-x: auto; padding: 2px 0;">
                                                    @for($i = 0; $i < $qty; $i++)
                                                        <button type="button" 
                                                                @click="activeTab = {{ $i }}" 
                                                                class="pax-tab-item"
                                                                :class="{ 'pax-tab-item-active': activeTab === {{ $i }} }">
                                                            <svg width="14" height="14" style="width: 14px !important; height: 14px !important; flex-shrink: 0 !important; opacity: 0.75; display: block;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                                            <span>Pax {{ $i + 1 }}</span>
                                                        </button>
                                                    @endfor
                                                </div>

                                                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1rem; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.02);">
                                                    @for($i = 0; $i < $qty; $i++)
                                                        <div x-show="activeTab === {{ $i }}" style="display:none; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;" :style="activeTab === {{ $i }} ? 'display:grid;' : 'display:none;'">
                                                            @foreach($paxFields as $def)
                                                                @php 
                                                                    $key = !empty($def['key']) ? $def['key'] : \Illuminate\Support\Str::slug($def['name'] ?? 'custom', '_');
                                                                    $isRequired = $def['is_required'] ?? false;
                                                                    $modelKey = "customValues.{$key}.{$i}";
                                                                @endphp
                                                                
                                                                <div wire:key="field-{{ $key }}-{{ $i }}" style="grid-column: span {{ in_array($def['type'], ['textarea', 'file']) ? '2' : '1' }}; display: flex; flex-direction: column; gap: 0.25rem;">
                                                                    <label style="font-size: 0.85rem; font-weight: 600; color: #374151;">
                                                                        {{ $def['name'] }} {!! $isRequired ? '<span style="color:#dc2626;">*</span>' : '' !!}
                                                                    </label>

                                                                    @if($def['type'] === 'textarea')
                                                                        <textarea wire:model="{{ $modelKey }}" placeholder="{{ $def['placeholder'] ?? '' }}" rows="2" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none; font-family:inherit;"></textarea>
                                                                    @elseif($def['type'] === 'select')
                                                                        <select wire:model="{{ $modelKey }}" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none; background:white;">
                                                                                    <option value="">{{ $def['placeholder'] ?? __('Sélectionnez...') }}</option>
                                                                            @foreach($def['choices'] ?? [] as $choice)
                                                                                <option value="{{ $choice }}">{{ $choice }}</option>
                                                                            @endforeach
                                                                        </select>
                                                                    @elseif($def['type'] === 'date')
                                                                        <input type="date" wire:model="{{ $modelKey }}" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none;">
                                                                    @elseif($def['type'] === 'number')
                                                                        <input type="number" wire:model="{{ $modelKey }}" placeholder="{{ $def['placeholder'] ?? '' }}" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none;">
                                                                    @elseif($def['type'] === 'client_hotel')
                                                                        <div x-data="{
                                                                            manualHotel: false,
                                                                            open: false,
                                                                            query: '',
                                                                            selected: @entangle($modelKey).live,
                                                                            hotels: @js($hotelDirectory),
                                                                            get results() {
                                                                                const needle = (this.query || '').toLocaleLowerCase();
                                                                                return this.hotels.filter(hotel => hotel.name.toLocaleLowerCase().includes(needle)).slice(0, 12);
                                                                            },
                                                                            choose(hotel) { this.selected = hotel.name; this.query = hotel.name; this.open = false; }
                                                                        }" x-init="query = selected || ''" style="display:flex; flex-direction:column; gap:0.5rem;">
                                                                            <div x-show="!manualHotel" x-effect="if (!manualHotel) query = selected || ''" style="position:relative;">
                                                                                <label style="display:block; margin-bottom:0.35rem; font-size:0.78rem; font-weight:700; color:#475569;">🏢 {{ __('Rechercher dans la base d’hébergements') }}</label>
                                                                                <input type="text" x-model="query" x-on:click.stop="open = true" x-on:focus="open = true" x-on:input="open = true" x-on:keydown.escape="open = false" placeholder="{{ __('Tapez le nom d’un hébergement…') }}" autocomplete="off" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:0.375rem; font-size:0.85rem; outline:none;">
                                                                                <div x-show="open" x-on:click.outside="open = false" style="position:absolute; z-index:30; top:100%; left:0; right:0; max-height:15rem; overflow-y:auto; margin-top:0.25rem; border:1px solid #cbd5e1; border-radius:0.5rem; background:#fff; box-shadow:0 10px 20px rgba(15,23,42,.12);" x-cloak>
                                                                                    <template x-for="hotel in results" :key="hotel.name">
                                                                                        <button type="button" x-on:click="choose(hotel)" style="display:block; width:100%; padding:0.65rem 0.75rem; border:0; border-bottom:1px solid #f1f5f9; background:#fff; text-align:left; cursor:pointer;">
                                                                                            <span x-text="hotel.name" style="display:block; color:#1e293b; font-size:.85rem; font-weight:700;"></span>
                                                                                            <span x-show="hotel.address" x-text="hotel.address" style="display:block; margin-top:.12rem; color:#64748b; font-size:.75rem;"></span>
                                                                                        </button>
                                                                                    </template>
                                                                                    <p x-show="results.length === 0" style="margin:0; padding:.75rem; color:#64748b; font-size:.8rem;">{{ __('Aucun hébergement correspondant') }}</p>
                                                                                </div>
                                                                                <button type="button" x-on:click="manualHotel = true" style="margin-top:0.55rem; padding:0; border:0; background:transparent; color:var(--tk-accent); font-size:0.78rem; font-weight:700; text-align:left; cursor:pointer;">{{ __('L’hébergement n’est pas dans la liste ? Saisie manuelle') }}</button>
                                                                            </div>
                                                                            <div x-show="manualHotel" style="display:none;">
                                                                                <label style="display:block; margin-bottom:0.35rem; font-size:0.78rem; font-weight:700; color:#475569;">{{ __('Nom de l’hébergement') }}</label>
                                                                                <input type="text" x-model="selected" placeholder="{{ $def['placeholder'] ?? __('Saisissez le nom de l’hébergement…') }}" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:0.375rem; font-size:0.85rem; outline:none;">
                                                                                <button type="button" x-on:click="manualHotel = false" style="margin-top:0.55rem; padding:0; border:0; background:transparent; color:var(--tk-accent); font-size:0.78rem; font-weight:700; text-align:left; cursor:pointer;">{{ __('Revenir à la recherche dans la base') }}</button>
                                                                            </div>
                                                                        </div>
                                                                    @elseif($def['type'] === 'toggle')
                                                                        <div style="display:flex; align-items:center; gap:0.5rem; margin-top:0.25rem;">
                                                                            <input type="checkbox" wire:model="{{ $modelKey }}" id="check_pax_{{ $key }}_{{ $i }}" style="width:14px; height:14px; accent-color:var(--primary-600);">
                                                                            <label for="check_pax_{{ $key }}_{{ $i }}" style="font-size:0.85rem; color:#4b5563;">Oui, je confirme</label>
                                                                        </div>
                                                                    @elseif($def['type'] === 'file')
                                                                        <div x-data="{ isUploading: false, isUploaded: false, fileName: '' }"
                                                                             x-on:livewire-upload-start="isUploading = true"
                                                                             x-on:livewire-upload-finish="isUploading = false; isUploaded = true"
                                                                             x-on:livewire-upload-error="isUploading = false"
                                                                             style="position: relative; margin-top: 0.25rem;">

                                                                            <label style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; border: 2px dashed #cbd5e1; border-radius: 0.75rem; padding: 1.25rem 1rem; background: #ffffff; cursor: pointer; transition: all 0.2s ease-in-out;"
                                                                                   onmouseover="this.style.borderColor='var(--primary-600)'; this.style.background='var(--tk-accent-soft)';"
                                                                                   onmouseout="this.style.borderColor='#cbd5e1'; this.style.background='#ffffff';">

                                                                                <div x-show="!isUploading && !isUploaded" style="display: flex; flex-direction: column; align-items: center; gap: 0.35rem; text-align: center;">
                                                                                    <svg style="width: 28px; height: 28px; color: var(--tk-accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                                                    </svg>
                                                                                    <span style="font-size: 0.85rem; font-weight: 600; color: #334155;">Cliquez ou glissez le document du Pax {{ $i + 1 }}</span>
                                                                                    <span style="font-size: 0.75rem; color: #94a3b8;">PDF, JPG, PNG (Max. 10 Mo)</span>
                                                                                </div>

                                                                                <div x-show="isUploading" style="display: flex; align-items: center; gap: 0.5rem; color: var(--tk-accent); font-size: 0.85rem; font-weight: 600;">
                                                                                    <svg class="animate-spin-custom" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24">
                                                                                        <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                                        <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                                    </svg>
                                                                                    <span>Transfert en cours...</span>
                                                                                </div>

                                                                                <div x-show="isUploaded && !isUploading" style="display: flex; align-items: center; gap: 0.5rem; color: #166534; font-size: 0.85rem; font-weight: 700;">
                                                                                    <svg style="width: 22px; height: 22px; color: #16a34a;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                                                    </svg>
                                                                                    <span x-text="fileName ? @js(__('Fichier prêt :')) + ' ' + fileName : @js(__('Document transmis avec succès !'))"></span>
                                                                                </div>

                                                                                <input type="file"
                                                                                       wire:model="{{ $modelKey }}"
                                                                                       accept=".pdf,.png,.jpg,.jpeg,.doc,.docx"
                                                                                       x-on:change="fileName = $event.target.files[0] ? $event.target.files[0].name : ''"
                                                                                       style="display: none;">
                                                                            </label>

                                                                        @error($modelKey) <span style="color:#dc2626; font-size:0.75rem; font-weight:500; margin-top:0.25rem; display:block;">{{ $message }}</span> @enderror
                                                                    </div>
                                                                @else
                                                                    <input type="text" wire:model="{{ $modelKey }}" placeholder="{{ $def['placeholder'] ?? '' }}" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none;">
                                                                @endif
                                                                @error($modelKey) <span style="color:#dc2626; font-size:0.75rem; font-weight:500;">{{ $message }}</span> @enderror
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endfor
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- RECAPITULATIF DU TARIF POUR PRODUIT STANDARD (NON TRANSPORT) --}}
                        <div class="product-config-section product-price-summary">
                            <h4 class="product-config-section-title">{{ $product->category_id === 4 ? __('Détail du tarif guidage') : __('Récapitulatif du tarif') }}</h4>
                            @php
                                $estimate = $this->getEstimatedPrice();
                            @endphp

                            @if($estimate['is_on_demand'])
                                <div style="background: #fff7ed; border: 1px solid #ffedd5; color: #c2410c; padding: 1.5rem; border-radius: 0.75rem; text-align: center;">
                                    <h4 style="margin:0 0 0.5rem 0; font-size: 1.1rem; font-weight: 600;">⚠️ {{ __('Tarif sur Devis') }}</h4>
                                    <p style="margin:0; font-size: 0.85rem; opacity: 0.9;">{{ __('Cette prestation nécessite une cotation personnalisée.') }}</p>
                                </div>
                            @else
                                <div style="display: flex; flex-direction: column;">
                                    
                                    @if(($estimate['is_guiding'] ?? false) && ($estimate['has_date'] ?? false))
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 0.65rem;">
                                            <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">{{ __('Type de prestation') }}</span>
                                            <span style="font-size: 0.9rem; font-weight: 700; color: #334155; text-align: right;">{{ filled($estimate['guiding_variant_names'] ?? []) ? implode(' · ', $estimate['guiding_variant_names']) : __('Aucune déclinaison sélectionnée') }}</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                                            <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">
                                                {{ __('Tarif de la saison') }}@if(filled($estimate['guiding_season_name'] ?? null)) <span style="font-weight: 500;">({{ $estimate['guiding_season_name'] }})</span>@endif
                                            </span>
                                            <span style="font-size: 0.9rem; font-weight: 700; color: #334155; white-space: nowrap;">{{ number_format($estimate['guiding_variant_total'] ?? 0, 0, '.', ' ') }} ¥</span>
                                        </div>
                                        @if($estimate['base_adult_total'] !== null)
                                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                                                <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">
                                                    {{ __('Tarif adultes') }} ({{ $ageSelectorAdults }} {{ __('adulte(s)') }})@if(filled($estimate['guiding_pax_label'] ?? null)) <span style="font-weight: 500;">· {{ $estimate['guiding_pax_label'] }}</span>@endif
                                                </span>
                                                <span style="font-size: 0.9rem; font-weight: 700; color: #334155; white-space: nowrap;">{{ number_format($estimate['base_adult_total'], 0, '.', ' ') }} ¥</span>
                                            </div>
                                            @if($product->hasChildSelector() && $ageSelectorChildren > 0)
                                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                                                    <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">{{ __('Tarif enfants') }} ({{ $ageSelectorChildren }} {{ __('enfant(s)') }})</span>
                                                    <span style="font-size: 0.9rem; font-weight: 700; color: #334155; white-space: nowrap;">{{ ($estimate['base_child_total'] ?? 0) == 0 ? __('Gratuit selon la grille tarifaire') : number_format($estimate['base_child_total'], 0, '.', ' ') . ' ¥' }}</span>
                                                </div>
                                            @endif
                                            @if($product->hasTeenSelector() && $ageSelectorTeenagers > 0)
                                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                                                    <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">{{ __('Tarif adolescents') }} ({{ $ageSelectorTeenagers }} {{ __('adolescent(s)') }})</span>
                                                    <span style="font-size: 0.9rem; font-weight: 700; color: #334155; white-space: nowrap;">{{ ($estimate['base_teen_total'] ?? 0) == 0 ? __('Gratuit selon la grille tarifaire') : number_format($estimate['base_teen_total'], 0, '.', ' ') . ' ¥' }}</span>
                                                </div>
                                            @endif
                                        @else
                                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                                                <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">
                                                    {{ __('Tarif lié au nombre de pax') }}@if(filled($estimate['guiding_pax_label'] ?? null)) <span style="font-weight: 500;">({{ $estimate['guiding_pax_label'] }})</span>@endif
                                                </span>
                                                <span style="font-size: 0.9rem; font-weight: 700; color: #334155; white-space: nowrap;">{{ number_format($estimate['total_base'], 0, '.', ' ') }} ¥</span>
                                            </div>
                                        @endif
                                    @elseif($product->usesAgeSelector() && $estimate['base_adult_total'] !== null)
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                                            <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">
                                                {{ __('Tarif adulte') }} ({{ $ageSelectorAdults }} × {{ number_format($estimate['base_adult_unit'], 0, '.', ' ') }} ¥)
                                            </span>
                                            <span style="font-size: 0.9rem; font-weight: 700; color: #334155;">{{ number_format($estimate['base_adult_total'], 0, '.', ' ') }} ¥</span>
                                        </div>
                                        @if($product->hasChildSelector())<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                            <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">
                                                {{ __('Tarif enfant') }} ({{ $ageSelectorChildren }} × {{ number_format($estimate['base_child_unit'], 0, '.', ' ') }} ¥)
                                            </span>
                                            <span style="font-size: 0.9rem; font-weight: 700; color: #334155;">{{ number_format($estimate['base_child_total'], 0, '.', ' ') }} ¥</span>
                                        </div>@endif
                                        @if($product->hasTeenSelector())
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                                <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">
                                                    {{ __('Tarif adolescent') }} ({{ $ageSelectorTeenagers }} × {{ number_format($estimate['base_teen_unit'], 0, '.', ' ') }} ¥)
                                                </span>
                                                <span style="font-size: 0.9rem; font-weight: 700; color: #334155;">{{ number_format($estimate['base_teen_total'], 0, '.', ' ') }} ¥</span>
                                            </div>
                                        @endif
                                    @else
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                            <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">
                                                {{ __('Prestation') }} {{ $estimate['has_date'] ? '('.$estimate['qty'].' '.__('pax').')' : '('.__('À partir de').')' }}
                                            </span>
                                            <span style="font-size: 0.9rem; font-weight: 700; color: #334155;">{{ ($estimate['has_date'] || $estimate['total_base'] > 0) ? number_format($estimate['total_base'], 0, '.', ' ') . ' ¥' : '—' }}</span>
                                        </div>
                                    @endif

                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                                        @if(($estimate['is_guiding'] ?? false) && ($estimate['guiding_optional_total'] ?? 0) > 0)
                                            <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">{{ __('Options complémentaires') }}</span>
                                            <span style="font-size: 0.9rem; font-weight: 700; color: #334155;">+ {{ number_format($estimate['guiding_optional_total'], 0, '.', ' ') }} ¥</span>
                                        @elseif(!($estimate['is_guiding'] ?? false) && $estimate['total_options'] > 0)
                                            @if($product->usesAgeSelector())
                                                <div style="display:flex; flex-direction:column; gap:0.2rem; font-size:0.85rem; font-weight:600; color:#64748b;">
                                                    <span>{{ __('Options adultes') }}</span>
                                                    @if($product->hasChildSelector())<span>{{ __('Options enfants') }}</span>@endif
                                                    @if($product->hasTeenSelector())<span>{{ __('Options adolescents') }}</span>@endif
                                                </div>
                                                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:0.2rem; font-size:0.9rem; font-weight:700; color:#334155;">
                                                    <span>+ {{ number_format($estimate['options_adult_total'], 0, '.', ' ') }} ¥</span>
                                                    @if($product->hasChildSelector())<span>+ {{ number_format($estimate['options_child_total'], 0, '.', ' ') }} ¥</span>@endif
                                                    @if($product->hasTeenSelector())<span>+ {{ number_format($estimate['options_teen_total'], 0, '.', ' ') }} ¥</span>@endif
                                                </div>
                                            @else
                                                <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">{{ __('Déclinaisons / Options') }}</span>
                                                <span style="font-size: 0.9rem; font-weight: 700; color: #334155;">+ {{ number_format($estimate['total_options'], 0, '.', ' ') }} ¥</span>
                                            @endif
                                        @endif
                                    </div>
                                    
                                    <div style="height: 1px; background: #e2e8f0; margin: 0.25rem 0 0.6rem;"></div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                                        <span style="font-size: 0.85rem; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: 0.04em;">Total</span>
                                        <span style="font-size: 1.6rem; font-weight: 700; color: var(--tk-accent); line-height: 1; letter-spacing: -0.02em;">
                                            {{ ($estimate['has_date'] || $estimate['grand_total'] > 0) ? number_format($estimate['grand_total'], 0, '.', ' ') . ' ¥' : '—' }}
                                        </span>
                                    </div>
                                    
                                    <div>
                                        @if(!$estimate['has_date'])
                                            <div style="margin-top: 0.6rem; font-size: 0.75rem; color: #d97706; background: #fffbeb; padding: 0.5rem 0.6rem; border-radius: 0.5rem; display: flex; align-items: center; gap: 0.5rem; line-height: 1.4;">
                                                <svg style="width:16px; height:16px; flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                {{ __('Veuillez sélectionner une date d’activité pour obtenir le tarif exact.') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                        @endif

                        {{-- DOSSIER ET ACTION --}}
                        <div class="product-config-section product-final-step" style="display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 0;">
                            <h4 class="product-config-section-title" style="margin-bottom: 0;">{{ __('Ajouter au dossier') }}</h4>
                            <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <label style="font-size: 0.85rem; font-weight: 700; color: #374151;">Dossier de destination</label>
                                    {{ $this->createFolderAction }}
                                </div>
                                
                                <select wire:model="selectedFolderId" style="width: 100%; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; outline: none; background: white; color: #111827;">
                                    <option value="">-- Choisir un dossier voyage --</option>
                                    @foreach($this->foldersList as $folder)
                                        <option value="{{ $folder->id }}">
                                            {{ $folder->folder_name }} 
                                            @if($folder->start_date && $folder->end_date)
                                                (Du {{ \Carbon\Carbon::parse($folder->start_date)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($folder->end_date)->format('d/m/Y') }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('selectedFolderId') <span style="color: #dc2626; font-size: 0.75rem; font-weight: 500;">{{ $message }}</span> @enderror
                            </div>

                            @php $importantDocs = $product->importantDocumentLinks(); @endphp
                            @if(count($importantDocs) > 0)
                                <div role="alert" style="margin-bottom: 0.75rem; background: #fef2f2; border: 2px solid #dc2626; border-radius: 0.6rem; padding: 0.75rem 0.85rem;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 800; color: #991b1b; font-size: 0.9rem; margin-bottom: 0.4rem;">
                                        <svg style="width:18px; height:18px; flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" /></svg>
                                        {{ __('Informations importantes à lire') }}
                                    </div>
                                    <ul style="margin: 0 0 0.5rem 0; padding-left: 1.1rem; font-size: 0.82rem; color: #7f1d1d;">
                                        @foreach($importantDocs as $doc)
                                            <li style="margin-bottom: 0.2rem;"><a href="{{ $doc['url'] }}" target="_blank" rel="noopener" style="color: #991b1b; font-weight: 700; text-decoration: underline;">{{ $doc['label'] }}</a></li>
                                        @endforeach
                                    </ul>
                                    <p style="margin: 0; font-size: 0.75rem; color: #7f1d1d; line-height: 1.4;">{{ __('En ajoutant cette prestation au dossier, vous reconnaissez avoir pris connaissance de ces informations.') }}</p>
                                </div>
                            @endif

                            <button type="button" wire:click="addToFolder" style="width: 100%; background: var(--primary-600); color: white; border: none; padding: 0.7rem; border-radius: 0.5rem; font-weight: 700; font-size: 0.9rem; cursor: pointer; text-align: center; box-shadow: 0 4px 6px -1px color-mix(in srgb, var(--primary-600) 20%, transparent); transition: background 0.2s;" onmouseover="this.style.background='var(--primary-700)'" onmouseout="this.style.background='var(--primary-600)'">
                                Ajouter cette prestation au dossier
                            </button>
                        </div>

                        {{-- Barre de réservation fixe (mobile / tablette) : total estimé et accès direct à l'étape finale --}}
                        @php $barEstimate = $this->getEstimatedPrice(); @endphp
                        <div class="product-mobile-bar">
                            <div style="display: flex; flex-direction: column; line-height: 1.15; min-width: 0;">
                                <span style="font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em;">{{ __('Total estimé') }}</span>
                                <span style="font-size: 1.15rem; font-weight: 800; color: var(--tk-accent); white-space: nowrap;">
                                    @if($barEstimate['is_on_demand'] ?? false)
                                        {{ __('Sur devis') }}
                                    @else
                                        {{ (($barEstimate['has_date'] ?? false) || ($barEstimate['grand_total'] ?? 0) > 0) ? number_format($barEstimate['grand_total'] ?? 0, 0, '.', ' ') . ' ¥' : '—' }}
                                    @endif
                                </span>
                            </div>
                            <button type="button"
                                    onclick="document.querySelector('.product-final-step')?.scrollIntoView({ behavior: 'smooth', block: 'center' })"
                                    style="flex: none; background: var(--primary-600); color: #ffffff; border: none; padding: 0.65rem 1.1rem; border-radius: 0.5rem; font-weight: 700; font-size: 0.85rem; cursor: pointer;">
                                {{ __('Ajouter au dossier') }}
                            </button>
                        </div>

                        {{-- CONDITIONS D'ANNULATION --}}
                        @php
                            $isSpecific = $product->cancellation_type === 'specific' && !empty($product->cancellation_specifics);
                            $generalPolicy = \App\Models\Setting::first()?->general_cancellation_policy 
                                          ?? \App\Models\Setting::first()?->cancellation_policy 
                                          ?? '';
                            $hasPolicyToDisplay = $isSpecific || !empty($generalPolicy);
                            // Conditions spécifiques : texte brut (Textarea) à échapper ; conditions générales : HTML de l'éditeur riche.
                            $policyContent = $isSpecific ? e($product->cancellation_specifics) : $generalPolicy;
                        @endphp

                        @if($hasPolicyToDisplay)
                        <div x-data="{ expanded: false }" style="background: white; border-radius: 1.25rem; border: 1px solid #f1f5f9; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.03); overflow: hidden;">
                            <button @click="expanded = !expanded" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; background: transparent; border: none; cursor: pointer; text-align: left; outline: none;">
                                <span style="font-size: 0.95rem; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 0.5rem;">
                                    <svg width="20" height="20" style="width: 20px !important; height: 20px !important; flex-shrink: 0 !important; color: #94a3b8; display: block;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ __('Détails des conditions d’annulation') }}
                                </span>
                                <svg width="20" height="20" :style="expanded ? 'transform: rotate(180deg); width: 20px !important; height: 20px !important; flex-shrink: 0 !important; color: #64748b; transition: transform 0.2s; display: block;' : 'width: 20px !important; height: 20px !important; flex-shrink: 0 !important; color: #64748b; transition: transform 0.2s; display: block;'" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>
                            
                            <div x-show="expanded" x-collapse style="display: none;">
                                <div style="padding: 0 1.5rem 1.5rem 1.5rem;">
                                    <div style="background: #f8fafc; border-left: 4px solid #cbd5e1; padding: 1rem; border-radius: 0 0.5rem 0.5rem 0;">
                                        <div style="color: #475569; line-height: 1.6; font-size: 0.85rem; white-space: pre-wrap;">{!! $policyContent !!}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                    </div>
                @endif

                {{-- BLOC 2 : VUE ADMINISTRATEUR --}}
                @if(!$this->activeAgencyId && $this->isAdmin)
                    <div style="background: white; border-radius: 1.25rem; padding: 2.25rem 2rem; border: 1px solid #e0f2fe; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.04);">
                        <div style="color: #0284c7; background: #e0f2fe; width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto; border: 1px solid #bae6fd;">
                            <svg style="width: 26px; height: 26px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <h3 style="font-size: 1.3rem; font-weight: 600; color: #0f172a; margin: 0 0 0.5rem 0;">{{ __('Vue Administrateur') }}</h3>
                        <p style="color: #475569; font-size: 0.9rem; line-height: 1.6; margin: 0;">{{ __('Vous explorez le catalogue avec un compte administrateur. Pour utiliser la fonction d’ajout au panier, veuillez vous connecter avec un véritable compte Agence partenaire.') }}</p>
                    </div>
                @endif

                {{-- BLOC 3 : PROMO CONNEXION POUR UTILISATEUR NON-MEMBRE --}}
                @if(!$this->activeAgencyId && !$this->isAdmin)
                    <div style="background: white; border-radius: 1.25rem; padding: 2.25rem 2rem; border: 1px solid #f1f5f9; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.04);">
                        <div style="color: #d97706; background: #fffbeb; width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto; border: 1px solid #fde68a;">
                            <svg style="width: 26px; height: 26px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        </div>
                        <h3 style="font-size: 1.3rem; font-weight: 600; color: #1e293b; margin: 0 0 0.5rem 0;">{{ __('Tarifs et Réservations Pro') }}</h3>
                        <p style="color: #64748b; font-size: 0.9rem; line-height: 1.6; margin: 0 0 1.75rem 0;">{{ __('Les grilles tarifaires japonaises (saisonnières/enfants) ainsi que les modules de devis direct sont réservés aux agences de voyages partenaires.') }}</p>
                        
                        <a href="{{ route('filament.agency.auth.login') }}" style="display: block; width: 100%; background: var(--primary-600); color: white; text-decoration: none; padding: 0.9rem; border-radius: 0.5rem; font-weight: 700; font-size: 0.9rem; text-align: center; box-sizing: border-box; box-shadow: 0 4px 6px -1px color-mix(in srgb, var(--primary-600) 20%, transparent); transition: background 0.2s;" onmouseover="this.style.background='var(--primary-700)'" onmouseout="this.style.background='var(--primary-600)'">
                            {{ __('S’identifier sur le portail') }}
                        </a>
                    </div>
                @endif

            </div>
        </div>

    </div>
</x-filament-panels::page>
