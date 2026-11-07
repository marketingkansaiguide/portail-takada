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
