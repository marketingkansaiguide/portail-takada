<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Spatie\GoogleCalendar\Event;
use Carbon\Carbon;

class GoogleCalendarWidget extends Widget
{
    protected static string $view = 'filament.widgets.google-calendar-widget';

    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 1;

    // Cette fonction sera appelée par la vue pour récupérer les événements
    public function getTodayEvents(): array|iterable
    {
        try {
            // On récupère tous les événements entre minuit et 23h59 aujourd'hui
            return Event::get(Carbon::today(), Carbon::today()->endOfDay());
        } catch (\Exception $e) {
            // En cas de problème d'API ou de configuration
            return [];
        }
    }
}