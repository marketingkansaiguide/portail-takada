<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Services\FolderDriveStorage;
use Illuminate\Http\Response;

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

    private function mayAccess(Folder $folder): bool
    {
        $user = auth('agency')->user() ?? auth('web')->user();

        if (! $user) {
            return false;
        }

        if (in_array($user->role ?? null, ['super_admin', 'admin', 'agent'], true)) {
            return true;
        }

        return (int) ($user->agency_id ?? 0) === (int) $folder->agency_id;
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
}
