@if ($folder->isInternal())
<form wire:submit="quickAddItem" style="margin-bottom:.75rem;width:100%">
    {{ $this->quickAddForm }}
    <div style="margin-top:.5rem">
        <x-filament::button type="submit" size="sm">{{ __('Ajouter') }}</x-filament::button>
        <span class="tk-c-muted" style="margin-left:.5rem">{{ __('Le panneau de la ligne s’ouvre ensuite pour le reste.') }}</span>
    </div>
</form>
@endif
