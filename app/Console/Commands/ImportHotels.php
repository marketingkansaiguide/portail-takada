<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use Illuminate\Console\Command;

class ImportHotels extends Command
{
    protected $signature = 'import:hotels {file=hotels.csv}';
    protected $description = 'Importe la liste des hôtels depuis un fichier CSV';

    public function handle()
    {
        $filePath = base_path($this->argument('file'));

        if (!file_exists($filePath)) {
            $this->error("Fichier introuvable : {$filePath}");
            return Command::FAILURE;
        }

        $file = fopen($filePath, 'r');
        fgetcsv($file); // Sauter la ligne d'en-tête

        $count = 0;
        while (($data = fgetcsv($file)) !== false) {
            if (!empty($data[0])) {
                Hotel::updateOrCreate(
                    ['name' => trim($data[0])],
                    [
                        'phone' => trim($data[1] ?? ''),
                        'google_maps_url' => trim($data[2] ?? ''),
                        'address' => trim($data[3] ?? ''),
                    ]
                );
                $count++;
            }
        }

        fclose($file);
        $this->info("{$count} hôtels importés ou mis à jour avec succès !");

        return Command::SUCCESS;
    }
}