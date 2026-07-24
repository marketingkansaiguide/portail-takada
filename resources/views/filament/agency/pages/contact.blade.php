<x-filament-panels::page>
    <form wire:submit="send">
        
        {{ $this->form }}

        {{-- L'espacement est forcé en dur (style) pour contourner la purge de Tailwind --}}
        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
            
            <x-filament::button 
                tag="a" 
                href="{{ route('filament.agency.pages.catalogue') }}" 
                color="gray"
            >
                {{ __('Annuler') }}
            </x-filament::button>

            <x-filament::button 
                type="submit" 
                color="primary" 
                icon="heroicon-m-paper-airplane"
            >
                {{ __('Envoyer le message') }}
            </x-filament::button>
            
        </div>
        
    </form>
</x-filament-panels::page>