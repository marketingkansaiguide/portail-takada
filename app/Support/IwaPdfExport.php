<?php

namespace App\Support;

use App\Models\Folder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/** Commande IWA en PDF : un PDF par dossier client, regroupés dans un ZIP s'il y en a plusieurs. */
class IwaPdfExport
{
    /** Date japonaise : 2026年10月28日. */
    public static function jaDate(?string $date): string
    {
        if (blank($date)) {
            return '';
        }
        try {
            return Carbon::parse($date)->format('Y年n月j日');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Lignes groupées par dossier (ordre : début de dossier, puis id).
     *
     * @param  iterable<array<string, mixed>>  $routes
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    public static function groupByFolder(iterable $routes): Collection
    {
        return collect($routes)->map(fn ($r) => (array) $r)
            ->groupBy(fn ($r) => (int) ($r['folder_id'] ?? 0))
            ->sortBy(fn ($rows) => [($rows->first()['folder_start'] ?? '') ?: '9999-99-99', (int) ($rows->first()['folder_id'] ?? 0)])
            ->values();
    }

    /** Données de la vue pour un dossier. */
    public static function viewData(Collection $rows, ?Folder $folder): array
    {
        $rows = $rows->sortBy(fn ($r) => [(string) ($r['date'] ?? '') ?: '9999-99-99', IwaRowFormatter::normalizeTime($r['dep_time'] ?? '')])->values();
        $jaNames = IwaRowFormatter::loadJaNames($rows);
        $first = $rows->first() ?? [];

        $start = $folder?->start_date ? Carbon::parse($folder->start_date)->format('Y-m-d') : ($first['folder_start'] ?? null);
        $end = $folder?->end_date ? Carbon::parse($folder->end_date)->format('Y-m-d') : null;

        return [
            'agency' => IwaRowFormatter::AGENCY_BLOCK,
            'reference' => static::reference($folder, $first),
            'folderName' => (string) ($folder?->folder_name ?? ''),
            'traveler' => trim((string) ($first['pax_name'] ?? $folder?->lead_traveler_name ?? '')) . '様',
            'startJa' => static::jaDate($start),
            'endJa' => static::jaDate($end),
            'generatedAt' => now()->format('d/m/Y H:i'),
            'fontDir' => resource_path('fonts'),
            'lines' => $rows->map(fn ($r) => [
                'date' => static::jaDate($r['date'] ?? null),
                'detail' => IwaRowFormatter::detailCell($r, $jaNames),
                'dep' => IwaRowFormatter::normalizeTime($r['dep_time'] ?? ''),
                'arr' => IwaRowFormatter::normalizeTime($r['arr_time'] ?? ''),
                'pax' => IwaRowFormatter::passengersCell($r),
            ])->all(),
        ];
    }

    public static function renderHtml(Collection $rows, ?Folder $folder): string
    {
        return view('pdf.iwa-dossier', static::viewData($rows, $folder))->render();
    }

    public static function pdfBinary(Collection $rows, ?Folder $folder): string
    {
        return Pdf::loadHTML(static::renderHtml($rows, $folder))
            ->setPaper('a4', 'portrait')
            ->setOption(['enable_font_subsetting' => true])
            ->output();
    }

    protected static function reference(?Folder $folder, array $first): string
    {
        $ref = trim((string) ($folder?->reference ?? ''));

        return $ref !== '' ? $ref : (string) ($first['folder_id'] ?? $folder?->id ?? '');
    }

    /** IWA_<référence ou id>_<voyageur ASCII>.pdf */
    public static function fileName(string $reference, string $traveler): string
    {
        $clean = fn (string $s) => trim(preg_replace('/[^A-Za-z0-9]+/', '-', Str::ascii($s)), '-');
        $name = $clean($traveler);

        return 'IWA_' . ($clean($reference) ?: 'dossier') . '_' . ($name !== '' ? $name : 'client') . '.pdf';
    }

    /** Un PDF pour un seul dossier, un ZIP de PDF sinon. Le fichier temporaire est supprimé après l'envoi. */
    public static function download(iterable $routes): BinaryFileResponse
    {
        $groups = static::groupByFolder($routes);
        $folders = Folder::whereIn('id', $groups->map(fn ($g) => (int) ($g->first()['folder_id'] ?? 0))->filter()->all())->get()->keyBy('id');

        $files = [];
        foreach ($groups as $rows) {
            $folder = $folders->get((int) ($rows->first()['folder_id'] ?? 0));
            $reference = static::reference($folder, $rows->first());
            $name = static::fileName($reference, trim((string) ($rows->first()['pax_name'] ?? $folder?->lead_traveler_name ?? '')));
            $files[$name] = static::pdfBinary($rows, $folder);
        }

        $path = tempnam(sys_get_temp_dir(), 'iwa');

        if (count($files) === 1) {
            file_put_contents($path, reset($files));

            return response()->download($path, array_key_first($files), ['Content-Type' => 'application/pdf'])->deleteFileAfterSend(true);
        }

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $binary) {
            $zip->addFromString($name, $binary);
        }
        $zip->close();

        return response()->download($path, 'Commande_IWA_' . now()->format('Ymd-Hi') . '.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }
}
