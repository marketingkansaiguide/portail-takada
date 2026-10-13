<?php

namespace App\Models;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class FolderHistory extends Model
{
    protected $guarded = [];
    protected $casts = ['changes_payload' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
    public function folder() { return $this->belongsTo(Folder::class); }

    public function getCategoryAttribute(): string
    {
        return (string) ($this->changes_payload['category'] ?? 'folder');
    }

    public function getSummaryAttribute(): string
    {
        return (string) ($this->changes_payload['summary'] ?? '');
    }

    /**
     * Resolve the actor across both Filament panels. System jobs remain nullable
     * instead of being incorrectly attributed to user #1.
     */
    public static function resolveActorId(): ?int
    {
        try {
            if ($panel = Filament::getCurrentPanel()) {
                $id = auth($panel->getAuthGuard())->id();

                if ($id) {
                    return (int) $id;
                }
            }
        } catch (\Throwable) {
            // Filament is not booted (queue, command, observer, etc.).
        }

        foreach (['web', 'agency'] as $guard) {
            try {
                if ($id = auth($guard)->id()) {
                    return (int) $id;
                }
            } catch (\Throwable) {
                // Guard unavailable in this execution context.
            }
        }

        return auth()->id() ? (int) auth()->id() : null;
    }

    /**
     * 💡 MOTEUR DE CONSOLIDATION 💡
     * Regroupe TOUTES les actions d'un même dossier et même utilisateur 
     * dans un intervalle court pour un seul rendu visuel.
     */
    public static function logConsolidated(
        $folderId,
        $action,
        $summary,
        string $category = 'folder',
        array $changes = [],
        array $metadata = [],
    ): ?self
    {
        if (!$folderId || trim((string) $summary) === '') {
            return null;
        }

        $userId = self::resolveActorId();
        $query = self::query()
            ->where('folder_id', $folderId)
            ->where('created_at', '>=', Carbon::now()->subSeconds(12));

        $userId === null
            ? $query->whereNull('user_id')
            : $query->where('user_id', $userId);

        $recent = $query->latest('id')->get()->first(function (self $history) use ($category): bool {
            return ($history->changes_payload['category'] ?? 'folder') === $category;
        });

        $entry = [
            'action' => (string) $action,
            'summary' => trim((string) $summary),
            'changes' => array_values($changes),
            'metadata' => $metadata,
            'recorded_at' => now()->toIso8601String(),
        ];

        $fingerprint = sha1(json_encode([
            $category,
            $entry['action'],
            $entry['summary'],
            $entry['changes'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $entry['fingerprint'] = $fingerprint;

        if ($recent) {
            $payload = is_array($recent->changes_payload) ? $recent->changes_payload : [];
            $entries = is_array($payload['entries'] ?? null) ? $payload['entries'] : [];

            if ($entries === [] && !empty($payload['summary'])) {
                $entries[] = [
                    'action' => $recent->action,
                    'summary' => (string) $payload['summary'],
                    'changes' => [],
                    'recorded_at' => optional($recent->created_at)->toIso8601String(),
                    'fingerprint' => sha1((string) $payload['summary']),
                ];
            }

            if (collect($entries)->contains(fn (array $item): bool => ($item['fingerprint'] ?? null) === $fingerprint)) {
                return $recent;
            }

            $entries[] = $entry;
            $payload = array_merge($payload, [
                'category' => $category,
                'summary' => collect($entries)->pluck('summary')->filter()->implode("\n\n"),
                'entries' => $entries,
                'changes' => collect($entries)->flatMap(fn (array $item) => $item['changes'] ?? [])->values()->all(),
                'metadata' => array_merge(Arr::wrap($payload['metadata'] ?? []), $metadata),
            ]);

            $recent->update([
                'action' => count($entries) > 1 ? __('Mise à jour groupée') : $action,
                'changes_payload' => $payload,
                'updated_at' => now(),
            ]);

            self::notifyAgency($recent->folder, (string) $action, (string) $summary, $category, $changes, $metadata, $userId);

            return $recent;
        }

        $history = self::create([
            'folder_id' => $folderId,
            'user_id' => $userId,
            'action' => $action,
            'changes_payload' => [
                'category' => $category,
                'summary' => $entry['summary'],
                'entries' => [$entry],
                'changes' => $entry['changes'],
                'metadata' => $metadata,
            ],
        ]);

        self::notifyAgency($history->folder, (string) $action, (string) $summary, $category, $changes, $metadata, $userId);

        return $history;
    }

    /**
     * Envoie aux membres de l'agence uniquement les changements réellement
     * effectués par un membre de l'équipe Takada. Les actions de l'agence,
     * les tâches automatiques et les exports techniques restent internes.
     */
    private static function notifyAgency(?Folder $folder, string $action, string $summary, string $category, array $changes, array $metadata, ?int $actorId): void
    {
        if (! $folder?->agency_id) {
            return;
        }

        $actor = $actorId ? User::find($actorId) : null;
        if (! $actor || ! $actor->isStaff()) {
            return;
        }

        // L'impression ou l'export d'étiquettes est un traitement interne :
        // il ne doit jamais encombrer le centre de notifications agence.
        $changedFields = collect($changes)->pluck('field')->filter()->all();
        if (in_array('label_exported_at', $changedFields, true)) {
            return;
        }

        $recipients = User::query()
            ->where('agency_id', $folder->agency_id)
            ->where('role', User::ROLE_AGENCY)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $content = self::agencyNotificationContent($folder, $action, $summary, $category, $changes, $metadata);

        $notification = Notification::make()
            ->title($content['title'])
            ->body($content['body'])
            ->actions([
                Action::make('openFolder')
                    ->label(__('Voir le dossier'))
                    ->button()
                    ->url(route('filament.agency.resources.agency-folders.edit', ['record' => $folder]))
                    ->markAsRead(),
            ]);

        match ($content['type']) {
            'message' => $notification->info()->icon('heroicon-o-chat-bubble-left-right'),
            'service' => $notification->success()->icon('heroicon-o-ticket'),
            'document' => $notification->info()->icon('heroicon-o-document-arrow-up'),
            'task' => $notification->warning()->icon('heroicon-o-clipboard-document-check'),
            default => $notification->info()->icon('heroicon-o-folder-open'),
        };

        // Filament envoie normalement les notifications en file d'attente.
        // Le portail local ne dispose pas d'un worker permanent : on les écrit
        // donc directement pour que la cloche agence soit immédiatement fiable.
        $message = $notification->getDatabaseMessage();

        $recipients->each(fn (User $recipient) => $recipient->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => \Filament\Notifications\DatabaseNotification::class,
            'data' => $message,
        ]));
    }

    private static function agencyNotificationContent(Folder $folder, string $action, string $summary, string $category, array $changes, array $metadata): array
    {
        // Le centre agence ne doit jamais exposer la référence technique du
        // dossier. Un dossier sans nom reste explicitement identifiable comme
        // tel, sans retomber sur sa référence interne.
        $folderName = filled($folder->folder_name)
            ? $folder->folder_name
            : __('Dossier sans nom');
        $changedFields = collect($changes)->pluck('field')->filter()->all();

        if (in_array('documents', $changedFields, true)) {
            return [
                'type' => 'document',
                'title' => __('Nouveaux documents dans le dossier'),
                'body' => __('De nouveaux documents ont été ajoutés au dossier :folder.', ['folder' => $folderName]),
            ];
        }

        if ($category === 'communication' && $action === __('Nouveau message')) {
            return [
                'type' => 'message',
                'title' => __('Nouveau message de l’équipe Takada'),
                'body' => __('Un nouveau message vous attend dans le dossier :folder.', ['folder' => $folderName]),
            ];
        }

        if ($category === 'service' && in_array('item_status_id', $changedFields, true)) {
            return [
                'type' => 'service',
                'title' => __('Prestation mise à jour'),
                'body' => __('Cette prestation a été mise à jour. Vous pouvez vérifier les détails dans le dossier.'),
            ];
        }

        return [
            'type' => $category === 'task' ? 'task' : 'default',
            'title' => __('Mise à jour — dossier :folder', ['folder' => $folderName]),
            'body' => str($action . ' — ' . $summary)->squish()->limit(220)->toString(),
        ];
    }
}
