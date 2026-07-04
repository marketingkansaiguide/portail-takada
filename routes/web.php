<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// On laisse l'accueil libre pour le panel Filament Agence
// Route::get('/', function () {
//     return view('welcome');
// });

/*
|--------------------------------------------------------------------------
| SOLUTION INFAILLIBLE POUR LES IMAGES SOUS WINDOWS / HERD
|--------------------------------------------------------------------------
| Cette route intercepte toutes les requêtes vers /storage/... et va 
| lire le fichier directement sur le disque, sans avoir besoin du 
| lien symbolique capricieux de Windows.
*/
Route::get('/storage/{path}', function (string $path) {
    // 1. On cherche d'abord dans le bon dossier (storage/app/public)
    $publicDisk = Storage::disk('public');
    if ($publicDisk->exists($path)) {
        return response()->file($publicDisk->path($path));
    }
    
    // 2. Si l'image a été mal sauvegardée, on fouille dans le dossier racine (storage/app)
    $localDisk = Storage::disk('local');
    if ($localDisk->exists($path)) {
        return response()->file($localDisk->path($path));
    }

    // 3. Si l'image n'est nulle part, on renvoie une 404
    abort(404, "L'image n'existe pas physiquement sur votre disque dur.");
})->where('path', '.*'); // Le '.*' permet d'accepter les sous-dossiers (ex: products/image.jpg)


// Votre route de langue (toujours intacte)
Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, ['fr', 'en', 'ja'])) {
        session(['locale' => $locale]);
    }
    
    return redirect()->back();
})->name('lang.switch');