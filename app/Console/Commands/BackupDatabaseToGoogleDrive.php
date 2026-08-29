<?php

namespace App\Console\Commands;

use App\Services\FolderDriveStorage;
use App\Services\SystemHealthAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class BackupDatabaseToGoogleDrive extends Command
{
    protected $signature = 'backups:database-to-google-drive
        {--keep=28 : Number of the most recent backups to retain on Google Drive}';

    protected $description = 'Exporte la base MySQL, la compresse et l’envoie vers le Google Drive partagé.';

    public function handle(FolderDriveStorage $driveStorage, SystemHealthAlert $alerts): int
    {
        $connection = config('database.connections.'.config('database.default'));

        if (($connection['driver'] ?? null) !== 'mysql') {
            $this->error('Cette sauvegarde nécessite une connexion MySQL ou MariaDB.');

            return self::FAILURE;
        }

        $database = (string) ($connection['database'] ?? '');
        $username = (string) ($connection['username'] ?? '');

        if ($database === '' || $username === '') {
            $this->error('Les identifiants de la base de données sont incomplets.');

            return self::FAILURE;
        }

        $temporaryDirectory = storage_path('app/technical-backups');
        File::ensureDirectoryExists($temporaryDirectory, 0700, true);

        $fileName = 'portail-takada-db-'.now()->format('Y-m-d_H-i-s').'.sql.gz';
        $temporaryPath = $temporaryDirectory.DIRECTORY_SEPARATOR.$fileName;

        try {
            $this->dumpDatabase($connection, $database, $username, $temporaryPath);

            $fileId = $driveStorage->uploadDatabaseBackup($temporaryPath, $fileName);
            $deleted = $driveStorage->pruneDatabaseBackups(max(1, (int) $this->option('keep')));

            $this->info("Sauvegarde envoyée sur Google Drive : {$fileName} ({$fileId}).");

            if ($deleted > 0) {
                $this->info("Anciennes sauvegardes supprimées : {$deleted}.");
            }

            $alerts->clear('database-backup-missing');
            $alerts->clear('database-backup-stale');
            $alerts->clear('database-backup-unavailable');
            $alerts->clear('database-backup-failed');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('La sauvegarde a échoué : '.$exception->getMessage());
            $alerts->sendOnce('database-backup-failed', 'Échec de sauvegarde de la base de données', [
                'La tâche de sauvegarde vers Google Drive a échoué.',
                'Détail : '.str($exception->getMessage())->squish()->limit(300, '…'),
            ]);

            return self::FAILURE;
        } finally {
            File::delete($temporaryPath);
        }
    }

    /** @param array<string, mixed> $connection */
    private function dumpDatabase(array $connection, string $database, string $username, string $temporaryPath): void
    {
        $gzip = gzopen($temporaryPath, 'wb9');

        if ($gzip === false) {
            throw new RuntimeException('Impossible de créer le fichier temporaire de sauvegarde.');
        }

        $errors = '';
        $process = new Process([
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--default-character-set=utf8mb4',
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? 3306),
            '--user='.$username,
            $database,
        ]);
        $process->setTimeout(900);
        $process->setEnv(['MYSQL_PWD' => (string) ($connection['password'] ?? '')]);

        try {
            $exitCode = $process->run(function (string $type, string $output) use (&$errors, $gzip): void {
                if ($type === Process::OUT) {
                    if (gzwrite($gzip, $output) === false) {
                        throw new RuntimeException('Impossible d’écrire la sauvegarde compressée.');
                    }

                    return;
                }

                $errors .= $output;
            });
        } finally {
            gzclose($gzip);
        }

        if ($exitCode !== 0 || ! is_file($temporaryPath) || filesize($temporaryPath) === 0) {
            throw new RuntimeException('L’export MySQL a échoué'.($errors !== '' ? ' : '.trim($errors) : '.'));
        }
    }
}
