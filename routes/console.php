<?php

use App\Models\Folder;
use Illuminate\Support\Facades\Schedule;

// 🔄 CRON AUTOMATIQUE : Clôture les dossiers après la date de départ du Japon
// Cette tâche s'exécute automatiquement en arrière-plan tous les jours à minuit
Schedule::call(function () {
    Folder::whereIn('status', ['draft', 'pending', 'confirmed'])
        ->whereDate('end_date', '<', now())
        ->update(['status' => 'completed']);
})->daily();