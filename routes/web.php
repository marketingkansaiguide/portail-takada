<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request; // 💡 IMPORT AJOUTÉ POUR LE TÉLÉCHARGEMENT
use App\Models\Folder;
use App\Models\FolderItem; // 💡 IMPORT AJOUTÉ POUR LES ÉTIQUETTES DE TRAIN
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\ContactController; // 💡 IMPORT AJOUTÉ POUR QUE LA CLASSE SOIT RECONNUE
use App\Http\Controllers\FolderDriveFileController;

Route::get('/robots.txt', function () {
    try {
        $preventIndexing = (bool) \App\Models\Setting::query()->value('prevent_search_indexing');
    } catch (\Throwable) {
        $preventIndexing = false;
    }

    $content = $preventIndexing
        ? "User-agent: *\nDisallow: /\n"
        : "User-agent: *\nAllow: /\n";

    return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');

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

Route::get('/dossiers/{folder}/fichiers/{fileId}', [FolderDriveFileController::class, 'show'])
    ->where('fileId', '[A-Za-z0-9_-]+')
    ->middleware(['auth:web,agency'])
    ->name('folders.drive-file');

Route::get('/dossiers/{folder}/fichiers-historiques', [FolderDriveFileController::class, 'showLegacy'])
    ->middleware(['auth:web,agency'])
    ->name('folders.legacy-file');


/*
|--------------------------------------------------------------------------
| ROUTE POUR LA PRÉ-FACTURE / RÉCAPITULATIF PDF (CORRIGÉE AVEC DOMPDF)
|--------------------------------------------------------------------------
*/
Route::get('/pdf/recapitulatif/{folder}', function (Folder $folder) {
    abort_unless(auth('web')->check(), 403);
    abort_unless(auth('web')->user()->can('view', $folder), 403);

    // 1. On charge les relations nécessaires (Agence, Prestations, Options)
    $folder->load([
        'agency',
        'folderItems' => fn ($query) => $query
            ->notCancelled()
            ->orderByRaw('service_date IS NULL')
            ->orderBy('service_date')
            ->orderBy('id'),
        'folderItems.product',
        'folderItems.productOption',
    ]);

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
    abort_unless(auth('web')->check(), 403);
    abort_unless(auth('web')->user()->can('view', $folderItem), 403);

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
})->name('pdf.fax');


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

/*
|--------------------------------------------------------------------------
| ROUTE POUR IMPRIMER / EXPORTER LA PLAQUETTE D'ÉTIQUETTES DES CHECK-INS
|--------------------------------------------------------------------------
*/
Route::get('/pdf/labels', function (Illuminate\Http\Request $request) {
    abort_unless(auth('web')->check(), 403);
    $user = auth('web')->user();
    abort_unless($user?->can('viewAny', Folder::class), 403);

    $rawIds = $request->query('ids', '');
    
    if (empty($rawIds)) {
        abort(400, 'Aucun dossier sélectionné.');
    }

    $ids = array_filter(explode(',', $rawIds));
    
    $folders = Folder::whereIn('id', $ids)
        ->whereIn('status', ['confirmed', 'completed'])
        ->orderByRaw('COALESCE(first_hotel_check_in, start_date) ASC')
        ->get();

    if ($folders->isEmpty()) {
        abort(404, 'Aucun dossier trouvé pour cette sélection.');
    }

    abort_unless($folders->every(fn (Folder $folder) => $user->can('view', $folder)), 403);

    return view('pdf.labels', compact('folders'));
})->name('pdf.labels');

Route::get('/pdf/train-labels', function (Request $request) {
    abort_unless(auth('web')->check(), 403);
    $user = auth('web')->user();
    abort_unless($user?->can('viewAny', Folder::class), 403);

    // On récupère les IDs sélectionnés passés en paramètre
    $ids = explode(',', $request->get('ids'));
    $items = FolderItem::with('folder')->whereIn('id', $ids)->get();
    abort_unless($items->isNotEmpty() && $items->every(fn (FolderItem $item) => $user->can('view', $item)), 403);
    
    return view('pdf.train-labels', compact('items'));
})->name('pdf.train-labels');

Route::get('/pdf/seven-tickets-labels', function (Request $request) {
    abort_unless(auth('web')->check(), 403);
    $user = auth('web')->user();
    abort_unless($user?->can('viewAny', Folder::class), 403);

    $ids = array_filter(explode(',', (string) $request->query('ids', '')));

    if ($ids === []) {
        abort(400, 'Aucune prestation sélectionnée.');
    }

    $records = \App\Filament\Pages\SevenTicketsOrders::getSevenTicketsItemsQuery()
        ->whereIn('folder_items.id', $ids)
        ->orderBy('folder_items.service_date')
        ->get();

    if ($records->isEmpty()) {
        abort(404, 'Aucune prestation Seven Tickets trouvée pour cette sélection.');
    }

    abort_unless($records->every(fn (FolderItem $item) => $user->can('view', $item)), 403);

    return view('pdf.seven-tickets-labels', compact('records'));
})->name('pdf.seven-tickets-labels');
