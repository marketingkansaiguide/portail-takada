<?php

namespace App\Services;

use App\Models\Folder;
use App\Models\FolderHistory;
use App\Models\FolderItem;
use App\Models\FolderOrderBatch;
use App\Models\FolderPlannedItem;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\User;
use App\Support\FolderAccess;
use App\Support\ItemPricer;
use App\Support\PlannedItemMapper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Commande explicite des prestations préparées d'un dossier interne (lot 3b).
 *
 * - Récapitulatif : contrôles et recalcul des prix (même moteur que la saisie).
 * - Commande : tout ou rien, dans une transaction, avec revalidation complète,
 *   clé d'idempotence liée à la sélection, verrous de ligne et contrôle de version.
 * - Les effets externes (missions guides, Calendar) partent après validation en base.
 */
final class PlannedItemOrderService
{
    /** @var array<int, true> prestations préparées en cours de conversion (lu par FolderItem::creating) */
    private static array $converting = [];

    public static function isConverting(int $plannedItemId): bool
    {
        return isset(self::$converting[$plannedItemId]);
    }

    public static function selectionHash(Folder $folder, array $ids, User $user): string
    {
        $ids = array_map('intval', $ids);
        sort($ids);

        return hash('sha256', $folder->id . '|' . implode(',', $ids) . '|' . $user->id);
    }

    /** Récapitulatif présenté avant confirmation. */
    public function summary(Folder $folder, array $ids, User $user): array
    {
        $this->authorize($folder, $user);
        $items = $this->loadSelection($folder, $ids, lock: false);
        $lines = $items->map(fn (FolderPlannedItem $item) => $this->checkLine($folder, $item))->values()->all();

        return [
            'folder_problems' => $this->folderProblems($folder),
            'lines' => $lines,
            'idempotency_key' => (string) Str::uuid(),
            'lock_version' => (int) $folder->lock_version,
            'has_price_changes' => collect($lines)->contains(fn ($l) => $l['price_changed']),
            'has_blocking' => collect($lines)->contains(fn ($l) => $l['problems'] !== []),
        ];
    }

    /**
     * @return array{status: string, batch_id?: int, message: string, lines?: array}
     */
    public function order(Folder $folder, array $ids, User $user, int $expectedLockVersion, string $idempotencyKey, bool $acceptNewPrices): array
    {
        $this->authorize($folder, $user);
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return ['status' => 'rejected', 'message' => __('Aucune prestation sélectionnée.')];
        }
        $hash = self::selectionHash($folder, $ids, $user);

        // Rejeu (double clic, nouvel envoi) : on renvoie le résultat déjà enregistré.
        if ($replay = $this->replay($idempotencyKey, $hash)) {
            return $replay;
        }

        try {
            $result = DB::transaction(function () use ($folder, $ids, $user, $expectedLockVersion, $idempotencyKey, $acceptNewPrices, $hash) {
                $locked = Folder::query()->whereKey($folder->id)->lockForUpdate()->firstOrFail();

                if ((int) $locked->lock_version !== $expectedLockVersion) {
                    throw new OrderRejected(__('Le dossier a été modifié entre-temps : rechargez la page et vérifiez la sélection.'));
                }
                if ($problems = $this->folderProblems($locked)) {
                    throw new OrderRejected(implode(' ', $problems));
                }

                $items = $this->loadSelection($locked, $ids, lock: true);
                if ($items->count() !== count($ids)) {
                    throw new OrderRejected(__('Une ou plusieurs prestations sélectionnées n’appartiennent pas à ce dossier.'));
                }
                $notDraft = $items->filter(fn ($i) => $i->state !== FolderPlannedItem::STATE_DRAFT);
                if ($notDraft->isNotEmpty()) {
                    throw new OrderRejected(__('Déjà commandée ou retirée : :list. Aucune prestation n’a été commandée.', ['list' => $notDraft->map->label()->implode(', ')]));
                }

                // Revalidation complète au moment de la confirmation.
                $lines = $items->map(fn (FolderPlannedItem $item) => $this->checkLine($locked, $item))->values();
                $blocking = $lines->filter(fn ($l) => $l['problems'] !== []);
                if ($blocking->isNotEmpty()) {
                    throw new OrderRejected(__('Commande impossible : :list', ['list' => $blocking->map(fn ($l) => $l['label'] . ' (' . implode(', ', $l['problems']) . ')')->implode(' ; ')]));
                }
                if (! $acceptNewPrices && $lines->contains(fn ($l) => $l['price_changed'])) {
                    throw new OrderRejected(__('Un tarif a changé depuis la préparation : vérifiez le récapitulatif et acceptez les nouveaux tarifs.'));
                }

                $batch = FolderOrderBatch::create([
                    'folder_id' => $locked->id,
                    'user_id' => $user->id,
                    'idempotency_key' => $idempotencyKey,
                    'selection_hash' => $hash,
                    'planned_item_ids' => $ids,
                    'status' => 'completed',
                    'price_changes_acknowledged' => $acceptNewPrices && $lines->contains(fn ($l) => $l['price_changed']),
                ]);

                // Le statut technique « confirmé » est posé avant la création des lignes :
                // les automatismes existants (missions guides) en dépendent.
                if ($locked->status === 'draft') {
                    $locked->status = 'confirmed';
                }
                if (in_array($locked->ops_status, ['brouillon', 'en_preparation'], true)) {
                    $locked->ops_status = 'reservations_en_cours';
                }
                if ($locked->isDirty()) {
                    $locked->save();
                }

                $snapshot = [];
                $resultLines = [];
                foreach ($items as $planned) {
                    $line = $lines->firstWhere('planned_item_id', $planned->id);
                    $attributes = PlannedItemMapper::toFolderItemAttributes($planned);
                    if ($line['price_changed']) {
                        foreach (['unit_price', 'total_price', 'purchase_unit_price', 'purchase_total_price'] as $k) {
                            if ($line['current'][$k] !== null && ! self::isOverridden($planned, $k)) {
                                $attributes[$k] = (int) round($line['current'][$k]);
                            }
                        }
                    }
                    $attributes['ordered_by_user_id'] = $user->id;
                    $attributes['order_batch_id'] = $batch->id;

                    self::$converting[$planned->id] = true;
                    try {
                        $item = FolderItem::create($attributes);
                    } finally {
                        unset(self::$converting[$planned->id]);
                    }

                    $planned->allowConvertedWrite = true;
                    $planned->forceFill([
                        'state' => FolderPlannedItem::STATE_CONVERTED,
                        'folder_item_id' => $item->id,
                        'converted_at' => now(),
                        'converted_by_user_id' => $user->id,
                        'order_batch_id' => $batch->id,
                    ])->saveQuietly();

                    // Instantané sans données personnelles (ni noms, ni champs personnalisés).
                    $snapshot[] = [
                        'planned_item_id' => $planned->id,
                        'folder_item_id' => $item->id,
                        'product_id' => $planned->product_id,
                        'option_ids' => collect($planned->selected_options ?? [])->pluck('product_option_id')->filter()->values()->all(),
                        'service_date' => $planned->service_date?->format('Y-m-d'),
                        'pax' => ['adults' => $planned->pax_adults, 'children' => $planned->pax_children, 'teenagers' => $planned->pax_teenagers, 'total' => $planned->quantity],
                        'prepared' => $line['prepared'],
                        'retained' => ['unit_price' => $item->unit_price, 'total_price' => $item->total_price, 'purchase_unit_price' => $item->purchase_unit_price, 'purchase_total_price' => $item->purchase_total_price],
                        'price_changed' => $line['price_changed'],
                    ];
                    $resultLines[] = ['planned_item_id' => $planned->id, 'folder_item_id' => $item->id, 'label' => $planned->label(), 'status' => 'ordered'];
                }

                $batch->forceFill(['snapshot' => $snapshot, 'result' => $resultLines])->save();
                Folder::query()->whereKey($locked->id)->increment('lock_version');

                FolderHistory::logConsolidated(
                    $locked->id,
                    __('Commande de prestations'),
                    __(':user a commandé :count prestation(s) : :list', ['user' => $user->name, 'count' => count($resultLines), 'list' => collect($resultLines)->pluck('label')->implode(', ')]),
                    'service',
                    [],
                    ['event' => 'order_batch', 'order_batch_id' => $batch->id],
                );

                return ['status' => 'completed', 'batch_id' => $batch->id, 'message' => __(':count prestation(s) commandée(s).', ['count' => count($resultLines)]), 'lines' => $resultLines];
            });
        } catch (OrderRejected $rejected) {
            return ['status' => 'rejected', 'message' => $rejected->getMessage()];
        } catch (QueryException $exception) {
            // Deux envois simultanés avec la même clé : l'index unique a tranché, on relit.
            if ($replay = $this->replay($idempotencyKey, $hash)) {
                return $replay;
            }
            throw $exception;
        }

        return $result;
    }

    /** Problèmes bloquants au niveau du dossier. */
    public function folderProblems(Folder $folder): array
    {
        $problems = [];
        if (! $folder->isInternal()) {
            $problems[] = __('La commande depuis la préparation est réservée aux dossiers internes.');
        }
        if (in_array($folder->ops_status, ['annule', 'termine'], true) || in_array($folder->status, ['cancelled', 'completed'], true)) {
            $problems[] = __('Dossier annulé ou terminé : rouvrez-le avant de commander.');
        }
        if ($folder->hasUndeterminedPricingPolicy()) {
            $problems[] = __('Type de vente et politique tarifaire à définir avant de commander.');
        }

        return $problems;
    }

    private function authorize(Folder $folder, User $user): void
    {
        if (! $folder->isInternal() || ! $user->can('update', $folder) || ! FolderAccess::staffMay($user, 'order')) {
            throw new \Illuminate\Auth\Access\AuthorizationException(__('Vous n’avez pas le droit de commander sur ce dossier.'));
        }
    }

    /** @return Collection<int, FolderPlannedItem> */
    private function loadSelection(Folder $folder, array $ids, bool $lock): Collection
    {
        $query = FolderPlannedItem::query()->with('product')->where('folder_id', $folder->id)->whereIn('id', $ids)->orderBy('id');

        return ($lock ? $query->lockForUpdate() : $query)->get();
    }

    private function replay(string $key, string $hash): ?array
    {
        $batch = FolderOrderBatch::where('idempotency_key', $key)->first();
        if (! $batch) {
            return null;
        }
        if ($batch->selection_hash !== $hash) {
            return ['status' => 'rejected', 'message' => __('Cette opération de commande a déjà été utilisée pour une autre sélection.')];
        }

        return ['status' => 'completed', 'batch_id' => $batch->id, 'replayed' => true, 'message' => __('Commande déjà enregistrée (aucun doublon créé).'), 'lines' => $batch->result ?? []];
    }

    /** Vrai si le prix est saisi à la main sur cette ligne (vente : unit_price/total_price ; achat : purchase_*). */
    private static function isOverridden(FolderPlannedItem $item, string $column): bool
    {
        return str_starts_with($column, 'purchase_') ? (bool) $item->purchase_price_overridden : (bool) $item->sale_price_overridden;
    }

    /** Contrôles d'une ligne + comparaison prix préparés / tarif actuel. */
    private function checkLine(Folder $folder, FolderPlannedItem $item): array
    {
        $problems = [];
        $product = $item->product;
        if (! $product) {
            $problems[] = __('produit manquant');
        }
        if (! $item->service_date) {
            $problems[] = __('date manquante');
        }
        if ((int) $item->quantity < 1) {
            $problems[] = __('nombre de participants manquant');
        }
        if ($product && $folder->pricing_client_group_id && $product->clientGroups()->exists()
            && ! $product->clientGroups()->whereKey($folder->pricing_client_group_id)->exists()) {
            $problems[] = __('produit non disponible pour la politique tarifaire du dossier');
        }
        if ($product) {
            foreach ((array) ($product->custom_field_definitions ?? []) as $def) {
                $key = $def['key'] ?? null;
                if ($key && ! empty($def['is_required']) && ($def['type'] ?? null) !== 'toggle' && blank(($item->custom_values ?? [])[$key] ?? null)) {
                    $problems[] = __('information requise manquante : :name', ['name' => $def['name'] ?? $key]);
                }
            }
            $selected = collect($item->selected_options ?? [])->pluck('product_option_id')->filter()->map(fn ($id) => (int) $id);
            $requiredGroups = ProductOption::where('product_id', $product->id)->where('is_required', true)->get()->groupBy(fn ($o) => $o->group_name ?: 'option-' . $o->id);
            foreach ($requiredGroups as $options) {
                if ($options->pluck('id')->intersect($selected)->isEmpty()) {
                    $problems[] = __('option obligatoire manquante : :name', ['name' => $options->first()->group_name ?: $options->first()->name]);
                }
            }
        }

        $prepared = ['unit_price' => $item->unit_price, 'total_price' => $item->total_price, 'purchase_unit_price' => $item->purchase_unit_price, 'purchase_total_price' => $item->purchase_total_price];
        $current = $product && $item->service_date ? ItemPricer::price(PlannedItemMapper::toPricingState($item)) : null;
        $changed = false;
        if ($current) {
            foreach (['total_price', 'purchase_total_price'] as $k) {
                // Un prix saisi à la main pour ce dossier n'est jamais comparé au tarif ni remplacé par lui.
                if (self::isOverridden($item, $k)) {
                    continue;
                }
                if ($current[$k] !== null && (int) round($current[$k]) !== (int) ($prepared[$k] ?? 0)) {
                    $changed = true;
                }
            }
        }

        return [
            'planned_item_id' => $item->id,
            'label' => $item->label(),
            'service_date' => $item->service_date?->format('d/m/Y'),
            'pax' => (int) $item->quantity,
            'options' => collect($item->selected_options ?? [])->pluck('product_option_id')->filter()->map(fn ($id) => ProductOption::find($id)?->name)->filter()->values()->all(),
            'prepared' => $prepared,
            'current' => $current ?? $prepared,
            'price_changed' => $changed,
            'problems' => $problems,
        ];
    }
}
