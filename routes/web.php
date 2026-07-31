<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request; // 💡 IMPORT AJOUTÉ POUR LE TÉLÉCHARGEMENT
use App\Models\Folder;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\ContactController; // 💡 IMPORT AJOUTÉ POUR QUE LA CLASSE SOIT RECONNUE

// On laisse l'accueil libre pour le panel Filament Agence
// Route::get('/', function () {
//     return view('welcome');
// });

/*
|--------------------------------------------------------------------------
| Routes pour la page de contact / sur-mesure
|--------------------------------------------------------------------------
*/

Route::get('/contact', [ContactController::class, 'index'])->name('contact.index');
Route::post('/contact', [ContactController::class, 'send'])->name('contact.send');


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
| ROUTE POUR GÉNÉRER LE FAX (VUE HTML PRÊTE À IMPRIMER / PDF)
|--------------------------------------------------------------------------
*/
Route::get('/pdf/fax/{folderItem}', function (\App\Models\FolderItem $folderItem) {
    $item = $folderItem->load(['product.supplier', 'folder']);
    
    $faxData = $item->product->supplier_fax_header ?? [];
    if (!is_array($faxData)) $faxData = [];

    $writerName = auth()->check() ? auth()->user()->name : 'Takada Travel';
    $writerEmail = auth()->check() ? auth()->user()->email : 'resa@kansai-guide.com';

    $data = [
        'date' => now()->format('Y/m/d'),
        'to_company' => $faxData['to_company_name'] ?? ($item->product->supplier->name ?? ''),
        'to_contact' => $faxData['to_contact_name'] ?? 'ご担当者様',
        'to_tel' => $faxData['to_tel'] ?? ($item->product->supplier->phone ?? ''),
        'to_fax' => $faxData['to_fax'] ?? ($item->product->supplier->phone ?? ''),
        
        'from_company' => $faxData['from_company'] ?? 'TAKADA TRAVEL合同会社',
        'from_address' => $faxData['from_address'] ?? "〒532-0012大阪市淀川区木川東\n3丁目1-23 KC新大阪ビル 2階",
        'from_contact' => str_replace('[NOM_AGENT]', $writerName, $faxData['from_contact'] ?? '担当者： [NOM_AGENT]'),
        'from_mail' => str_replace('[EMAIL_AGENT]', $writerEmail, $faxData['from_mail'] ?? 'MAIL : [EMAIL_AGENT]'),
        'from_tel' => $faxData['from_tel'] ?? 'TEL：06-6195-9799',
        'from_fax' => $faxData['from_fax'] ?? 'FAX：06-6195-9921',
        
        'subject' => $item->parseSupplierEmailSubject(),
        'body' => $item->parseSupplierEmail(),
        'writer_name' => $writerName,
    ];

    return view('pdf.fax', $data);
})->name('pdf.fax')->middleware('auth');


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
| ROUTE DE TÉLÉCHARGEMENT UNIVERSELLE (PARFEITE POUR WINDOWS / HERD)
|--------------------------------------------------------------------------
*/
Route::get('/download-file', function (Illuminate\Http\Request $request) {
    $path = $request->query('path');
    
    if (!$path) {
        abort(404, 'Le chemin du fichier est manquant.');
    }

    // 1. Nettoyage des slashs Windows et des préfixes inutiles
    $cleanPath = str_replace(['\\', '//'], '/', $path);
    $cleanPath = preg_replace('#^/?(storage/|public/)#', '', $cleanPath);
    $cleanPath = ltrim($cleanPath, '/');

    // 2. Recherche sur le disque public (storage/app/public/...)
    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($cleanPath)) {
        return \Illuminate\Support\Facades\Storage::disk('public')->download($cleanPath);
    }

    // 3. Recherche sur le disque local (storage/app/...)
    if (\Illuminate\Support\Facades\Storage::disk('local')->exists($cleanPath)) {
        return \Illuminate\Support\Facades\Storage::disk('local')->download($cleanPath);
    }

    // 4. Vérification physique directe des fichiers sur le disque dur
    $pubFile = storage_path('app/public/' . $cleanPath);
    if (file_exists($pubFile)) {
        return response()->download($pubFile);
    }

    $locFile = storage_path('app/' . $cleanPath);
    if (file_exists($locFile)) {
        return response()->download($locFile);
    }

    abort(404, "Fichier introuvable sur le serveur ($cleanPath).");
})->name('file.download');
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