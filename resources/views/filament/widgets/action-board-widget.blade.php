<x-filament-widgets::widget>
    <x-filament::section>
        
        <x-slot name="heading">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <x-filament::icon icon="heroicon-o-clipboard-document-list" class="w-6 h-6 text-primary-500" style="width: 1.5rem; height: 1.5rem;" />
                <span>Plan d'Action & Alertes</span>
            </div>
        </x-slot>

        <x-slot name="description">
            Tâches nécessitant une intervention.
        </x-slot>

        @if($this->pendingTasks->isEmpty())
            <div style="text-align: center; padding: 3rem 0; color: gray;">
                <x-filament::icon icon="heroicon-o-sparkles" class="text-success-500" style="width: 3rem; height: 3rem; margin: 0 auto 1rem auto;" />
                <p style="font-size: 1.125rem; font-weight: 600;">Tout est à jour !</p>
                <p style="font-size: 0.875rem;">Aucune action n'est requise sur vos dossiers en cours.</p>
            </div>
        @else
            <div style="display: flex; flex-direction: column; gap: 1.5rem; margin-top: 1rem;">
                
                @foreach($this->pendingTasks as $folderId => $tasks)
                    @php $folder = $tasks->first()->folder; @endphp

                    <x-filament::section compact>
                        
                        <x-slot name="heading">
                            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 1.1rem;">
                                <x-filament::icon icon="heroicon-m-user" class="text-primary-500" style="width: 1.25rem; height: 1.25rem;" />
                                <span>{{ $folder->lead_traveler_name ?? $folder->reference }}</span>
                            </div>
                        </x-slot>

                        <x-slot name="description">
                            <div style="font-size: 0.85rem; margin-top: 0.25rem;">
                                Agence : <strong style="color: var(--primary-600);">{{ $folder->agency->name }}</strong> 
                                <span style="margin: 0 0.5rem; color: gray;">|</span> 
                                Dates : <strong>{{ $folder->start_date ? $folder->start_date->format('d/m/y') : '?' }}</strong> 
                                <x-filament::icon icon="heroicon-m-arrow-right" style="display: inline; width: 0.75rem; height: 0.75rem; color: gray; margin: 0 0.25rem;" />
                                <strong>{{ $folder->end_date ? $folder->end_date->format('d/m/y') : '?' }}</strong>
                            </div>
                        </x-slot>

                        <div style="display: flex; flex-direction: column; padding: 0.5rem 0;">
                            @foreach($tasks as $task)
                                <div style="display: flex; align-items: flex-start; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid rgba(128, 128, 128, 0.15);">
                                    
                                    <button 
                                        wire:click="mountAction('validateTask', { task_id: {{ $task->id }} })"
                                        wire:loading.attr="disabled"
                                        style="cursor: pointer; background: transparent; border: none; padding: 0; margin-top: 2px;"
                                        title="Valider la tâche"
                                    >
                                        <x-filament::icon icon="heroicon-o-check-circle" style="width: 1.5rem; height: 1.5rem; color: #9ca3af; transition: color 0.2s;" onmouseover="this.style.color='#10b981'" onmouseout="this.style.color='#9ca3af'" />
                                    </button>

                                    <div style="display: flex; align-items: flex-start; gap: 0.5rem; padding-top: 0.125rem;">
                                        <x-filament::icon :icon="$task->icon" class="{{ $task->color }}" style="width: 1.25rem; height: 1.25rem; flex-shrink: 0;" />
                                        <span style="font-size: 0.9rem; line-height: 1.4;" class="dark:text-white">
                                            {{ $task->description }}
                                        </span>
                                    </div>
                                    
                                </div>
                            @endforeach
                        </div>

                        <div style="margin-top: 1rem; padding-top: 1rem; display: flex; justify-content: flex-end;">
                            <x-filament::button tag="a" href="{{ \App\Filament\Resources\Folders\FolderResource::getUrl('edit', ['record' => $folder->id]) }}" color="primary" icon="heroicon-m-arrow-top-right-on-square">
                                Accéder au dossier
                            </x-filament::button>
                        </div>

                    </x-filament::section>
                @endforeach

            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
    
</x-filament-widgets::widget>