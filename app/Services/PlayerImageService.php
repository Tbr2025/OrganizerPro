<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Sizing rules for player / captain photos, in one place.
 *
 * Poster slots are laid out against a 1080x1350 canvas, and a full-bleed player
 * cut-out fills most of that height — so the renderer asks for artwork around
 * 1000x1350. Every upload path used to squash photos to 425px wide before
 * storing them, which meant the renderer had to upscale by ~2.5x and every
 * generated poster showed a soft, mushy player. These caps keep enough pixels
 * for the largest slot without storing full 12MP phone photos.
 */
class PlayerImageService
{
    /**
     * Widest a stored player photo needs to be for any poster slot.
     *
     * Raised from 1200x1600 to match the ceiling the croppers already allow.
     * 1200 left almost no headroom: an award-poster player slot is ~900-1000px
     * wide, so a capped photo was downscaled by barely 1.2x, and a downscale
     * that small gains little of the detail-per-pixel that makes a face read
     * as sharp. At 1600 the same slot gets a ~1.7x reduction, which is where
     * the resample plus sharpenImage() actually looks crisp.
     */
    public const MAX_WIDTH = 1600;

    /** Tallest a stored player photo needs to be for any poster slot. */
    public const MAX_HEIGHT = 2133;

    /**
     * Shrink a PNG/JPEG on disk so it fits within the given box, preserving
     * aspect ratio and transparency.
     *
     * Only ever downscales. The old callers multiplied every image up to a
     * fixed 425px width, which *enlarged* small photos — adding bytes and
     * blur without adding detail.
     *
     * @param  string  $fullPath  Absolute path; the file is rewritten in place as PNG.
     * @return bool  true when the file was rewritten, false when left untouched.
     */
    public function capSize(string $fullPath, int $maxWidth = self::MAX_WIDTH, int $maxHeight = self::MAX_HEIGHT): bool
    {
        try {
            $info = @getimagesize($fullPath);
            if (! $info) {
                return false;
            }

            $origWidth = $info[0];
            $origHeight = $info[1];

            $source = match ($info['mime'] ?? '') {
                'image/png' => @imagecreatefrompng($fullPath),
                'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($fullPath),
                'image/webp' => @imagecreatefromwebp($fullPath),
                default => null,
            };

            if (! $source) {
                return false;
            }

            // Already small enough — leave it alone rather than re-encoding,
            // which would only cost a resample pass and lose sharpness.
            if ($origWidth <= $maxWidth && $origHeight <= $maxHeight) {
                imagedestroy($source);

                return false;
            }

            $scale = min($maxWidth / $origWidth, $maxHeight / $origHeight);
            $newWidth = max(1, (int) round($origWidth * $scale));
            $newHeight = max(1, (int) round($origHeight * $scale));

            $resized = $this->downscale($source, $newWidth, $newHeight);
            imagedestroy($source);

            // Max zlib level: these are written once on upload and then served
            // to every poster render, so the one-off CPU cost is worth the bytes
            // saved on a disk holding thousands of player cut-outs.
            $written = imagepng($resized, $fullPath, 9);
            imagedestroy($resized);

            return (bool) $written;
        } catch (\Throwable $e) {
            Log::warning("PlayerImageService::capSize failed for {$fullPath}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Resample onto a transparent canvas of the requested size.
     *
     * Thin wrapper over imagecopyresampled whose only job is getting the alpha
     * flags right — every hand-rolled copy of this in the poster services had
     * to remember imagealphablending(false) + imagesavealpha(true) + a
     * transparent fill, and a cut-out rendered against the wrong flags picks up
     * a black fringe.
     *
     * Deliberately a single resample: GD's imagecopyresampled already area-
     * averages over the whole source rectangle when shrinking, so pre-halving
     * the image adds a resample pass (and its rounding) for no measurable gain.
     * Sharpness lost to the resample is recovered by the unsharp pass in
     * PosterGeneratorService::sharpenImage() instead.
     */
    public function downscale(\GdImage $source, int $targetWidth, int $targetHeight): \GdImage
    {
        $final = $this->blankCanvas($targetWidth, $targetHeight);
        imagecopyresampled(
            $final,
            $source,
            0, 0, 0, 0,
            $targetWidth, $targetHeight,
            imagesx($source), imagesy($source)
        );

        return $final;
    }

    /**
     * Mirror an image horizontally (or vertically), preserving alpha.
     *
     * Used by poster generation so a player photographed facing left can be
     * placed on the right-hand side of a head-to-head poster.
     */
    public function flip(\GdImage $image, bool $horizontal = true, bool $vertical = false): void
    {
        if (! $horizontal && ! $vertical) {
            return;
        }

        $mode = match (true) {
            $horizontal && $vertical => IMG_FLIP_BOTH,
            $vertical => IMG_FLIP_VERTICAL,
            default => IMG_FLIP_HORIZONTAL,
        };

        imageflip($image, $mode);
    }

    /** A transparent truecolour canvas with alpha saving switched on. */
    protected function blankCanvas(int $width, int $height): \GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        return $canvas;
    }
}
