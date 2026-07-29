<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GoogleCalendarWidget extends Widget
{
    protected string $view = 'filament.widgets.google-calendar-widget';
    // Force le widget à prendre exactement 1 colonne sur les 3
    protected int | string | array $columnSpan = 1;
    protected static ?int $sort = 3;

    public function getUpcomingEventsGroupedByDate(): array
    {
        $calendarId = env('GOOGLE_CALENDAR_ID');
        $keyFilePath = storage_path('app/google-credentials.json');

        if (!$calendarId || !file_exists($keyFilePath)) {
            return [];
        }

        try {
            $client = new \Google\Client();
            $client->setAuthConfig($keyFilePath);
            $client->addScope(\Google\Service\Calendar::CALENDAR_READONLY);
            
            $service = new \Google\Service\Calendar($client);

            $optParams = [
                'timeMin' => now()->startOfDay()->toRfc3339String(),
                'timeMax' => now()->addDays(7)->endOfDay()->toRfc3339String(),
                'singleEvents' => true,
                'orderBy' => 'startTime',
                'maxResults' => 20,
            ];

            $results = $service->events->listEvents($calendarId, $optParams);
            $events = $results->getItems() ?? [];

            $grouped = [];
            foreach ($events as $event) {
                $isAllDay = !empty($event->start->date);
                $start = Carbon::parse($isAllDay ? $event->start->date : $event->start->dateTime);
                
                $dateKey = $start->format('Y-m-d');
                $grouped[$dateKey][] = $event;
            }

            return $grouped;

        } catch (\Exception $e) {
            Log::error('Erreur Lecture Google Calendar Widget : ' . $e->getMessage());
            return [];
        }
    }
}