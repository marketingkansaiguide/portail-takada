<?php

namespace App\Console\Commands;

use App\Models\BusStation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportBusStations extends Command
{
    protected $signature = 'import:bus-stations {file=bus_stations.csv}';
    protected $description = 'Importe la liste des stations de bus depuis un fichier CSV';

    public function handle()
    {
        $filePath = base_path($this->argument('file'));

        if (!file_exists($filePath)) {
            $this->error("Fichier introuvable : {$filePath}");
            return Command::FAILURE;
        }

        $file = fopen($filePath, 'r');
        if ($file === false) {
            $this->error("Impossible d'ouvrir le fichier : {$filePath}");
            return Command::FAILURE;
        }

        // Sauter la ligne d'en-tête
        fgetcsv($file);

        $count = 0;
        $this->info("Début de l'importation des stations de bus...");

        DB::transaction(function () use ($file, &$count) {
            while (($data = fgetcsv($file)) !== false) {
                if (isset($data[0]) && trim($data[0]) !== '') {
                    BusStation::updateOrCreate(
                        [
                            'name_en' => trim($data[0]),
                        ],
                        [
                            'name_ja' => trim($data[1] ?? ''),
                            'address' => trim($data[2] ?? ''),
                            'google_maps_url' => trim($data[3] ?? ''),
                        ]
                    );
                    $count++;
                }
            }
        });

        fclose($file);
        $this->info("{$count} stations de bus importées ou mises à jour avec succès !");

        return Command::SUCCESS;
    }
}