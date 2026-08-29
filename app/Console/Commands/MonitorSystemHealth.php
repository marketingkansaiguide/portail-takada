<?php

namespace App\Console\Commands;

use App\Services\FolderDriveStorage;
use App\Services\SystemHealthAlert;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MonitorSystemHealth extends Command
{
    protected $signature = 'monitor:system
        {--force : Envoie les alertes même si une alerte identique est encore en période de silence}
        {--no-notify : Affiche le diagnostic sans envoyer d’e-mail}';

    protected $description = 'Surveille les sauvegardes, la file d’attente, l’espace disque et les erreurs applicatives.';

    public function handle(FolderDriveStorage $driveStorage, SystemHealthAlert $alerts): int
    {
        $issues = [];

        $this->checkDatabaseBackup($driveStorage, $alerts, $issues);
        $this->checkQueue($alerts, $issues);
        $this->checkDisk($alerts, $issues);
        $this->checkApplicationErrors($alerts, $issues);

        if ($issues === []) {
            $this->info('Supervision : aucun incident détecté.');

            return self::SUCCESS;
        }

        foreach ($issues as $key => $issue) {
            $this->warn($issue['subject']);

            if (! $this->option('no-notify')) {
                $alerts->sendOnce(
                    $key,
                    $issue['subject'],
                    $issue['lines'],
                    (bool) $this->option('force'),
                );
            }
        }

        return self::SUCCESS;
    }

    /** @param array<string, array{subject: string, lines: array<int, string>}> $issues */
    private function checkDatabaseBackup(FolderDriveStorage $driveStorage, SystemHealthAlert $alerts, array &$issues): void
    {
        try {
            $backup = $driveStorage->latestDatabaseBackup();

            if ($backup === null) {
                $issues['database-backup-missing'] = [
                    'subject' => 'Aucune sauvegarde de base de données détectée',
                    'lines' => ['Aucun fichier de sauvegarde n’a été trouvé dans le Drive partagé.'],
                ];

                return;
            }

            $createdAt = Carbon::parse($backup['created_at']);
            $maxAge = max(1, (int) config('monitoring.backup_max_age_hours', 8));

            if ($createdAt->lt(now()->subHours($maxAge))) {
                $issues['database-backup-stale'] = [
                    'subject' => 'Sauvegarde de base de données trop ancienne',
                    'lines' => [
                        "Dernière sauvegarde : {$backup['name']}.",
                        'Date : '.$createdAt->format('d/m/Y H:i').'.',
                        "Seuil d’alerte : {$maxAge} heures.",
                    ],
                ];

                return;
            }

            $alerts->clear('database-backup-missing');
            $alerts->clear('database-backup-stale');
            $alerts->clear('database-backup-unavailable');
        } catch (Throwable $exception) {
            report($exception);

            $issues['database-backup-unavailable'] = [
                'subject' => 'Vérification des sauvegardes Google Drive indisponible',
                'lines' => ['La dernière sauvegarde n’a pas pu être vérifiée : '.$this->safeMessage($exception).'.'],
            ];
        }
    }

    /** @param array<string, array{subject: string, lines: array<int, string>}> $issues */
    private function checkQueue(SystemHealthAlert $alerts, array &$issues): void
    {
        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->count();

            if ($failed > 0) {
                $issues['queue-failed-jobs'] = [
                    'subject' => 'Des tâches en arrière-plan ont échoué',
                    'lines' => ["{$failed} tâche(s) sont présentes dans failed_jobs."],
                ];
            } else {
                $alerts->clear('queue-failed-jobs');
            }
        }

        $connectionName = (string) config('queue.default');
        $connection = config("queue.connections.{$connectionName}", []);

        if (($connection['driver'] ?? null) !== 'database' || ! Schema::hasTable((string) ($connection['table'] ?? 'jobs'))) {
            return;
        }

        $staleAfter = max(1, (int) config('monitoring.queue_stale_minutes', 20));
        $database = filled($connection['connection'] ?? null)
            ? DB::connection($connection['connection'])
            : DB::connection();
        $stale = $database->table($connection['table'] ?? 'jobs')
            ->where('created_at', '<', now()->subMinutes($staleAfter)->getTimestamp())
            ->count();

        if ($stale > 0) {
            $issues['queue-stale-jobs'] = [
                'subject' => 'La file d’attente semble bloquée',
                'lines' => [
                    "{$stale} tâche(s) attendent depuis plus de {$staleAfter} minutes.",
                    'Vérifiez le worker de file d’attente du VPS.',
                ],
            ];
        } else {
            $alerts->clear('queue-stale-jobs');
        }
    }

    /** @param array<string, array{subject: string, lines: array<int, string>}> $issues */
    private function checkDisk(SystemHealthAlert $alerts, array &$issues): void
    {
        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());

        if (! is_numeric($total) || ! is_numeric($free) || $total <= 0) {
            return;
        }

        $usedPercentage = (int) round((1 - ($free / $total)) * 100);
        $threshold = min(99, max(1, (int) config('monitoring.disk_max_usage_percent', 85)));

        if ($usedPercentage >= $threshold) {
            $issues['disk-usage-high'] = [
                'subject' => 'Espace disque du VPS bientôt saturé',
                'lines' => [
                    "Utilisation actuelle : {$usedPercentage} %.",
                    "Seuil d’alerte : {$threshold} %.",
                ],
            ];
        } else {
            $alerts->clear('disk-usage-high');
        }
    }

    /** @param array<string, array{subject: string, lines: array<int, string>}> $issues */
    private function checkApplicationErrors(SystemHealthAlert $alerts, array &$issues): void
    {
        $window = max(1, (int) config('monitoring.application_error_window_minutes', 15));
        $threshold = max(1, (int) config('monitoring.application_error_threshold', 3));
        $errors = $this->recentApplicationErrorCount($window);

        if ($errors >= $threshold) {
            $issues['application-errors'] = [
                'subject' => 'Erreurs applicatives répétées détectées',
                'lines' => [
                    "{$errors} erreur(s) ERROR ou CRITICAL durant les {$window} dernières minutes.",
                    'Consultez storage/logs/laravel.log pour le détail.',
                ],
            ];
        } else {
            $alerts->clear('application-errors');
        }
    }

    private function recentApplicationErrorCount(int $minutes): int
    {
        $path = storage_path('logs/laravel.log');

        if (! is_file($path) || ($handle = fopen($path, 'rb')) === false) {
            return 0;
        }

        try {
            $size = filesize($path) ?: 0;
            $offset = max(0, $size - 512_000);

            if ($offset > 0) {
                fseek($handle, $offset);
                fgets($handle);
            }

            $contents = stream_get_contents($handle) ?: '';
        } finally {
            fclose($handle);
        }

        preg_match_all('/^\[(?<timestamp>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*?\.(?:ERROR|CRITICAL):/m', $contents, $matches);

        return collect($matches['timestamp'] ?? [])
            ->filter(fn (string $timestamp): bool => Carbon::parse($timestamp)->gte(now()->subMinutes($minutes)))
            ->count();
    }

    private function safeMessage(Throwable $exception): string
    {
        return str($exception->getMessage())->squish()->limit(300, '…')->toString();
    }
}
