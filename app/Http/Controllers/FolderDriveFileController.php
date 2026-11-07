<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\User;
use App\Services\FolderDriveStorage;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FolderDriveFileController extends Controller
{
    public function show(Folder $folder, string $fileId, FolderDriveStorage $storage): Response
    {
        abort_unless($this->mayAccess($folder), 403);

        $path = $this->findPath($folder, $fileId);
        abort_unless($path, 404);

        $file = $storage->download($path);

        return response($file['contents'], 200, [
            'Content-Type' => $file['mime_type'],
            'Content-Disposition' => 'attachment; filename="' . addslashes($file['name']) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Télécharge les anciens fichiers locaux, uniquement lorsqu'ils sont bien
     * référencés dans le dossier demandé. Les chemins fournis par l'URL ne sont
     * jamais utilisés directement sans cette vérification.
     */
    public function showLegacy(Folder $folder, Request $request)
    {
        abort_unless($this->mayAccess($folder), 403);

        $path = trim((string) $request->query('path'));
        abort_unless($this->isLegacyPathInFolder($folder, $path), 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->download($path, basename($path), [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function mayAccess(Folder $folder): bool
    {
        $user = auth('agency')->user() ?? auth('web')->user();

        if (! $user) {
            return false;
        }

        // Une seule règle : la policy (qui délègue à FolderAccess pour les agences).
        return $user->can('view', $folder);
    }

    private function findPath(Folder $folder, string $fileId): ?string
    {
        $paths = $folder->documents ?? [];

        foreach ($folder->messages()->whereNotNull('attachment_path')->pluck('attachment_path') as $path) {
            $paths[] = $path;
        }

        foreach ($folder->allFolderItems()->pluck('custom_values') as $values) {
            $this->collectPaths($values, $paths);
        }

        foreach ($paths as $path) {
            if (is_string($path) && str_starts_with($path, 'gdrive://' . $fileId . '/')) {
                return $path;
            }
        }

        return null;
    }

    private function collectPaths(mixed $value, array &$paths): void
    {
        if (is_array($value)) {
            foreach ($value as $entry) {
                $this->collectPaths($entry, $paths);
            }

            return;
        }

        if (is_string($value) && str_starts_with($value, 'gdrive://')) {
            $paths[] = $value;
        }
    }

    private function isLegacyPathInFolder(Folder $folder, string $path): bool
    {
        if (! str_starts_with($path, 'folders/custom_fields/')) {
            return false;
        }

        $paths = [];

        foreach ($folder->allFolderItems()->pluck('custom_values') as $values) {
            $this->collectLegacyPaths($values, $paths);
        }

        return in_array($path, $paths, true);
    }

    private function collectLegacyPaths(mixed $value, array &$paths): void
    {
        if (is_array($value)) {
            foreach ($value as $entry) {
                $this->collectLegacyPaths($entry, $paths);
            }

            return;
        }

        if (! is_string($value)) {
            return;
        }

        preg_match_all('/(folders\/custom_fields\/[^\r\n]+?\.(?:pdf|jpe?g|png|docx?|xlsx?|xls|csv))/i', $value, $matches);

        foreach ($matches[1] ?? [] as $legacyPath) {
            $paths[] = $legacyPath;
        }
    }
}
