<?php

use App\Http\Controllers\GuideMissionStatusController;
use Illuminate\Support\Facades\Route;

Route::post('/integrations/guide-missions/status', GuideMissionStatusController::class)
    ->middleware('throttle:60,1')
    ->name('api.integrations.guide-missions.status');
