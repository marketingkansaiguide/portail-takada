<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-calendar class="w-6 h-6 text-primary-500" />
                {{ __('Planning du jour (Google Calendar)') }}
            </div>
        </x-slot>

        @php
            $events = $this->getTodayEvents();
        @endphp

        <div class="space-y-4">
            @forelse($events as $event)
                <div class="flex items-start gap-4 p-4 rounded-lg bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10">
                    
                    {{-- Horaires --}}
                    <div class="flex flex-col items-center justify-center w-16 shrink-0 text-primary-600 dark:text-primary-400">
                        @if($event->isAllDayEvent())
                            <span class="text-sm font-bold uppercase">{{ __('Toute la journée') }}</span>
                        @else
                            <span class="text-lg font-bold">{{ Carbon\Carbon::parse($event->startDateTime)->format('H:i') }}</span>
                            <span class="text-xs text-gray-500">- {{ Carbon\Carbon::parse($event->endDateTime)->format('H:i') }}</span>
                        @endif
                    </div>

                    {{-- Ligne de séparation visuelle --}}
                    <div class="w-1 h-12 rounded-full bg-primary-500"></div>

                    {{-- Détails de l'événement --}}
                    <div class="flex-1">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ $event->name }}
                        </h4>
                        @if($event->description)
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
                                {{ strip_tags($event->description) }}
                            </p>
                        @endif
                    </div>
                    
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-6 text-center text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-face-smile class="w-12 h-12 mb-2 text-gray-400" />
                    <p>{{ __('Aucun événement prévu dans l\'agenda aujourd\'hui.') }}</p>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>