<?php

namespace Tests\Support;

use App\Models\Agency;
use App\Models\Folder;
use App\Models\FolderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Petites fabriques de données pour les tests de référence. */
final class World
{
    public static function agency(string $name = 'Agence A', ?int $clientGroupId = null): Agency
    {
        // agencies.client_group_id est obligatoire en base.
        $clientGroupId ??= (int) DB::table('client_groups')->orderBy('id')->value('id');

        return Agency::create(['name' => $name, 'email' => strtolower(str_replace(' ', '', $name)) . '@example.test', 'client_group_id' => $clientGroupId, 'is_approved' => true]);
    }

    public static function agencyUser(Agency $agency, string $email = null): User
    {
        $user = User::create([
            'name' => 'Vendeur ' . $agency->name,
            'email' => $email ?? 'vendeur' . $agency->id . '-' . uniqid() . '@example.test',
            'password' => 'secret-password',
            'role' => User::ROLE_AGENCY,
            'agency_id' => $agency->id,
        ]);
        DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => User::ROLE_AGENCY]);

        return $user->fresh();
    }

    public static function staff(string $role = 'super_admin'): User
    {
        $user = User::create([
            'name' => 'Staff ' . $role,
            'email' => $role . '-' . uniqid() . '@example.test',
            'password' => 'secret-password',
            'role' => $role,
        ]);
        DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => $role]);

        return $user->fresh();
    }

    public static function folder(Agency $agency, array $attributes = []): Folder
    {
        return Folder::create(array_merge([
            'origin' => Folder::ORIGIN_B2B,
            'agency_id' => $agency->id,
            'folder_name' => 'DUPONT',
            'lead_traveler_name' => 'Jean Dupont',
            'status' => 'draft',
            'pax_adults' => 2,
            'pax_children' => 0,
            'start_date' => '2027-04-01',
            'end_date' => '2027-04-10',
        ], $attributes));
    }

    /** Dossier interne Takada, éventuellement associé à une agence pour information. */
    public static function internalFolder(?Agency $clientAgency = null, array $attributes = []): Folder
    {
        return Folder::create(array_merge([
            'origin' => Folder::ORIGIN_INTERNAL,
            'client_agency_id' => $clientAgency?->id,
            'folder_name' => 'MARTIN',
            'lead_traveler_name' => 'Claire Martin',
            'status' => 'draft',
            'pax_adults' => 2,
            'pax_children' => 0,
            'start_date' => '2027-05-01',
            'end_date' => '2027-05-12',
        ], $attributes));
    }

    /** Prestation préparée valide (champs et options obligatoires renseignés). */
    public static function plannedItem(Folder $folder, Product|int $product, array $attributes = []): \App\Models\FolderPlannedItem
    {
        $product = $product instanceof Product ? $product : Product::findOrFail($product);
        $custom = [];
        foreach ((array) ($product->custom_field_definitions ?? []) as $def) {
            if (! empty($def['is_required']) && ! empty($def['key'])) {
                $custom[$def['key']] = $def['choices'][0] ?? 'Réponse de test';
            }
        }
        $options = \App\Models\ProductOption::where('product_id', $product->id)->where('is_required', true)->get()
            ->groupBy(fn ($o) => $o->group_name ?: 'option-' . $o->id)
            ->map(fn ($group) => ['product_option_id' => $group->first()->id, 'quantity' => 1])->values()->all();

        $state = ['product_id' => $product->id, 'service_date' => $attributes['service_date'] ?? '2027-04-05', 'quantity' => $attributes['quantity'] ?? 2, 'selected_options' => $options, 'custom_values' => $custom];
        $price = \App\Support\ItemPricer::price($state);

        return \App\Models\FolderPlannedItem::create(array_merge([
            'folder_id' => $folder->id,
            'product_id' => $product->id,
            'service_date' => $state['service_date'],
            'quantity' => $state['quantity'],
            'pax_adults' => $state['quantity'],
            'selected_options' => $options,
            'custom_values' => $custom,
            'unit_price' => (int) round($price['unit_price'] ?? 0),
            'total_price' => (int) round($price['total_price'] ?? 0),
            'purchase_unit_price' => $price['purchase_unit_price'] !== null ? (int) round($price['purchase_unit_price']) : null,
            'purchase_total_price' => $price['purchase_total_price'] !== null ? (int) round($price['purchase_total_price']) : null,
        ], $attributes));
    }

    /** Prestation commandée d'un dossier interne, par le vrai service de commande. */
    public static function orderedInternalItem(Folder $folder, Product|int $product, array $attributes = []): FolderItem
    {
        if ($folder->hasUndeterminedPricingPolicy()) {
            $folder->update(['sale_type' => 'b2c', 'pricing_client_group_id' => (int) DB::table('client_groups')->orderBy('id')->value('id')]);
        }
        $planned = self::plannedItem($folder, $product, $attributes);
        $user = User::where('role', 'super_admin')->first() ?? self::staff('super_admin');
        $folder = $folder->fresh();
        $result = app(\App\Services\PlannedItemOrderService::class)->order($folder, [$planned->id], $user, (int) $folder->lock_version, (string) \Illuminate\Support\Str::uuid(), true);
        if ($result['status'] !== 'completed') {
            throw new \RuntimeException('Commande de test refusée : ' . $result['message']);
        }

        return FolderItem::where('planned_item_id', $planned->id)->firstOrFail();
    }

    public static function item(Folder $folder, Product|int $product, array $attributes = []): FolderItem
    {
        $productId = $product instanceof Product ? $product->id : $product;

        return FolderItem::create(array_merge([
            'folder_id' => $folder->id,
            'product_id' => $productId,
            'service_date' => '2027-04-05',
            'quantity' => 2,
            'pax_adults' => 2,
            'unit_price' => 10000,
            'total_price' => 20000,
            'purchase_unit_price' => 7000,
            'purchase_total_price' => 14000,
        ], $attributes));
    }

    /** Premier produit guide (catégorie 4) du catalogue chargé. */
    public static function guideProduct(): Product
    {
        return Product::where('category_id', 4)->orderBy('id')->firstOrFail();
    }

    public static function activityProduct(): Product
    {
        return Product::where('category_id', 1)->orderBy('id')->firstOrFail();
    }
}
