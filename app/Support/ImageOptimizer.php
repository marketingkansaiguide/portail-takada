<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Redimensionne et recompresse les images (GD) : largeur max 1920px,
 * JPEG qualité 82. Les PNG transparents restent en PNG.
 */
class ImageOptimizer
{
    public const MAX_WIDTH = 1920;
    public const QUALITY = 82;

    /**
     * Optimise un fichier image vers un blob binaire.
     * Retourne [binaire, extension] ou null si le fichier n'est pas traitable.
     */
    public static function optimizeFile(string $path): ?array
    {
        if (! function_exists('imagecreatefromstring') || ! is_file($path)) {
            return null;
        }

        $info = @getimagesize($path);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null; // GIF/SVG/autres : on ne touche pas
        }

        $img = @imagecreatefromstring((string) file_get_contents($path));
        if (! $img) {
            return null;
        }

        // Orientation EXIF (photos de téléphone)
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = @exif_read_data($path)['Orientation'] ?? 1;
            $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
            if ($angle) {
                $rotated = imagerotate($img, $angle, 0);
                if ($rotated) {
                    $img = $rotated;
                }
            }
        }

        $w = imagesx($img);
        $h = imagesy($img);
        if ($w > self::MAX_WIDTH) {
            $scaled = imagescale($img, self::MAX_WIDTH, (int) round($h * self::MAX_WIDTH / $w), IMG_BICUBIC);
            if ($scaled) {
                $img = $scaled;
            }
        }

        $hasAlpha = $info[2] !== IMAGETYPE_JPEG && self::hasAlpha($img);

        ob_start();
        if ($hasAlpha) {
            imagesavealpha($img, true);
            imagepng($img, null, 9);
            $ext = 'png';
        } else {
            // fond blanc pour aplatir d'éventuelles zones transparentes
            $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
            imageinterlace($flat, true);
            imagejpeg($flat, null, self::QUALITY);
            $ext = 'jpg';
        }
        $data = ob_get_clean();

        return [$data, $ext];
    }

    /** Stocke un upload Filament optimisé sur le disque, retourne le chemin relatif. */
    public static function storeUpload(UploadedFile $file, string $disk, string $directory): string
    {
        $result = self::optimizeFile($file->getRealPath());
        $base = pathinfo($file->hashName(), PATHINFO_FILENAME);

        if ($result === null) {
            return $file->storeAs($directory, $file->hashName(), ['disk' => $disk, 'visibility' => 'public']);
        }

        [$data, $ext] = $result;
        $path = trim($directory, '/') . '/' . $base . '.' . $ext;
        Storage::disk($disk)->put($path, $data, 'public');

        return $path;
    }

    /**
     * URL d'une miniature nette (largeur $width, jamais agrandie), générée une
     * fois puis mise en cache sur le disque public. Retombe sur l'original.
     */
    public static function thumbUrl(string $path, int $width = 640): string
    {
        $path = ltrim($path, '/');
        $disk = Storage::disk('public');
        $thumb = 'thumbs/' . $width . '/' . md5($path) . '.jpg';

        try {
            if (! $disk->exists($thumb)) {
                $src = $disk->path($path);
                $info = is_file($src) ? @getimagesize($src) : false;
                if (! $info || $info[0] <= $width || ! class_exists(\Imagick::class)) {
                    return asset('storage/' . $path);
                }

                $im = new \Imagick($src);
                $im->setIteratorIndex(0);
                $im->autoOrient();
                $im->setImageBackgroundColor('white');
                $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $im->resizeImage($width, 0, \Imagick::FILTER_LANCZOS, 1);
                $im->unsharpMaskImage(0, 0.6, 0.7, 0.02);
                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality(88);
                $im->setInterlaceScheme(\Imagick::INTERLACE_PLANE);
                $im->stripImage();
                $disk->put($thumb, $im->getImageBlob(), 'public');
                $im->clear();
            }
        } catch (\Throwable) {
            return asset('storage/' . $path);
        }

        return asset('storage/' . $thumb);
    }

    private static function hasAlpha($img): bool
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $stepX = max(1, intdiv($w, 60));
        $stepY = max(1, intdiv($h, 60));
        for ($x = 0; $x < $w; $x += $stepX) {
            for ($y = 0; $y < $h; $y += $stepY) {
                if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
