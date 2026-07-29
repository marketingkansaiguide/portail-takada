<x-filament-widgets::widget class="h-full flex flex-col">
    {{-- On s'assure que la section Filament prend toute la hauteur disponible --}}
    <x-filament::section style="height: 100%; display: flex; flex-direction: column;">
        <x-slot name="heading">
            <div style="display: flex; align-items: center; gap: 8px;">
                {{-- L'icône avec sa taille stricte restaurée --}}
                <x-heroicon-o-calendar style="width: 20px; height: 20px; color: #f59e0b; flex-shrink: 0;" />
                <span style="font-size: 1rem; font-weight: 600;">{{ __('Agenda (7 j)') }}</span>
            </div>
        </x-slot>

        @php
            $groupedEvents = $this->getUpcomingEventsGroupedByDate();
            \Carbon\Carbon::setLocale(app()->getLocale());
        @endphp

        <div style="display: flex; flex-direction: column; gap: 16px; flex: 1; overflow-y: auto; padding-right: 8px;">
            @forelse($groupedEvents as $date => $events)
                @php
                    $carbonDate = \Carbon\Carbon::parse($date);
                    $dateTitle = $carbonDate->isToday() ? __('Aujourd\'hui') : ($carbonDate->isTomorrow() ? __('Demain') : ucfirst($carbonDate->translatedFormat('D d M')));
                @endphp

                <div style="position: relative;">
                    <h3 style="display: flex; align-items: center; gap: 8px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #6b7280; margin-bottom: 8px;">
                        <span style="{{ $carbonDate->isToday() ? 'color: #d97706;' : '' }}">{{ $dateTitle }}</span>
                        <div style="height: 1px; flex: 1; background-color: #e5e7eb;"></div>
                    </h3>

                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        @foreach($events as $event)
                            @php
                                $isAllDay = !empty($event->start->date);
                                $start = \Carbon\Carbon::parse($isAllDay ? $event->start->date : $event->start->dateTime);
                            @endphp

                            <div style="display: flex; align-items: flex-start; gap: 8px;">
                                <div style="width: 35px; flex-shrink: 0; text-align: right; padding-top: 2px;">
                                    @if($isAllDay)
                                        <span style="font-size: 0.65rem; font-weight: 700; color: #9ca3af;">{{ __('Jour') }}</span>
                                    @else
                                        <div style="font-size: 0.75rem; font-weight: 700; color: #111827;">{{ $start->format('H:i') }}</div>
                                    @endif
                                </div>
                                <div style="position: relative; display: flex; flex-direction: column; align-items: center; padding-top: 6px;">
                                    <div style="width: 8px; height: 8px; border-radius: 50%; background-color: #f59e0b; flex-shrink: 0;"></div>
                                </div>
                                <div style="flex: 1;">
                                    {{-- 💡 Le titre complet s'affiche et passe à la ligne au lieu d'être coupé ! --}}
                                    <h4 style="font-size: 0.8rem; font-weight: 600; color: #111827; margin: 0; line-height: 1.3; word-break: break-word;">
                                        {{ $event->summary }}
                                    </h4>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div style="text-align: center; padding: 20px 0; color: #9ca3af; font-size: 0.8rem;">
                    {{ __('Aucun événement à venir.') }}
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>