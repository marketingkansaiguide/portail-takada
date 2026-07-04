<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Models\Folder;
use Barryvdh\DomPDF\Facade\Pdf;

// On laisse l'accueil libre pour le panel Filament Agence
// Route::get('/', function () {
//     return view('welcome');
// });

/*
|--------------------------------------------------------------------------
| ROUTE POUR LA PRÉ-FACTURE / RÉCAPITULATIF PDF (CORRIGÉE AVEC DOMPDF)
|--------------------------------------------------------------------------
*/
Route::get('/pdf/recapitulatif/{folder}', function (Folder $folder) {
    // 1. On charge les relations nécessaires (Agence, Prestations, Options)
    $folder->load(['agency', 'folderItems.product', 'folderItems.productOption']);

    // 2. On prépare toutes les variables dynamiques
    $dateEmit = now()->format('d/m/Y');
    $agency = $folder->agency;
    $totalPax = $folder->pax_adults + $folder->pax_children;
    $items = $folder->folderItems;
    $itemsTotal = $items->sum('total_price');
    $grandTotal = $itemsTotal + $folder->folder_fee;
    
    // 3. ON GÉNÈRE LE VRAI PDF AVEC LE PACKAGE BARRYVDH
    $pdf = Pdf::loadView('pdf.recapitulatif', compact(
        'folder',
        'dateEmit',
        'agency',
        'totalPax',
        'items',
        'itemsTotal',
        'grandTotal'
    ));

    // 4. On retourne le fichier PDF au navigateur (stream pour l'afficher, ou download pour forcer le téléchargement)
    return $pdf->stream('recapitulatif_' . $folder->reference . '.pdf');

})->name('pdf.recapitulatif');


/*
|--------------------------------------------------------------------------
| SOLUTION INFAILLIBLE POUR LES IMAGES SOUS WINDOWS / HERD
|--------------------------------------------------------------------------
*/
Route::get('/storage/{path}', function (string $path) {
    $publicDisk = Storage::disk('public');
    if ($publicDisk->exists($path)) {
        return response()->file($publicDisk->path($path));
    }
    
    $localDisk = Storage::disk('local');
    if ($localDisk->exists($path)) {
        return response()->file($localDisk->path($path));
    }

    abort(404, "L'image n'existe pas physiquement sur votre disque dur.");
})->where('path', '.*');


/*
|--------------------------------------------------------------------------
| ROUTE DE LANGUE
|--------------------------------------------------------------------------
*/
Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, ['fr', 'en', 'ja'])) {
        session(['locale' => $locale]);
    }
    
    return redirect()->back();
})->name('lang.switch');