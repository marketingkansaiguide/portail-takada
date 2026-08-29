<x-filament-panels::page>
    @php
        $catalogueSettings = $this->catalogueSettings;
        $carouselEnabled = $catalogueSettings['useCarousel'];
    @endphp
    <div style="display: flex; flex-direction: column; gap: 2rem;">
        
        <div style="display: flex; flex-direction: column; gap: 1rem; border-bottom: 2px solid #f3f4f6; padding-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="font-size: 2rem; font-weight: 800; color: #096a61; margin: 0;">{{ __('Explorez le Japon') }}</h1>
                    <p style="color: #64748b; font-size: 1rem; margin-top: 0.25rem;">{{ __('Découvrez nos activités exclusives pour vos groupes et voyageurs.') }}</p>
                    @if($categoryId)
                        <a href="{{ route('filament.agency.pages.catalogue') }}" style="display: inline-flex; margin-top: 0.65rem; color: #096a61; font-size: 0.85rem; font-weight: 700; text-decoration: none;">← {{ __('Voir toutes les catégories') }}</a>
                    @endif
                </div>
                
                <div style="position: relative; width: 100%; max-width: 400px;">
                    <input type="text" wire:model.live="search" placeholder="{{ __('Rechercher une activité...') }}"
                           style="width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; border: 1px solid #e2e8f0; border-radius: 9999px; font-size: 0.9rem; outline: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                    <svg style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: #94a3b8;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
            </div>
        </div>

        @foreach($this->productSections as $section)
            <section style="display: flex; flex-direction: column; gap: 1rem;">
                @php
                    $carouselId = 'category-carousel-' . $loop->index;
                @endphp
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem;">
                    <div style="display: flex; align-items: baseline; gap: 0.6rem;">
                        <h2 style="font-size: 1.4rem; font-weight: 800; color: #1e293b; margin: 0;">{{ $section['name'] }}</h2>
                        <span style="font-size: 0.8rem; color: #94a3b8; font-weight: 600;">{{ $section['total'] }} {{ $section['total'] > 1 ? __('prestations') : __('prestation') }}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        @if($carouselEnabled && $section['products']->count() > 1)
                            <button type="button" aria-label="{{ __('Prestation précédente') }}" onclick="document.getElementById('{{ $carouselId }}').scrollBy({ left: -340, behavior: 'smooth' })" style="display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border: 1px solid #cbd5e1; border-radius: 9999px; background: white; color: #096a61; cursor: pointer; font-size: 1.1rem;">←</button>
                            <button type="button" aria-label="{{ __('Prestation suivante') }}" onclick="document.getElementById('{{ $carouselId }}').scrollBy({ left: 340, behavior: 'smooth' })" style="display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border: 1px solid #cbd5e1; border-radius: 9999px; background: white; color: #096a61; cursor: pointer; font-size: 1.1rem;">→</button>
                        @endif
                        @if(!$categoryId && blank($search) && $section['categoryId'] && $section['total'] > $section['products']->count())
                            <a href="{{ route('filament.agency.pages.catalogue', ['category' => $section['categoryId']]) }}" style="color: #096a61; font-size: 0.85rem; font-weight: 800; text-decoration: none; white-space: nowrap; margin-left: 0.35rem;">{{ __('Voir plus') }} →</a>
                        @endif
                    </div>
                </div>

                <div id="{{ $carouselId }}" style="{{ $carouselEnabled ? 'display: flex; gap: 1.25rem; overflow-x: auto; padding: 0.25rem 0.2rem 1rem; scroll-snap-type: x mandatory; scrollbar-width: thin; scrollbar-color: #99c9c3 transparent;' : 'display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;' }}">
                    @foreach($section['products'] as $product)
                <div data-takada-page-link="{{ route('filament.agency.pages.view-product', ['record' => $product->id]) }}" style="{{ $carouselEnabled ? 'flex: 0 0 min(320px, 82vw); scroll-snap-align: start;' : '' }} background: white; border-radius: 1rem; overflow: hidden; border: 1px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s; cursor: pointer;"
                     onmouseover="this.style.transform='translateY(-4px)'; this.style.box-shadow='0 10px 15px -3px rgba(0,0,0,0.1)';" 
                     onmouseout="this.style.transform='translateY(0)'; this.style.box-shadow='0 4px 6px -1px rgba(0,0,0,0.05)';"
                     onclick="window.location.href='{{ route('filament.agency.pages.view-product', ['record' => $product->id]) }}'">
                    
                    <div style="height: 200px; background-color: #f8fafc; position: relative; overflow: hidden;">
                        @php 
                            $firstImage = null;
                            if (!empty($product->images) && is_array($product->images)) {
                                $firstImage = $product->images[0];
                            }
                        @endphp

                        @if($firstImage)
                            <img src="{{ asset('storage/' . $firstImage) }}" alt="{{ $product->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            <div style="display: flex; height: 100%; align-items: center; justify-content: center; color: #cbd5e1; background: #f8fafc;">
                                <svg style="width: 48px; height: 48px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </div>
                        @endif
                        
                        <div style="position: absolute; top: 1rem; left: 1rem; background: rgba(9, 106, 97, 0.9); color: white; padding: 0.2rem 0.75rem; border-radius: 0.5rem; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                            {{ $product->category->name ?? __('Activité') }}
                        </div>
                    </div>

                    <div style="padding: 1.25rem; display: flex; flex-direction: column; gap: 0.75rem;">
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: #1e293b; margin: 0; line-height: 1.3;">{{ $product->name }}</h3>
                        
                        <p style="font-size: 0.85rem; color: #64748b; line-height: 1.5; height: 3.8rem; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">
                            {{ strip_tags($product->description) }}
                        </p>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem; border-top: 1px solid #f1f5f9; padding-top: 1rem;">
                            @if(auth('agency')->check())
                                @php
                                    $minPrice = null;
                                    if ($product->productPeriods) {
                                        foreach($product->productPeriods as $period) {
                                            if ($period->productPrices) {
                                                foreach($period->productPrices as $price) {
                                                    if ($price->isExtraPaxRate()) {
                                                        continue;
                                                    }

                                                    if ($minPrice === null || $price->price < $minPrice) {
                                                        $minPrice = $price->price;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                @endphp

                                <div style="display: flex; flex-direction: column;">
                                    @if($product->is_on_demand)
                                        <span style="font-size: 0.85rem; font-weight: 800; color: #c2410c; margin-top:0.5rem;">{{ __('Sur Devis') }}</span>
                                    @elseif($minPrice !== null)
                                        <span style="font-size: 0.65rem; color: #94a3b8; text-transform: uppercase; font-weight: 700;">{{ __('À partir de') }}</span>
                                        <span style="font-size: 1.25rem; font-weight: 800; color: #096a61;">{{ number_format($minPrice, 0, '.', ' ') }} ¥</span>
                                    @else
                                        <span style="font-size: 0.85rem; font-weight: 800; color: #096a61; margin-top:0.5rem;">{{ __('Prix saisonnier') }}</span>
                                    @endif
                                </div>
                            @else
                                <span style="font-size: 0.75rem; font-weight: 600; color: #d97706; background: #fffbeb; padding: 0.25rem 0.6rem; border-radius: 0.375rem;">{{ __('Tarifs pro sur connexion') }}</span>
                            @endif

                            <a href="{{ route('filament.agency.pages.view-product', ['record' => $product->id]) }}" 
                               style="background: #096a61; color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.8rem; font-weight: 700; transition: background 0.2s;">
                                {{ __('Découvrir') }}
                            </a>
                        </div>
                    </div>
                </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if($this->products->isEmpty())
            <div style="text-align: center; padding: 4rem 2rem; background: #f9fafb; border-radius: 1rem; border: 2px dashed #e2e8f0;">
                <p style="color: #94a3b8; font-size: 1.1rem;">{{ __('Aucun produit ne correspond à votre recherche.') }}</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
