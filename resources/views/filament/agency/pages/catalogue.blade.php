<x-filament-panels::page>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
        @foreach($this->products as $product)
            <x-filament::section class="flex flex-col">
                
                <div style="margin-bottom: 1rem; border-radius: 0.5rem; overflow: hidden;">
                    @if($product->images && is_array($product->images) && count($product->images) > 0)
                        <img src="{{ Storage::url($product->images[0]) }}" alt="{{ $product->name }}" style="width: 100%; height: 200px; object-fit: cover;">
                    @else
                        <div style="width: 100%; height: 200px; background-color: #dde8b9; display: flex; align-items: center; justify-content: center;">
                            <span style="color: #096a61; font-weight: bold; font-size: 1.2rem;">{{ $product->name }}</span>
                        </div>
                    @endif
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                    <h3 style="font-size: 1.25rem; font-weight: bold; margin: 0; color: #111827;">{{ $product->name }}</h3>
                    @if($product->category)
                        <x-filament::badge color="success">
                            {{ $product->category->name }}
                        </x-filament::badge>
                    @endif
                </div>
                
                <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 1.5rem; flex-grow: 1;">
                    {{ \Illuminate\Support\Str::limit($product->description, 120) }}
                </p>
                
                @if($product->is_lottery)
                    <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 0.75rem; margin-bottom: 1.5rem; border-radius: 0.25rem;">
                        <p style="color: #d97706; font-size: 0.8rem; margin: 0; font-weight: 500;">⚠️ Soumis à loterie ou quantité limitée</p>
                    </div>
                @endif
                
                <div style="margin-top: auto;">
                    @if($this->isAuthenticated)
                        <x-filament::button tag="a" href="#" color="primary" style="width: 100%; justify-content: center;">
                            Voir les Tarifs & Ajouter
                        </x-filament::button>
                    @else
                        <div style="text-align: center; padding: 1rem; background-color: #f9fafb; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                            <p style="font-size: 0.75rem; color: #6b7280; margin-bottom: 0.5rem;">Tarifs réservés aux agences</p>
                            <x-filament::button tag="a" href="{{ filament()->getLoginUrl() }}" color="primary" variant="outlined" style="width: 100%; justify-content: center;">
                                Se connecter
                            </x-filament::button>
                        </div>
                    @endif
                </div>

            </x-filament::section>
        @endforeach
    </div>
    
    @if(count($this->products) === 0)
        <x-filament::section>
            <div style="text-align: center; padding: 2rem; color: #6b7280; font-size: 1.1rem;">
                Aucun produit n'est actuellement disponible dans le catalogue public.
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>