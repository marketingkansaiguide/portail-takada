<?php

namespace App\Filament\Resources\Folders\FolderResource\RelationManagers;

use App\Models\FolderHistory;
use App\Models\FolderItem;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class HistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->columns([
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Dernière activité'))
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (FolderHistory $record): ?string => $record->created_at?->ne($record->updated_at)
                        ? __('Début : :date', ['date' => $record->created_at->format('d/m/Y H:i')])
                        : null)
                    ->sortable()
                    ->width('150px'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('Utilisateur'))
                    ->default(__('Système'))
                    ->description(fn (FolderHistory $record): string => $record->user?->email ?? __('Action automatique'))
                    ->searchable()
                    ->sortable()
                    ->width('190px'),

                Tables\Columns\TextColumn::make('category')
                    ->label(__('Catégorie'))
                    ->state(fn (FolderHistory $record): string => $record->category)
                    ->formatStateUsing(fn (string $state): string => $this->categoryLabel($state))
                    ->badge()
                    ->icon(fn (string $state): string => $this->categoryIcon($state))
                    ->color(fn (string $state): string => $this->categoryColor($state)),

                Tables\Columns\TextColumn::make('action')
                    ->label(__('Action'))
                    ->badge()
                    ->color(fn (FolderHistory $record): string => $this->categoryColor($record->category))
                    ->searchable(),

                Tables\Columns\TextColumn::make('details')
                    ->label(__('Détails des changements'))
                    ->state(fn (FolderHistory $record): HtmlString => $this->formatDetails($record))
                    ->wrap()
                    ->html(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label(__('Catégorie'))
                    ->options([
                        'folder' => __('Dossier'),
                        'service' => __('Prestation'),
                        'passenger' => __('Passager'),
                        'communication' => __('Communication'),
                        'note' => __('Note'),
                        'task' => __('Tâche'),
                        'system' => __('Système'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (!$value) {
                            return $query;
                        }

                        if ($value === 'folder') {
                            return $query->where(function (Builder $query): void {
                                $query->where('changes_payload->category', 'folder')
                                    ->orWhereNull('changes_payload->category');
                            });
                        }

                        return $query->where('changes_payload->category', $value);
                    }),

                Tables\Filters\TernaryFilter::make('system_actions')
                    ->label(__('Actions automatiques'))
                    ->nullable()
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('user_id'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('user_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),

                Tables\Filters\Filter::make('period')
                    ->label(__('Période'))
                    ->form([
                        DatePicker::make('from')->label(__('Du')),
                        DatePicker::make('until')->label(__('Au')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('updated_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('updated_at', '<=', $date))),
            ])
            ->headerActions([
                // Désactivation des actions de création manuelle pour éviter les faux historiques
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->striped();
    }

    private function categoryLabel(string $category): string
    {
        return __([
            'folder' => 'Dossier',
            'service' => 'Prestation',
            'passenger' => 'Passager',
            'communication' => 'Communication',
            'note' => 'Note',
            'task' => 'Tâche',
            'system' => 'Système',
        ][$category] ?? ucfirst($category));
    }

    private function categoryColor(string $category): string
    {
        return match ($category) {
            'service' => 'success',
            'passenger' => 'warning',
            'communication' => 'info',
            'note' => 'gray',
            'task' => 'danger',
            'system' => 'gray',
            default => 'primary',
        };
    }

    private function categoryIcon(string $category): string
    {
        return match ($category) {
            'service' => 'heroicon-m-ticket',
            'passenger' => 'heroicon-m-user',
            'communication' => 'heroicon-m-chat-bubble-left-right',
            'note' => 'heroicon-m-pencil-square',
            'task' => 'heroicon-m-clipboard-document-check',
            'system' => 'heroicon-m-cog-6-tooth',
            default => 'heroicon-m-folder',
        };
    }

    private function formatDetails(FolderHistory $record): HtmlString
    {
        $payload = is_array($record->changes_payload) ? $record->changes_payload : [];
        $summary = trim((string) ($payload['summary'] ?? __('Aucun détail')));
        $entries = collect($payload['entries'] ?? [])
            ->filter(fn ($entry): bool => is_array($entry));
        $changes = $entries
            ->flatMap(function (array $entry) use ($payload): array {
                $context = $this->changeContext(
                    (string) ($entry['summary'] ?? ''),
                    is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [],
                    (string) ($payload['category'] ?? ''),
                    (string) ($entry['action'] ?? ''),
                );

                return collect($entry['changes'] ?? [])
                    ->filter(fn ($change): bool => is_array($change) && isset($change['label']))
                    ->flatMap(fn (array $change): array => $this->makeChangeReadable($change))
                    ->map(fn (array $change): array => [...$change, 'context' => $context])
                    ->all();
            });

        if ($changes->isEmpty()) {
            $context = $this->changeContext(
                $summary,
                is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
                (string) ($payload['category'] ?? ''),
                (string) $record->action,
            );

            $changes = collect($payload['changes'] ?? [])
                ->filter(fn ($change): bool => is_array($change) && isset($change['label']))
                ->flatMap(fn (array $change): array => $this->makeChangeReadable($change))
                ->map(fn (array $change): array => [...$change, 'context' => $context]);
        }

        $changes = $changes
            ->unique(fn (array $change): string => ($change['context'] ?? '') . '|' . ($change['field'] ?? $change['label']) . '|' . ($change['old'] ?? '') . '|' . ($change['new'] ?? ''))
            ->values();
        $notes = $entries
            ->filter(fn ($entry): bool => is_array($entry) && empty($entry['changes']) && filled($entry['summary'] ?? null))
            ->pluck('summary')
            ->unique()
            ->values();

        if ($changes->isEmpty() && $notes->isEmpty()) {
            [$summary, $legacyChanges] = $this->extractLegacyTransportChanges($summary);

            if ($legacyChanges !== []) {
                $changes = collect($legacyChanges);
                $notes = filled($summary) ? collect([$summary]) : collect();
            }
        }

        if ($changes->isNotEmpty()) {
            $notesHtml = $notes->isEmpty()
                ? ''
                : '<div style="margin-bottom: 0.65rem; color: #4b5563;">' . $notes
                    ->map(fn (string $note): string => '<div>' . nl2br(e($note)) . '</div>')
                    ->implode('') . '</div>';
            $rows = $changes->groupBy(fn (array $change): string => (string) ($change['context'] ?? ''))
                ->map(function ($group, string $context): string {
                    $heading = $context === ''
                        ? ''
                        : '<div style="margin: 0.65rem 0 0.45rem; font-size: 0.82rem; font-weight: 700; color: var(--tk-accent);">' . e($context) . '</div>';

                    $changesHtml = $group->map(function (array $change): string {
                        $label = e((string) $change['label']);
                        $old = e((string) ($change['old'] ?? __('Non renseigné')));
                        $new = e((string) ($change['new'] ?? __('Non renseigné')));

                        return <<<HTML
                            <div style="margin-bottom: 0.45rem;">
                                <div style="font-weight: 600; color: #374151;">{$label}</div>
                                <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; font-size: 0.82rem;">
                                    <span style="background: #f3f4f6; color: #6b7280; padding: 0.15rem 0.4rem; border-radius: 0.3rem; text-decoration: line-through;">{$old}</span>
                                    <span style="color: #9ca3af;">→</span>
                                    <span style="background: #ecfdf5; color: #047857; padding: 0.15rem 0.4rem; border-radius: 0.3rem; font-weight: 600;">{$new}</span>
                                </div>
                            </div>
                        HTML;
                    })->implode('');

                    return '<div style="margin-bottom: 0.7rem;">' . $heading . $changesHtml . '</div>';
                })
                ->implode('');

            return new HtmlString('<div style="min-width: 320px;">' . $notesHtml . $rows . '</div>');
        }

        $summary = $summary ?: __('Aucun détail');
        $summary = preg_replace('/\n\s*---\s*\n/u', "\n", $summary) ?? $summary;

        return new HtmlString('<div style="white-space: normal; line-height: 1.55; min-width: 320px;">' . nl2br(e($summary)) . '</div>');
    }

    private function changeContext(string $summary, array $metadata, string $category, string $action): ?string
    {
        if ($category !== 'service' && ! str_contains(mb_strtolower($action), 'prestation')) {
            return null;
        }

        $service = trim((string) ($metadata['service_label'] ?? ''));

        if ($service === '' && preg_match("/^(?:La prestation|Prestation)\\s+['«\"]?(.+?)['»\"]?\\s+a été\\b/mu", $summary, $matches)) {
            $service = trim($matches[1]);
        }

        return $service === '' ? __('Prestation concernée') : __('Prestation : :name', ['name' => $service]);
    }

    /** Convert legacy JSON transport changes when the history is displayed. */
    private function makeChangeReadable(array $change): array
    {
        if (($change['field'] ?? null) !== 'custom_values.transport_routes') {
            return [$change];
        }

        $readable = FolderItem::formatTransportRouteHistoryChanges(
            $change['old'] ?? null,
            $change['new'] ?? null,
        );

        return $readable !== [] ? $readable : [$change];
    }

    /**
     * Older records only stored an HTML-unfriendly sentence in `summary`.
     * Extract transport JSON from that sentence so existing history benefits too.
     */
    private function extractLegacyTransportChanges(string $summary): array
    {
        $changes = [];
        $pattern = "/• Information 'transport_routes' : '(\\[.*?\\])' ➔ '(\\[.*?\\])'/u";
        $remaining = preg_replace_callback(
            $pattern,
            function (array $match) use (&$changes): string {
                $changes = array_merge(
                    $changes,
                    FolderItem::formatTransportRouteHistoryChanges($match[1], $match[2]),
                );

                return '';
            },
            $summary,
        );

        // `_age_selector` is an internal pricing helper. Actual adult/child values
        // are logged separately and are the only useful information for an admin.
        $remaining = preg_replace("/^\\s*• Information '_age_selector' : .*$/mu", '', (string) $remaining);

        // A converted transport block can leave its former service heading behind.
        // Keep the heading only when it still introduces a non-technical detail.
        $remaining = collect(preg_split('/\n\s*---\s*\n/u', (string) $remaining) ?: [])
            ->map(function (string $block): string {
                $block = trim($block);
                $withoutHeading = preg_replace('/^La prestation .+? a été modifiée\s*:\s*$/mu', '', $block) ?? $block;

                return str_contains($withoutHeading, '•') ? $block : trim($withoutHeading);
            })
            ->filter(fn (string $block): bool => filled($block))
            ->implode("\n\n");

        return [trim((string) $remaining), $changes];
    }
}
