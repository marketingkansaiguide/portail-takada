<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it("classe les dossiers existants en b2b sans toucher à leur agence, puis rend l'origine obligatoire", function () {
    // On revient à l'état de la production actuelle (avant les lots 2 et 3a).
    (require database_path('migrations/2026_11_07_120000_create_folder_planned_items_and_order_batches.php'))->down();
    (require database_path('migrations/2026_11_07_110000_add_internal_folder_fields_to_folders_table.php'))->down();
    $migration = require database_path('migrations/2026_11_07_100000_add_origin_and_client_agency_to_folders_table.php');
    $migration->down();
    expect(Schema::hasColumn('folders', 'origin'))->toBeFalse();

    $groupId = DB::table('client_groups')->insertGetId(['name' => 'Groupe', 'created_at' => now(), 'updated_at' => now()]);
    $agencyId = DB::table('agencies')->insertGetId(['name' => 'Agence', 'email' => 'a@example.test', 'client_group_id' => $groupId, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('folders')->insert([
        ['reference' => 'TKD-1', 'lead_traveler_name' => 'X', 'start_date' => '2027-01-01', 'end_date' => '2027-01-05', 'agency_id' => $agencyId, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()],
        ['reference' => 'TKD-2', 'lead_traveler_name' => 'X', 'start_date' => '2027-01-01', 'end_date' => '2027-01-05', 'agency_id' => $agencyId, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $migration->up();

    expect(DB::table('folders')->pluck('origin')->unique()->all())->toBe(['b2b'])
        ->and(DB::table('folders')->pluck('agency_id')->unique()->all())->toBe([$agencyId])
        ->and(fn () => DB::table('folders')->insert(['reference' => 'TKD-3', 'lead_traveler_name' => 'X', 'start_date' => '2027-01-01', 'end_date' => '2027-01-05', 'agency_id' => $agencyId]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
