<x-filament-widgets::widget>
    <!-- Conteneur principal natif Filament -->
    <x-filament::section icon="heroicon-o-clipboard-document-check" icon-color="primary">
        <x-slot name="heading">
            Plan d'Action de l'Équipe
        </x-slot>
        <x-slot name="description">
            Tâches nécessitant une intervention. Cliquez sur la coche pour valider et archiver la tâche dans l'historique du dossier.
        </x-slot>

        @if($this->pendingTasksByFolder->isEmpty())
            <div style="text-align: center; padding: 3rem 0;" class="text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-face-smile" class="w-12 h-12 text-emerald-500" style="margin: 0 auto 1rem auto;" />
                <p style="font-weight: 500; font-size: 1.125rem;" class="text-gray-900 dark:text-white">Tout est à jour !</p>
                <p style="font-size: 0.875rem;">Aucune action n'est requise sur vos dossiers en cours.</p>
            </div>
        @else
            <!-- Grille native Filament (s'adapte à l'écran) -->
            <x-filament::grid default="1" md="2" xl="3" gap="6" style="margin-top: 1rem;">
                
                @foreach($this->pendingTasksByFolder as $folderId => $tasks)
                    @php $folder = $tasks->first()->folder; @endphp
                    
                    <!-- Carte native Filament pour CHAQUE dossier -->
                    <x-filament::section compact>
                        
                        <!-- TITRE : Nom du Pax Leader -->
                        <x-slot name="heading">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <x-filament::icon icon="heroicon-o-user" class="w-5 h-5 text-primary-500" />
                                <!-- 💡 Ajuste "lead_traveler_name" si ta colonne porte un autre nom dans ta base -->
                                <span>{{ $folder->lead_traveler_name ?? $folder->reference }}</span>
                            </div>
                        </x-slot>

                        <!-- SOUS-TITRE : Agence et Dates -->
                        <x-slot name="description">
                            <div style="margin-top: 0.25rem; font-size: 0.875rem; line-height: 1.6;">
                                <strong class="text-gray-900 dark:text-white">Agence :</strong> {{ $folder->agency->name }}<br>
                                <strong class="text-gray-900 dark:text-white">Dates :</strong> 
                                {{ $folder->start_date ? $folder->start_date->format('d/m/Y') : 'À définir' }} 
                                <x-filament::icon icon="heroicon-m-arrow-right" class="w-3 h-3 text-gray-400" style="display: inline; margin: 0 0.25rem;" /> 
                                {{ $folder->end_date ? $folder->end_date->format('d/m/Y') : 'À définir' }}
                            </div>
                        </x-slot>

                        <!-- BOUTON EN HAUT À DROITE -->
                        <x-slot name="headerEnd">
                            <x-filament::button 
                                tag="a" 
                                href="{{ \App\Filament\Resources\Folders\FolderResource::getUrl('edit', ['record' => $folder->id]) }}" 
                                size="sm"
                                color="gray">
                                Ouvrir
                            </x-filament::button>
                        </x-slot>

                        <!-- LISTE DES TÂCHES (Sécurisée avec style inline pour éviter la casse CSS) -->
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            @foreach($tasks as $task)
                                <div class="bg-gray-50 dark:bg-gray-800/50" style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid rgba(156, 163, 175, 0.2);">
                                    
                                    <!-- Bouton cliquable pour valider la tâche -->
                                    <button 
                                        wire:click="markAsDone({{ $task->id }})"
                                        wire:loading.attr="disabled"
                                        style="cursor: pointer; margin-top: 2px; flex-shrink: 0; background: none; border: none; padding: 0;"
                                        title="Marquer comme effectuée"
                                    >
                                        <x-filament::icon icon="heroicon-o-check-circle" class="w-6 h-6 text-gray-400 hover:text-emerald-500" style="transition: color 0.2s;" />
                                    </button>

                                    <!-- Texte et icône de la tâche -->
                                    <div style="display: flex; align-items: flex-start; gap: 0.5rem; padding-top: 0.125rem;">
                                        <x-filament::icon :icon="$task->icon" class="w-5 h-5 {{ $task->color }}" style="flex-shrink: 0;" />
                                        <span style="font-size: 0.875rem; line-height: 1.25rem;" class="text-gray-700 dark:text-gray-300">
                                            {{ $task->description }}
                                        </span>
                                    </div>

                                </div>
                            @endforeach
                        </div>

                    </x-filament::section>
                @endforeach

            </x-filament::grid>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>