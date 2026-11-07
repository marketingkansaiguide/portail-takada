<?php
/**
 * Test de bout en bout portail Laravel ↔ Planning Guides (WordPress local).
 * Exécuté depuis la racine du portail : php <ce fichier>
 * Variables d'env : DB_* (base portail_recette), GUIDE_PLANNING_* (WordPress local :8020).
 */
require 'C:/Users/marke/Herd/portail-takada/vendor/autoload.php';
$app = require 'C:/Users/marke/Herd/portail-takada/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Jobs\CancelGuideMission;
use App\Jobs\SyncGuideMission;
use App\Models\FolderItem;
use App\Models\ItemStatus;
use App\Models\Product;
use Tests\Support\World;

$S = 'C:/Users/marke/AppData/Local/Temp/claude/6429a6142552b01f/33461260-0813-43b6-8889-aa43ed767642/scratchpad';
$wpCli = fn (string $cmd) => trim((string) shell_exec("php {$S}/wp-test-dl/wp-cli.phar --path={$S}/wp-site {$cmd} 2>&1"));
$wp = new PDO('mysql:host=127.0.0.1;port=3307;dbname=wp_test;charset=utf8mb4', 'root', '');
$wp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$rows = fn (string $ref) => $wp->query("SELECT id, external_mission_id, employee_id, status, start_time, end_time, remuneration, external_reference, ticketing_order_item_id, message_history IS NOT NULL AS has_history FROM wp_pm_availabilities WHERE external_source = 'ticketing_portal' AND (external_reference = " . $wp->quote($ref) . " OR external_reference LIKE " . $wp->quote($ref . '#%') . ")")->fetchAll(PDO::FETCH_ASSOC);
// Base de TEST uniquement : les références folder_item_<id> repartent de zéro quand la démo
// Laravel est réinitialisée ; on efface donc les missions de test du portail laissées par les exécutions précédentes.
$wp->exec("DELETE FROM wp_pm_availabilities WHERE external_source = 'ticketing_portal'");
$report = [];
$check = function (bool $ok, string $label) use (&$report) { $report[] = ($ok ? 'OK   ' : 'KO   ') . $label; };
$run = function (callable $job) {
    try { $job(); return null; } catch (Throwable $e) { return $e->getMessage(); }
};

// Guide de test côté WordPress (une seule fois)
$employeeId = (int) $wp->query("SELECT id FROM wp_pm_employees WHERE name = 'Guide Recette'")->fetchColumn();
if (! $employeeId) {
    $userId = (int) $wpCli('user create guide.recette guide.recette@recette.test --role=subscriber --porcelain');
    $wp->exec("INSERT INTO wp_pm_employees (user_id, name, profile_type, is_active) VALUES ({$userId}, 'Guide Recette', 'independent', 1)");
    $employeeId = (int) $wp->lastInsertId();
}

// Dates propres à chaque exécution (évite les chevauchements d'horaires du guide)
$d1 = date('Y-m-d', strtotime('2028-01-01 +' . (random_int(0, 1500) * 3) . ' days')); // dates espacées : aucun chevauchement entre exécutions
$d2 = date('Y-m-d', strtotime($d1 . ' +1 day'));

// 1. Laravel crée la mission (dossier agence confirmé + prestation guide)
auth('web')->login(App\Models\User::where('role', 'super_admin')->first());
$product = Product::where('category_id', 4)->whereNotNull('guide_city')->whereNotNull('guide_start_time')->orderBy('id')->firstOrFail();
$agency = App\Models\Agency::firstOrFail();
$folder = World::folder($agency, ['status' => 'confirmed', 'folder_name' => 'E2E ' . date('His')]);
$item = World::item($folder, $product, ['service_date' => $d1, 'quantity' => 3, 'pax_adults' => 3, 'purchase_total_price' => 21000]);
$item->refresh();
$ref = 'folder_item_' . $item->id;
$check($item->guide_sync_status === 'synced' && $item->guide_mission_id > 0, "1. Laravel crée la mission (statut portail : {$item->guide_sync_status}, mission n° {$item->guide_mission_id})");

// 2. WordPress reçoit la mission
$r = $rows($ref);
$check(count($r) === 1 && (int) $r[0]['external_mission_id'] === (int) $item->guide_mission_id, '2. WordPress reçoit 1 mission (id ' . ($r[0]['id'] ?? '?') . ')');
$originalId = (int) ($r[0]['id'] ?? 0);

// 3. Un guide est affecté (depuis l'administration WordPress)
$out = $wpCli("eval-file {$S}/wp-e2e/wp-admin-edit.php {$originalId} {$employeeId} 09:00 13:00 réservé");
$r = $rows($ref);
$check(count($r) === 1 && (int) $r[0]['employee_id'] === $employeeId, "3. Guide affecté (réponse : " . mb_substr($out, 0, 80) . ')');

// 4. Modification de la mission depuis l'administration WordPress
$currentId = (int) ($r[0]['id'] ?? 0);
$out = $wpCli("eval-file {$S}/wp-e2e/wp-admin-edit.php {$currentId} {$employeeId} 10:00 14:00 réservé");
$r = $rows($ref);
$idAfterEdit = (int) ($r[0]['id'] ?? 0);
$check(count($r) === 1 && $r[0]['start_time'] === '10:00:00', "4. Mission modifiée dans WordPress (id avant {$originalId} / après {$idAfterEdit}, mission portail {$item->guide_mission_id})");
$check($idAfterEdit === $originalId, '4 bis. Identifiant d’origine conservé après modification');

// 5. Laravel met à jour la mission (changement de pax)
$err = $run(fn () => $item->fresh()->update(['quantity' => 4, 'pax_adults' => 4]));
$item->refresh();
$r = $rows($ref);
$check($err === null && $item->guide_sync_status === 'synced' && count($r) === 1 && (int) $r[0]['employee_id'] === $employeeId && $r[0]['status'] === 'réservé',
    '5. Laravel met à jour : 1 seule mission, guide et statut conservés' . ($err ? " (erreur : {$err})" : ''));

// 6. Laravel demande l'annulation
$err = $run(fn () => $item->fresh()->update(['item_status_id' => ItemStatus::where('name', 'Annulé')->value('id')]));
$item->refresh();
$r = $rows($ref);
$check($err === null && $item->guide_sync_status === 'cancelled', "6. Laravel demande l'annulation (statut portail : {$item->guide_sync_status}" . ($item->guide_sync_error ? ", erreur : {$item->guide_sync_error}" : '') . ')');

// 7. WordPress annule la bonne mission
$check(count($r) === 1 && in_array($r[0]['status'], ['annulé', 'annulé-rémunéré'], true), '7. WordPress : mission annulée (statut : ' . ($r[0]['status'] ?? '?') . ')');

// 8. Aucun doublon, informations conservées
$all = $wp->query("SELECT COUNT(*) FROM wp_pm_availabilities WHERE external_source = 'ticketing_portal' AND ticketing_order_item_id = {$item->id}")->fetchColumn();
$check((int) $all === 1 && (int) $r[0]['employee_id'] === $employeeId && $r[0]['remuneration'] !== null && $r[0]['external_reference'] === $ref,
    "8. Aucun doublon ({$all} ligne), guide, rémunération et référence conservés");

// Tentatives répétées : renvoyer l'annulation ne casse rien
$err = $run(fn () => app()->call([new CancelGuideMission($item->id), 'handle']));
$check($err === null && count($rows($ref)) === 1, 'Annulation renvoyée une 2e fois : sans erreur, sans doublon' . ($err ? " ({$err})" : ''));

// Panne réseau puis reprise (création d'une 2e mission alors que WordPress est injoignable)
$good = config('services.guide_planning.base_url');
config(['services.guide_planning.base_url' => 'http://127.0.0.1:9/wp-json/pm/v1']);
$item2 = null;
$err = $run(function () use (&$item2, $folder, $product, $d2) {
    $item2 = World::item($folder, $product, ['service_date' => $d2, 'quantity' => 2, 'pax_adults' => 2, 'purchase_total_price' => 21000]);
});
if (! $item2) {
    throw new RuntimeException('Création de la 2e prestation impossible : ' . $err);
}
$item2->refresh();
$ref2 = 'folder_item_' . $item2->id;
$check($item2->guide_sync_status === 'failed' && count($rows($ref2)) === 0, 'Panne réseau : synchronisation marquée en échec, aucune mission créée (' . count($rows($ref2)) . ' ligne)');
config(['services.guide_planning.base_url' => $good]);
app()->call([new SyncGuideMission($item2->id), 'handle']);
app()->call([new SyncGuideMission($item2->id), 'handle']); // rejeu
$check(count($rows($ref2)) === 1 && $item2->fresh()->guide_sync_status === 'synced', 'Reprise + rejeu : 1 seule mission');

// Missions existantes : missions déjà « désynchronisées » par une édition faite avant le correctif
foreach (FolderItem::where('guide_sync_status', 'failed')->whereNotNull('guide_mission_id')->where('id', '!=', $item->id)->get() as $old) {
    $oldRows = $rows('folder_item_' . $old->id);
    $diverged = $oldRows && (int) $oldRows[0]['id'] !== (int) $oldRows[0]['external_mission_id'];
    $err = $run(fn () => app()->call([new CancelGuideMission($old->id), 'handle']));
    $after = $rows('folder_item_' . $old->id);
    $check($err === null && in_array($after[0]['status'] ?? '', ['annulé', 'annulé-rémunéré'], true) && (int) $after[0]['employee_id'] === (int) $oldRows[0]['employee_id'],
        "Mission existante n° {$old->guide_mission_id} (ligne " . ($oldRows[0]['id'] ?? '?') . ($diverged ? ', identifiant déjà changé' : '') . ") : annulation " . ($err ? "en échec ({$err})" : 'réussie') . ', guide conservé');
}

// Mission créée directement dans WordPress (sans lien portail) : l'édition garde le comportement actuel
$wpTag = uniqid(); $d3 = date('Y-m-d', strtotime($d1 . ' +5 days'));
$wp->exec("INSERT INTO wp_pm_availabilities (employee_id, start_date, end_date, start_time, end_time, status, mission_title, agency, client, comment, remuneration, mission_group_id) VALUES ({$employeeId}, '$d3', '$d3', '09:00:00', '12:00:00', 'réservé', 'Mission WordPress $wpTag', 'Agence WP', 'Client WP', '', '10000', 'wp_" . uniqid() . "')");
$wpId = (int) $wp->lastInsertId();
$out = $wpCli("eval-file {$S}/wp-e2e/wp-admin-edit.php {$wpId} {$employeeId} 13:00 17:00 réservé");
$wpRows = $wp->query("SELECT id, employee_id, status, start_time, external_source FROM wp_pm_availabilities WHERE mission_title = 'Mission WordPress $wpTag'")->fetchAll(PDO::FETCH_ASSOC);
$check(count($wpRows) === 1 && $wpRows[0]['start_time'] === '13:00:00' && (int) $wpRows[0]['employee_id'] === $employeeId && $wpRows[0]['external_source'] === null,
    'Mission créée dans WordPress : modification enregistrée comme avant (ligne ' . $wpId . ' → ' . ($wpRows[0]['id'] ?? '?') . ', sans référence portail)');

echo implode(PHP_EOL, $report), PHP_EOL;
