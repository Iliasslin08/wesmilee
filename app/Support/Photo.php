<?php

namespace App\Support;

/** Photos et scans : réduits à 1800 px de large (JPEG qualité 80) pour prendre moins de place, en restant lisibles. */
class Photo
{
    public const LARGEUR_MAX = 1800;

    /** Réduit une photo à 1800 px de large maximum (JPEG qualité 80). Renvoie null pour un PDF, une image illisible ou si GD manque. */
    public static function reduire(string $source): ?string
    {
        if (! function_exists('imagecreatefromjpeg') || ! ($info = @getimagesize($source)) || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null;
        }
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };
        if (! $img) {
            return null;
        }
        [$l, $h] = [$info[0], $info[1]];
        if ($l > self::LARGEUR_MAX) {
            $nl = self::LARGEUR_MAX;
            $nh = (int) round($h * $nl / $l);
            $petite = imagecreatetruecolor($nl, $nh);
            imagefill($petite, 0, 0, imagecolorallocate($petite, 255, 255, 255));
            imagecopyresampled($petite, $img, 0, 0, 0, 0, $nl, $nh, $l, $h);
            imagedestroy($img);
            $img = $petite;
        }
        ob_start();
        imagejpeg($img, null, 80);
        imagedestroy($img);
        $jpeg = ob_get_clean();

        // Ne garder la version réduite que si elle est vraiment plus légère
        return strlen($jpeg) < filesize($source) ? $jpeg : file_get_contents($source);
    }
}
