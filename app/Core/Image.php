<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Zmensovani fotek pres GD (JPEG/PNG/WebP). Vraci true pri uspechu.
 */
final class Image
{
    public static function resize(string $src, string $dest, int $maxSize, int $quality = 82): bool
    {
        $info = @getimagesize($src);
        if ($info === false) {
            return false;
        }
        [$w, $h] = $info;
        $type = $info[2];

        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
            IMAGETYPE_PNG => @imagecreatefrompng($src),
            IMAGETYPE_WEBP => @imagecreatefromwebp($src),
            default => false,
        };
        if ($img === false) {
            return false;
        }

        // orientace z EXIF (foceno mobilem)
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($src);
            $img = match ((int)($exif['Orientation'] ?? 1)) {
                3 => imagerotate($img, 180, 0),
                6 => imagerotate($img, -90, 0),
                8 => imagerotate($img, 90, 0),
                default => $img,
            };
            $w = imagesx($img);
            $h = imagesy($img);
        }

        $scale = min(1.0, $maxSize / max($w, $h));
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));

        $out = imagecreatetruecolor($nw, $nh);
        // bile pozadi (PNG/WebP pruhlednost -> JPEG)
        $white = imagecolorallocate($out, 255, 255, 255);
        imagefill($out, 0, 0, $white);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);

        $ok = imagejpeg($out, $dest, $quality);
        imagedestroy($out);
        return $ok;
    }
}
