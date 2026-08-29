<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SystemHealthAlert
{
    public function sendOnce(string $key, string $subject, array $lines, bool $force = false): bool
    {
        $cacheKey = $this->cacheKey($key);
        $cooldown = max(1, (int) config('monitoring.alert_cooldown_minutes', 360));

        if (! $force && ! Cache::add($cacheKey, true, now()->addMinutes($cooldown))) {
            return false;
        }

        try {
            $recipients = $this->recipients();

            if ($recipients === []) {
                Log::warning('Alerte de supervision non envoyée : aucun destinataire configuré.', [
                    'monitoring_key' => $key,
                ]);

                return false;
            }

            Mail::raw(implode("\n", [
                'Une vérification automatique du Portail Takada nécessite votre attention.',
                '',
                ...$lines,
                '',
                'Consultez le backoffice et les journaux du serveur si nécessaire.',
            ]), function ($message) use ($recipients, $subject): void {
                $message
                    ->to($recipients)
                    ->subject('[Portail Takada] '.$subject);
            });

            Log::warning('Alerte de supervision envoyée.', [
                'monitoring_key' => $key,
                'recipient_count' => count($recipients),
            ]);

            return true;
        } catch (Throwable $exception) {
            Cache::forget($cacheKey);
            report($exception);

            Log::critical('Impossible d’envoyer une alerte de supervision.', [
                'monitoring_key' => $key,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    public function clear(string $key): void
    {
        Cache::forget($this->cacheKey($key));
    }

    private function cacheKey(string $key): string
    {
        return 'system-health-alert:'.sha1($key);
    }

    /** @return array<int, string> */
    private function recipients(): array
    {
        $emails = Setting::query()->first()?->technicalAlertEmails() ?? [];

        if ($emails === [] && filled(config('mail.admin_alert_address'))) {
            $emails = [(string) config('mail.admin_alert_address')];
        }

        return array_values(array_unique($emails));
    }
}
