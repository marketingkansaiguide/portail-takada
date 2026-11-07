<?php

use App\Models\Agency;
use App\Models\Category;
use App\Models\ClientGroup;
use App\Models\Folder;
use App\Models\Hotel;
use App\Models\ItemStatus;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Catalogue;
use Tests\Support\World;

uses(RefreshDatabase::class);

/*
 * Matrice de référence profils × droits (profils réels de la production).
 * Toute évolution des droits d'un profil existant fait échouer ce test : elle doit
 * être voulue, puis l'instantané régénéré (UPDATE_PERMISSION_SNAPSHOT=1).
 */
it('conserve les droits de chaque profil existant', function () {
    Catalogue::load();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $snapshotFile = __DIR__ . '/../../Fixtures/permission-matrix.json';

    $models = [
        'dossiers' => Folder::class, 'produits' => Product::class, 'catégories' => Category::class,
        'agences' => Agency::class, 'utilisateurs' => User::class, 'profils' => Role::class,
        'fournisseurs' => Supplier::class, 'hôtels' => Hotel::class, 'groupes clients' => ClientGroup::class,
        'paramètres' => Setting::class, 'statuts prestation' => ItemStatus::class,
    ];
    $pages = [
        'check-ins' => \App\Filament\Pages\UpcomingCheckins::class,
        'billets de train' => \App\Filament\Pages\TrainTickets::class,
        'cartes IC' => \App\Filament\Pages\IcCards::class,
        'IWA' => \App\Filament\Pages\IwaOrders::class,
        'Seven Tickets' => \App\Filament\Pages\SevenTicketsOrders::class,
        'statistiques' => \App\Filament\Pages\Statistics::class,
        'SEO' => \App\Filament\Pages\SeoDashboard::class,
    ];

    $matrix = [];
    foreach (DB::table('roles')->orderBy('id')->pluck('id') as $roleId) {
        $this->app['auth']->forgetGuards(); // aucune session résiduelle du profil précédent
        $user = $roleId === 'agency' ? World::agencyUser(World::agency('Agence ' . uniqid())) : World::staff($roleId);
        $this->actingAs($user, $roleId === 'agency' ? 'agency' : 'web');

        $row = [
            'panel admin' => $user->canAccessPanel(Filament::getPanel('admin')),
            'panel agence' => $user->canAccessPanel(Filament::getPanel('agency')),
        ];
        foreach ($models as $label => $class) {
            foreach (['viewAny', 'create'] as $ability) {
                $row["{$label}.{$ability}"] = $user->can($ability, $class);
            }
        }
        foreach ($pages as $label => $class) {
            $row["page {$label}"] = $class::canAccess();
        }
        $matrix[$roleId] = $row;
    }

    if (getenv('UPDATE_PERMISSION_SNAPSHOT') === '1') {
        file_put_contents($snapshotFile, json_encode($matrix, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->markTestSkipped('Matrice des droits régénérée.');
    }

    expect($matrix)->toEqual(json_decode(file_get_contents($snapshotFile), true));
});
