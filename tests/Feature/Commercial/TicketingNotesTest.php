<?php

use App\Models\FolderHistory;
use App\Models\FolderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Catalogue;
use Tests\Support\World;

uses(RefreshDatabase::class);

beforeEach(function () {
    Catalogue::load();
    $this->fit = World::staff('commercial_fit');
    $this->folder = World::internalFolder(null, ['start_date' => '2026-12-17', 'end_date' => '2027-01-03', 'owner_user_id' => $this->fit->id, 'ops_status' => 'en_preparation', 'kind' => 'full_trip']);
});

function orderedWithNotes($test): FolderItem
{
    $planned = World::plannedItem($test->folder, World::activityProduct(), [
        'ticketing_notes' => 'SECRET-NOTE bus Nohi à réserver',
        'meeting_point' => 'SECRET-RDV Hôtel Asakusa',
        'meeting_time' => '09:30',
    ]);
    $user = \App\Models\User::where('role', 'super_admin')->first() ?? World::staff('super_admin');
    $folder = $test->folder->fresh();
    app(\App\Services\PlannedItemOrderService::class)->order($folder, [$planned->id], $user, (int) $folder->lock_version, (string) \Illuminate\Support\Str::uuid(), true);

    return FolderItem::where('planned_item_id', $planned->id)->firstOrFail();
}

it('trace la modification des consignes après commande', function () {
    $item = orderedWithNotes($this);

    $item->update(['ticketing_notes' => 'Nouvelle consigne']);

    $entry = FolderHistory::where('folder_id', $this->folder->id)->latest('id')->first();
    expect($entry->description ?? $entry->details ?? json_encode($entry->toArray()))
        ->toContain('Informations pour la billetterie')->toContain('Nouvelle consigne');
});

it('ne diffuse jamais les consignes vers guide, agenda, e-mails ou fournisseur', function () {
    $files = array_merge(
        [app_path('Services/GuideMissionPayloadFactory.php'), app_path('Services/FolderItemCalendar.php')],
        glob(app_path('Mail/*.php')),
        glob(resource_path('views/emails/*.blade.php')) ?: [],
        glob(resource_path('views/mail/*.blade.php')) ?: [],
        glob(resource_path('views/**/*mail*.blade.php')) ?: [],
    );
    foreach (array_unique($files) as $file) {
        expect(file_get_contents($file))->not->toMatch('/ticketing_notes|ticketing_general_notes|ticketing_urgent|meeting_point/', $file);
    }
});

it('conserve une consigne urgente courte distincte des consignes générales', function () {
    $this->folder->update(['ticketing_urgent' => true, 'ticketing_urgent_note' => 'Bus à réserver pour le 23/12.', 'ticketing_general_notes' => 'Général']);
    $fresh = $this->folder->fresh();
    expect($fresh->ticketing_urgent_note)->toBe('Bus à réserver pour le 23/12.')
        ->and($fresh->ticketing_general_notes)->toBe('Général');
});
