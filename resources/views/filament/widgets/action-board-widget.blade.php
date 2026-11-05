<x-filament-widgets::widget>
    @php
        $summary = $this->taskSummary;
    @endphp

    <x-filament::section>
        <x-slot name="heading">
            <div style="display: flex; align-items: center; gap: .6rem;">
                <x-filament::icon icon="heroicon-o-clipboard-document-list" class="text-primary-500" style="width: 1.5rem; height: 1.5rem;" />
                <span>{{ __('Plan d’action') }}</span>
                @if($summary['total'])
                    <span style="display: inline-flex; min-width: 1.5rem; height: 1.5rem; align-items: center; justify-content: center; border-radius: 999px; background: var(--primary-600); color: white; font-size: .75rem; font-weight: 700;">{{ $summary['total'] }}</span>
                @endif
            </div>
        </x-slot>

        <x-slot name="description">{{ __('Les tâches actives sont regroupées par dossier.') }}</x-slot>

        @if($this->pendingTasks->isEmpty())
            <div style="text-align: center; padding: 3rem 0; color: gray;">
                <x-filament::icon icon="heroicon-o-sparkles" class="text-success-500" style="width: 3rem; height: 3rem; margin: 0 auto 1rem;" />
                <p style="font-size: 1.125rem; font-weight: 700;">{{ __('Tout est à jour !') }}</p>
                <p style="font-size: .875rem;">{{ __('Aucune action n’est requise sur vos dossiers en cours.') }}</p>
            </div>
        @else
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                @foreach($this->pendingTasks as $folderId => $tasks)
                    @php
                        $folder = $tasks->first()->folder;
                        $criticalCount = $tasks->where('priority', 'critical')->count();
                        $folderTitle = $folder->folder_name ?: $folder->lead_traveler_name ?: $folder->reference;
                    @endphp

                    <div style="border: 1px solid rgba(148,163,184,.45); border-left: 4px solid {{ $criticalCount ? '#dc2626' : '#2563eb' }}; border-radius: 0.5rem; overflow: hidden; background: rgba(255,255,255,.35);">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1rem 1rem .75rem; border-bottom: 1px solid rgba(148,163,184,.22);">
                            <div style="min-width: 0;">
                                <div style="display: flex; align-items: center; gap: .5rem; flex-wrap: wrap;">
                                    <x-filament::icon icon="heroicon-m-folder" class="text-primary-500" style="width: 1.2rem; height: 1.2rem; flex: none;" />
                                    <strong style="font-size: 1rem;">{{ $folderTitle }}</strong>
                                    <span style="font-size: .75rem; color: #64748b; font-weight: 700;">{{ $folder->reference }}</span>
                                </div>
                                <div style="display: flex; gap: .4rem 1rem; flex-wrap: wrap; margin: .45rem 0 0 1.7rem; color: #64748b; font-size: .8rem;">
                                    <span>{{ __('Agence :') }} <strong>{{ $folder->agency?->name ?? __('Non renseignée') }}</strong></span>
                                    <span>{{ __('Séjour :') }} <strong>{{ $folder->start_date?->format('d/m/y') ?? '—' }} → {{ $folder->end_date?->format('d/m/y') ?? '—' }}</strong></span>
                                </div>
                            </div>

                            <x-filament::button tag="a" href="{{ \App\Filament\Resources\Folders\FolderResource::getUrl('edit', ['record' => $folder->id]) }}" color="gray" icon="heroicon-m-arrow-top-right-on-square" size="sm">{{ __('Dossier') }}</x-filament::button>
                        </div>

                        <div style="display: flex; flex-direction: column;">
                            @foreach($tasks as $task)
                                @php
                                    $palette = match($task->priority) {
                                        'critical' => ['background' => '#fef2f2', 'border' => '#fecaca', 'text' => '#b91c1c'],
                                        'attention' => ['background' => '#fffbeb', 'border' => '#fde68a', 'text' => '#a16207'],
                                        default => ['background' => '#f8fafc', 'border' => '#dbeafe', 'text' => '#1d4ed8'],
                                    };
                                @endphp
                                <div style="display: flex; align-items: flex-start; gap: .75rem; padding: .8rem 1rem; border-bottom: 1px solid rgba(148,163,184,.16);">
                                    <button wire:click="mountAction('validateTask', { task_id: {{ $task->id }} })" wire:loading.attr="disabled" style="cursor: pointer; border: 1px solid #cbd5e1; border-radius: 999px; background: white; padding: .22rem; margin-top: .05rem; flex: none;" title="{{ __('Marquer comme effectuée') }}">
                                        <x-filament::icon icon="heroicon-m-check" style="width: 1rem; height: 1rem; color: #64748b;" />
                                    </button>
                                    <x-filament::icon :icon="$task->icon ?: 'heroicon-m-clipboard-document-check'" class="{{ $task->color ?: 'text-primary-500' }}" style="width: 1.2rem; height: 1.2rem; margin-top: .2rem; flex: none;" />
                                    <div style="min-width: 0; flex: 1;">
                                        <div style="display: flex; align-items: center; gap: .45rem; flex-wrap: wrap; margin-bottom: .25rem;">
                                            <span style="font-size: .75rem; color: {{ $palette['text'] }}; font-weight: 700;">{{ $task->type_label }}</span>
                                        </div>
                                        <p style="margin: 0; font-size: .9rem; line-height: 1.45;">{{ $task->description }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
