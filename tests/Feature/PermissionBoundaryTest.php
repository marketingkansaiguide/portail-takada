<?php

use App\Models\Folder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

test('an agency account can only access folders belonging to its agency', function () {
    $agencyUser = new User([
        'id' => 10,
        'role' => User::ROLE_AGENCY,
        'agency_id' => 1,
    ]);

    $ownFolder = new Folder(['origin' => 'b2b', 'agency_id' => 1, 'status' => 'draft']);
    $foreignFolder = new Folder(['origin' => 'b2b', 'agency_id' => 2, 'status' => 'draft']);

    expect($agencyUser->can('view', $ownFolder))->toBeTrue()
        ->and($agencyUser->can('update', $ownFolder))->toBeTrue()
        ->and($agencyUser->can('view', $foreignFolder))->toBeFalse()
        ->and($agencyUser->can('update', $foreignFolder))->toBeFalse();
});

test('an agency account cannot manage an internal account accidentally linked to its agency', function () {
    $agencyUser = new User([
        'id' => 10,
        'role' => User::ROLE_AGENCY,
        'agency_id' => 1,
    ]);

    $internalUser = new User([
        'id' => 11,
        'role' => User::ROLE_AGENT,
        'agency_id' => 1,
    ]);

    expect($agencyUser->can('view', $internalUser))->toBeFalse()
        ->and($agencyUser->can('update', $internalUser))->toBeFalse()
        ->and($agencyUser->can('delete', $internalUser))->toBeFalse();
});

test('only the super administrator can access the role matrix', function () {
    $agent = new User(['role' => User::ROLE_AGENT]);
    $superAdmin = new User(['role' => User::ROLE_SUPER_ADMIN]);
    $role = new Role(['id' => User::ROLE_AGENT]);

    expect($agent->can('viewAny', Role::class))->toBeFalse()
        ->and($agent->can('update', $role))->toBeFalse()
        ->and($superAdmin->can('viewAny', Role::class))->toBeTrue()
        ->and($superAdmin->can('update', $role))->toBeTrue();
});

test('sensitive document routes remain registered and the legacy downloader is absent', function () {
    foreach (['pdf.recapitulatif', 'pdf.fax', 'pdf.labels', 'pdf.train-labels', 'pdf.seven-tickets-labels'] as $routeName) {
        expect(Route::has($routeName))->toBeTrue();
    }

    expect(Route::has('file.download'))->toBeFalse();
});
