<?php

namespace App\Console\Commands;

use App\Models\TrainStation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportTrainStations extends Command
{
    protected $signature = 'import:train-stations {file=train_stations.csv}';
    protected $description = 'Importe la liste des stations de train depuis un fichier CSV';

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
        $this->info("Début de l'importation des stations de train...");

        DB::transaction(function () use ($file, &$count) {
            while (($data = fgetcsv($file)) !== false) {
                if (isset($data[1]) && trim($data[1]) !== '') {
                    TrainStation::updateOrCreate(
                        [
                            'name_en' => trim($data[1]),
                            'prefecture' => trim($data[5] ?? ''),
                            'name_ja' => trim($data[2] ?? ''),
                        ],
                        [
                            'name_kana' => trim($data[3] ?? ''),
                            'category' => trim($data[4] ?? ''),
                            'address' => trim($data[6] ?? ''),
                            'google_maps_url' => trim($data[7] ?? ''),
                        ]
                    );
                    $count++;
                }
            }
        });

        fclose($file);
        $this->info("{$count} stations de train importées ou mises à jour avec succès !");

        return Command::SUCCESS;
    }
}