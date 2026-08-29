<?php

namespace App\Services;

use App\Models\Folder;
use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class FolderDriveStorage
{
    private const PATH_PREFIX = 'gdrive://';

    private const TECHNICAL_BACKUPS_FOLDER = 'Sauvegardes portail Takada';

    /**
     * Upload a file into the dedicated folder of a client dossier.
     */
    public function upload(Folder $folder, UploadedFile $file, string $section): string
    {
        return $this->uploadFromPath(
            $folder,
            $file->getRealPath(),
            $file->getClientOriginalName(),
            $file->getMimeType() ?: 'application/octet-stream',
            $section,
        );
    }

    /**
     * Move an existing Laravel-stored file into the dossier's Drive folder.
     */
    public function uploadExistingFile(Folder $folder, string $absolutePath, string $originalName, string $section): string
    {
        return $this->uploadFromPath(
            $folder,
            $absolutePath,
            $originalName,
            mime_content_type($absolutePath) ?: 'application/octet-stream',
            $section,
        );
    }

    private function uploadFromPath(Folder $folder, string $absolutePath, string $originalName, string $mimeType, string $section): string
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException(__('Le fichier local à transférer est introuvable.'));
        }

        $parentId = $this->sectionId($folder, $section);
        $name = $this->safeFileName($originalName);

        $metadata = new DriveFile([
            'name' => $name,
            'parents' => [$parentId],
        ]);

        $created = $this->drive()->files->create($metadata, [
            'data' => file_get_contents($absolutePath),
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'supportsAllDrives' => true,
            'fields' => 'id,name',
        ]);

        return self::PATH_PREFIX . $created->getId() . '/' . rawurlencode($created->getName() ?: $name);
    }

    public function isDrivePath(?string $path): bool
    {
        return is_string($path) && str_starts_with($path, self::PATH_PREFIX);
    }

    public function fileId(string $path): ?string
    {
        if (! $this->isDrivePath($path)) {
            return null;
        }

        $id = Str::before(Str::after($path, self::PATH_PREFIX), '/');

        return $id !== '' ? $id : null;
    }

    public function fileName(string $path): string
    {
        if (! $this->isDrivePath($path)) {
            return basename($path);
        }

        $name = Str::after(Str::after($path, self::PATH_PREFIX), '/');

        return $name !== '' ? rawurldecode($name) : __('Fichier joint');
    }

    public function url(Folder $folder, string $path): string
    {
        if (! $this->isDrivePath($path)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
        }

        return route('folders.drive-file', [
            'folder' => $folder,
            'fileId' => $this->fileId($path),
        ]);
    }

    /** @return array{name: string, size: int, type: null, url: string}|null */
    public function uploadedFileInfo(Folder $folder, string $path, string|array|null $storedFileNames = null): ?array
    {
        if (! $this->isDrivePath($path)) {
            return null;
        }

        return [
            'name' => is_array($storedFileNames) ? ($storedFileNames[$path] ?? $this->fileName($path)) : ($storedFileNames ?: $this->fileName($path)),
            'size' => 0,
            'type' => null,
            'url' => $this->url($folder, $path),
        ];
    }

    /** @return array{name: string, mime_type: string, contents: string} */
    public function download(string $path): array
    {
        $fileId = $this->fileId($path);

        if (! $fileId) {
            throw new RuntimeException(__('Identifiant Google Drive invalide.'));
        }

        $metadata = $this->drive()->files->get($fileId, [
            'supportsAllDrives' => true,
            'fields' => 'name,mimeType',
        ]);
        $response = $this->drive()->files->get($fileId, [
            'alt' => 'media',
            'supportsAllDrives' => true,
        ]);

        return [
            'name' => $metadata->getName() ?: $this->fileName($path),
            'mime_type' => $metadata->getMimeType() ?: 'application/octet-stream',
            'contents' => (string) $response->getBody(),
        ];
    }

    public function delete(string $path): void
    {
        if ($fileId = $this->fileId($path)) {
            $this->drive()->files->delete($fileId, ['supportsAllDrives' => true]);
        }
    }

    /**
     * Upload a technical database backup outside of client dossier folders.
     */
    public function uploadDatabaseBackup(string $absolutePath, string $fileName): string
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException('Le fichier de sauvegarde local est introuvable.');
        }

        $name = $this->safeFileName($fileName);
        $created = $this->drive()->files->create(new DriveFile([
            'name' => $name,
            'parents' => [$this->technicalBackupsFolderId()],
        ]), [
            'data' => file_get_contents($absolutePath),
            'mimeType' => 'application/gzip',
            'uploadType' => 'multipart',
            'supportsAllDrives' => true,
            'fields' => 'id,name',
        ]);

        return (string) $created->getId();
    }

    /**
     * Keep the newest technical backups and delete the older ones from Drive.
     */
    public function pruneDatabaseBackups(int $keep): int
    {
        $folderId = $this->technicalBackupsFolderId();
        $result = $this->drive()->files->listFiles([
            'q' => sprintf("'%s' in parents and trashed = false", $folderId),
            'corpora' => 'drive',
            'driveId' => $this->sharedDriveId(),
            'includeItemsFromAllDrives' => true,
            'supportsAllDrives' => true,
            'spaces' => 'drive',
            'orderBy' => 'createdTime desc',
            'fields' => 'files(id)',
            'pageSize' => 1000,
        ]);

        $deleted = 0;

        foreach (array_slice($result->getFiles(), max(1, $keep)) as $file) {
            $this->drive()->files->delete($file->getId(), ['supportsAllDrives' => true]);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Return the newest database backup stored in the shared Drive.
     *
     * @return array{id: string, name: string, created_at: string}|null
     */
    public function latestDatabaseBackup(): ?array
    {
        $result = $this->drive()->files->listFiles([
            'q' => sprintf("'%s' in parents and trashed = false", $this->technicalBackupsFolderId()),
            'corpora' => 'drive',
            'driveId' => $this->sharedDriveId(),
            'includeItemsFromAllDrives' => true,
            'supportsAllDrives' => true,
            'spaces' => 'drive',
            'orderBy' => 'createdTime desc',
            'fields' => 'files(id,name,createdTime)',
            'pageSize' => 1,
        ]);

        $file = $result->getFiles()[0] ?? null;

        if ($file === null || $file->getCreatedTime() === null) {
            return null;
        }

        return [
            'id' => (string) $file->getId(),
            'name' => (string) $file->getName(),
            'created_at' => (string) $file->getCreatedTime(),
        ];
    }

    private function sectionId(Folder $folder, string $section): string
    {
        $folderId = $this->folderId($folder);
        $sectionName = match ($section) {
            'chat' => 'Messagerie',
            'services' => 'Prestations',
            default => 'Documents',
        };

        return $this->findOrCreateFolder($sectionName, $folderId);
    }

    private function technicalBackupsFolderId(): string
    {
        return $this->findOrCreateFolder(self::TECHNICAL_BACKUPS_FOLDER, $this->sharedDriveId());
    }

    private function folderId(Folder $folder): string
    {
        $expectedName = $this->folderName($folder);

        if ($folder->google_drive_folder_id) {
            $this->drive()->files->update($folder->google_drive_folder_id, new DriveFile(['name' => $expectedName]), [
                'supportsAllDrives' => true,
                'fields' => 'id',
            ]);

            return $folder->google_drive_folder_id;
        }

        $id = $this->findOrCreateFolder($expectedName, $this->sharedDriveId());
        $folder->forceFill(['google_drive_folder_id' => $id])->saveQuietly();

        return $id;
    }

    private function findOrCreateFolder(string $name, string $parentId): string
    {
        $escapedName = str_replace("'", "\\'", $name);
        $result = $this->drive()->files->listFiles([
            'q' => sprintf("name = '%s' and '%s' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false", $escapedName, $parentId),
            'corpora' => 'drive',
            'driveId' => $this->sharedDriveId(),
            'includeItemsFromAllDrives' => true,
            'supportsAllDrives' => true,
            'spaces' => 'drive',
            'fields' => 'files(id)',
            'pageSize' => 1,
        ]);

        if ($existing = $result->getFiles()[0] ?? null) {
            return $existing->getId();
        }

        $created = $this->drive()->files->create(new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId],
        ]), [
            'supportsAllDrives' => true,
            'fields' => 'id',
        ]);

        return $created->getId();
    }

    private function drive(): Drive
    {
        $credentials = (string) config('services.google_drive.service_account_json');
        $credentials = $credentials !== '' && ! str_starts_with($credentials, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:\\\\/', $credentials)
            ? base_path($credentials)
            : $credentials;

        if ($credentials === '' || ! is_file($credentials)) {
            throw new RuntimeException(__('Google Drive n’est pas configuré. Ajoutez le fichier de compte de service indiqué dans la configuration.'));
        }

        $client = new GoogleClient();
        $client->setAuthConfig($credentials);
        $client->setScopes([Drive::DRIVE]);

        return new Drive($client);
    }

    private function sharedDriveId(): string
    {
        $id = (string) config('services.google_drive.shared_drive_id');

        if ($id === '') {
            throw new RuntimeException(__('L’identifiant du Drive partagé Google est manquant.'));
        }

        return $id;
    }

    private function folderName(Folder $folder): string
    {
        $date = $folder->start_date?->format('Y-m-d') ?? $folder->created_at?->format('Y-m-d') ?? now()->format('Y-m-d');
        $name = trim((string) ($folder->folder_name ?: $folder->reference));
        $name = preg_replace('/[\\\\\\/:*?"<>|]+/', '-', $name) ?: $folder->reference;

        return trim($date . ' - ' . $name);
    }

    private function safeFileName(string $name): string
    {
        $name = trim(preg_replace('/[\\\\\\/:*?"<>|]+/', '-', $name) ?? '');

        return $name !== '' ? $name : 'fichier-' . Str::uuid();
    }
}
