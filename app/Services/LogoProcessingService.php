<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LogoProcessingService
{
    /**
     * Widest a stored logo needs to be for any poster slot.
     *
     * Both entry points used to hard-resize every logo to 200x200. That is a
     * target, not a cap, so it did the damage in both directions: a 1000px
     * crest was thrown away down to 200px, and a 120px one was blown up to
     * 200px. Poster team/tournament logo slots run 200-400px against a
     * 1080-wide canvas, so 200px stored artwork was being enlarged again at
     * render time — the same mistake PlayerImageService was written to stop
     * for player photos.
     */
    public const MAX_SIZE = 600;

    /**
     * Process an uploaded logo: cap at MAX_SIZE, apply circular clipping mask, save as PNG.
     *
     * @param UploadedFile $file The uploaded logo file
     * @param string $directory The storage directory (e.g., 'team-logos')
     * @param string|null $oldPath Previous logo path to delete
     * @return string The stored file path relative to the public disk
     */
    public static function processLogo(UploadedFile $file, string $directory = 'team-logos', ?string $oldPath = null): string
    {
        // Delete old logo if exists
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        // Load source image using GD
        $sourceImage = self::createImageFromFile($file->getPathname());
        if (!$sourceImage) {
            // Fallback: store without processing
            return $file->store($directory, 'public');
        }

        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);

        // Center-crop to square
        $size = min($origWidth, $origHeight);
        $cropX = (int)(($origWidth - $size) / 2);
        $cropY = (int)(($origHeight - $size) / 2);

        $squareImage = imagecreatetruecolor($size, $size);
        imagealphablending($squareImage, false);
        imagesavealpha($squareImage, true);
        imagecopyresampled($squareImage, $sourceImage, 0, 0, $cropX, $cropY, $size, $size, $size, $size);

        // Shrink only — never enlarge a small crest to reach MAX_SIZE.
        $outputSize = min(self::MAX_SIZE, $size);
        $resizedImage = imagecreatetruecolor($outputSize, $outputSize);
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        imagecopyresampled($resizedImage, $squareImage, 0, 0, 0, 0, $outputSize, $outputSize, $size, $size);

        $circularImage = self::circularMask($resizedImage, $outputSize);

        // Save as PNG
        $filename = uniqid('logo_') . '.png';
        $storagePath = storage_path('app/public/' . $directory);

        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0775, true);
        }

        $fullPath = $storagePath . '/' . $filename;
        imagepng($circularImage, $fullPath, 8);

        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($squareImage);
        imagedestroy($resizedImage);
        imagedestroy($circularImage);

        return $directory . '/' . $filename;
    }

    /**
     * Process a base64-encoded cropped logo (from Cropper.js).
     */
    public static function processBase64Logo(string $base64Data, string $directory = 'team-logos', ?string $oldPath = null, bool $circular = true): string
    {
        // Delete old logo if exists
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        // Decode base64
        $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $base64Data);
        $imageData = base64_decode($imageData);
        if (!$imageData) {
            throw new \RuntimeException('Failed to decode base64 logo data.');
        }

        // Save to temp file and process
        $tempPath = tempnam(sys_get_temp_dir(), 'logo_');
        file_put_contents($tempPath, $imageData);

        $sourceImage = @imagecreatefrompng($tempPath);
        if (!$sourceImage) {
            $sourceImage = @imagecreatefromjpeg($tempPath);
        }
        @unlink($tempPath);

        if (!$sourceImage) {
            throw new \RuntimeException('Failed to create image from cropped data.');
        }

        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);

        // Save as PNG (already cropped by Cropper.js)
        $filename = uniqid('logo_') . '.png';
        $storagePath = storage_path('app/public/' . $directory);
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0775, true);
        }

        if ($circular) {
            // Square off for the mask, capped at MAX_SIZE and never enlarged.
            $outputSize = min(self::MAX_SIZE, min($origWidth, $origHeight));
            $resizedImage = imagecreatetruecolor($outputSize, $outputSize);
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $outputSize, $outputSize, $origWidth, $origHeight);

            $circularImage = self::circularMask($resizedImage, $outputSize);

            imagepng($circularImage, $storagePath . '/' . $filename, 8);
            imagedestroy($resizedImage);
            imagedestroy($circularImage);
        } else {
            // Save directly (already cropped/resized by Cropper.js)
            imagepng($sourceImage, $storagePath . '/' . $filename, 8);
        }

        imagedestroy($sourceImage);

        return $directory . '/' . $filename;
    }

    /**
     * Clip a square image to a circle, with a feathered edge.
     *
     * The two callers each had their own copy of this loop, and both used a
     * hard `dist <= radius` test — a binary cut that leaves a visibly stepped
     * circumference once the logo is drawn onto a 1080-wide poster. Ramping
     * alpha across the last pixel of the radius costs nothing and is the
     * difference between a clean crest and a jagged one.
     */
    private static function circularMask(\GdImage $square, int $size): \GdImage
    {
        $circular = imagecreatetruecolor($size, $size);
        imagealphablending($circular, false);
        imagesavealpha($circular, true);
        imagefill($circular, 0, 0, imagecolorallocatealpha($circular, 0, 0, 0, 127));

        $center = ($size - 1) / 2;
        $radius = $size / 2;

        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y < $size; $y++) {
                $dist = sqrt(($x - $center) ** 2 + ($y - $center) ** 2);

                if ($dist > $radius) {
                    continue;
                }

                $color = imagecolorat($square, $x, $y);

                // Inside the circle but within a pixel of the edge: blend the
                // source alpha towards fully transparent instead of stopping dead.
                if ($dist > $radius - 1) {
                    $srcAlpha = ($color >> 24) & 0x7F;
                    $coverage = $radius - $dist; // 0 at the rim, 1 a pixel in
                    $alpha = (int) round($srcAlpha + ((127 - $srcAlpha) * (1 - $coverage)));
                    $color = ($color & 0x00FFFFFF) | (min(127, max(0, $alpha)) << 24);
                }

                imagesetpixel($circular, $x, $y, $color);
            }
        }

        return $circular;
    }

    private static function createImageFromFile(string $path): ?\GdImage
    {
        $info = @getimagesize($path);
        if (!$info) {
            return null;
        }

        return match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => null,
        };
    }
}
