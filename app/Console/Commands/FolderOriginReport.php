<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lecture seule. À lancer avant et après le déploiement du lot 2 :
 * les dossiers visibles par chaque compte agence doivent être identiques.
 */
class FolderOriginReport extends Command
{
    protected $signature = 'folders:origin-report';

    protected $description = 'Affiche, sans rien modifier, la répartition des dossiers par origine et les dossiers visibles par chaque compte agence';

    public function handle(): int
    {
        $hasOrigin = Schema::hasColumn('folders', 'origin');

        $this->info('Dossiers : ' . DB::table('folders')->count() . ' · sans agence propriétaire : ' . DB::table('folders')->whereNull('agency_id')->count());

        if ($hasOrigin) {
            $this->table(['Origine', 'Dossiers'], DB::table('folders')->selectRaw('origin, COUNT(*) total')->groupBy('origin')->get()->map(fn ($r) => [$r->origin, $r->total])->all());
        }

        $rows = DB::table('users')
            ->where('role', 'agency')
            ->orderBy('id')
            ->get(['id', 'email', 'agency_id'])
            ->map(function ($user) use ($hasOrigin) {
                $ids = DB::table('folders')
                    ->where('agency_id', $user->agency_id)
                    ->when($hasOrigin, fn ($q) => $q->where('origin', 'b2b'))
                    ->orderBy('id')
                    ->pluck('id')
                    ->implode(',');

                return [$user->id, $user->agency_id, $ids ?: '—', md5($ids)];
            })->all();

        $this->table(['Compte agence', 'Agence', 'Dossiers visibles', 'Empreinte'], $rows);

        return self::SUCCESS;
    }
}
