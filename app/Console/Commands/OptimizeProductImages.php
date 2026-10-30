<?php

namespace App\Console\Commands;

use App\Support\ImageOptimizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OptimizeProductImages extends Command
{
    protected $signature = 'images:optimize {--dir=* : Dossiers du disque public (défaut : products, variants)} {--dry-run}';

    protected $description = 'Recompresse sur place les images déjà en ligne (largeur max 1920px, JPEG q82). Faire une sauvegarde avant.';

    public function handle(): int
    {
        $dirs = $this->option('dir') ?: ['products', 'variants'];
        $disk = Storage::disk('public');
        $before = $after = $count = 0;

        foreach ($dirs as $dir) {
            foreach ($disk->allFiles($dir) as $rel) {
                $abs = $disk->path($rel);
                $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
                if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                    continue;
                }

                $size = filesize($abs);
                $result = ImageOptimizer::optimizeFile($abs);
                // on garde le même nom/format pour ne pas toucher à la base
                if (! $result) {
                    continue;
                }
                [$data, $newExt] = $result;
                $sameFormat = $newExt === ($ext === 'png' ? 'png' : 'jpg');
                if (! $sameFormat || strlen($data) >= $size * 0.95) {
                    continue; // pas de gain réel ou changement de format
                }

                $before += $size;
                $after += strlen($data);
                $count++;
                if (! $this->option('dry-run')) {
                    file_put_contents($abs, $data);
                }
                $this->line(sprintf('%s : %s -> %s', $rel, number_format($size / 1024) . ' Ko', number_format(strlen($data) / 1024) . ' Ko'));
            }
        }

        $this->info(sprintf('%d image(s), %s Mo -> %s Mo', $count, number_format($before / 1048576, 1), number_format($after / 1048576, 1)));

        return self::SUCCESS;
    }
}
