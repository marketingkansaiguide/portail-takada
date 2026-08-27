<?php

namespace App\Console\Commands;

use App\Models\Folder;
use App\Models\FolderMessage;
use App\Services\FolderDriveStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateFolderFilesToGoogleDrive extends Command
{
    protected $signature = 'folders:migrate-files-to-google-drive {--execute : Transfère réellement les fichiers}';

    protected $description = 'Transfère les anciens fichiers des dossiers vers leur dossier Google Drive dédié.';

    private int $candidates = 0;
    private int $migrated = 0;
    private int $missing = 0;
    private array $filesToDelete = [];

    public function handle(FolderDriveStorage $driveStorage): int
    {
        $execute = (bool) $this->option('execute');
        $this->info($execute ? 'Migration vers Google Drive en cours…' : 'Aperçu : aucune donnée ne sera modifiée.');

        Folder::query()->with(['messages', 'allFolderItems'])->orderBy('id')->each(function (Folder $folder) use ($driveStorage, $execute): void {
            $documents = $this->transform($folder, $folder->documents ?? [], 'documents', $driveStorage, $execute);
            if ($execute && $documents !== ($folder->documents ?? [])) {
                DB::table('folders')->where('id', $folder->id)->update(['documents' => json_encode($documents)]);
            }

            foreach ($folder->messages as $message) {
                $path = $this->migratePath($folder, $message->attachment_path, 'chat', $driveStorage, $execute);
                if ($execute && $path !== $message->attachment_path) {
                    DB::table('folder_messages')->where('id', $message->id)->update(['attachment_path' => $path]);
                }
            }

            foreach ($folder->allFolderItems as $item) {
                $values = $this->transform($folder, $item->custom_values ?? [], 'services', $driveStorage, $execute);
                if ($execute && $values !== ($item->custom_values ?? [])) {
                    DB::table('folder_items')->where('id', $item->id)->update(['custom_values' => json_encode($values)]);
                }
            }
        });

        if ($execute) {
            foreach ($this->filesToDelete as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
        }

        $this->newLine();
        $this->table(['Élément', 'Nombre'], [
            ['Fichiers candidats', $this->candidates],
            ['Fichiers transférés', $this->migrated],
            ['Fichiers locaux introuvables', $this->missing],
            ['Copies locales supprimées', $execute ? count($this->filesToDelete) : 0],
        ]);

        return self::SUCCESS;
    }

    private function transform(Folder $folder, mixed $value, string $section, FolderDriveStorage $driveStorage, bool $execute): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $entry) {
                $value[$key] = $this->transform($folder, $entry, $section, $driveStorage, $execute);
            }

            return $value;
        }

        return is_string($value)
            ? $this->migratePath($folder, $value, $section, $driveStorage, $execute)
            : $value;
    }

    private function migratePath(Folder $folder, ?string $path, string $section, FolderDriveStorage $driveStorage, bool $execute): ?string
    {
        if (! $path || $driveStorage->isDrivePath($path) || ! $this->isAttachmentPath($path, $section)) {
            return $path;
        }

        $this->candidates++;
        $source = $this->findLocalFile($path);
        if (! $source) {
            $this->missing++;
            $this->warn("Introuvable : {$path} (dossier #{$folder->id})");

            return $path;
        }

        if (! $execute) {
            return $path;
        }

        try {
            $newPath = $driveStorage->uploadExistingFile($folder, $source['absolute_path'], basename($path), $section);
            $this->migrated++;
            $this->filesToDelete[$source['disk'] . '|' . $path] = [$source['disk'], $path];

            return $newPath;
        } catch (Throwable $exception) {
            $this->error("Échec du transfert de {$path} : {$exception->getMessage()}");

            return $path;
        }
    }

    private function isAttachmentPath(string $path, string $section): bool
    {
        return match ($section) {
            'documents' => str_starts_with($path, 'folders/documents/'),
            'chat' => str_starts_with($path, 'chat-attachments/'),
            'services' => str_starts_with($path, 'folders/custom_fields/'),
            default => false,
        };
    }

    /** @return array{disk: string, absolute_path: string}|null */
    private function findLocalFile(string $path): ?array
    {
        foreach (['public', 'local'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return ['disk' => $disk, 'absolute_path' => Storage::disk($disk)->path($path)];
            }
        }

        return null;
    }
}
