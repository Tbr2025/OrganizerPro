<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LogoProcessingService
{
    /**
     * Process an uploaded logo: resize to 200x200, apply circular clipping mask, save as PNG.
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

        // Resize to 200x200
        $outputSize = 200;
        $resizedImage = imagecreatetruecolor($outputSize, $outputSize);
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        imagecopyresampled($resizedImage, $squareImage, 0, 0, 0, 0, $outputSize, $outputSize, $size, $size);

        // Apply circular clipping mask
        $circularImage = imagecreatetruecolor($outputSize, $outputSize);
        imagealphablending($circularImage, false);
        imagesavealpha($circularImage, true);

        $transparent = imagecolorallocatealpha($circularImage, 0, 0, 0, 127);
        imagefill($circularImage, 0, 0, $transparent);

        imagealphablending($circularImage, true);

        $center = $outputSize / 2;
        $radius = $outputSize / 2;

        for ($x = 0; $x < $outputSize; $x++) {
            for ($y = 0; $y < $outputSize; $y++) {
                $dist = sqrt(($x - $center) ** 2 + ($y - $center) ** 2);
                if ($dist <= $radius) {
                    $color = imagecolorat($resizedImage, $x, $y);
                    imagesetpixel($circularImage, $x, $y, $color);
                }
            }
        }

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
    public static function processBase64Logo(string $base64Data, string $directory = 'team-logos', ?string $oldPath = null): string
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

        // Resize to 200x200 (already cropped square by Cropper.js)
        $outputSize = 200;
        $resizedImage = imagecreatetruecolor($outputSize, $outputSize);
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $outputSize, $outputSize, $origWidth, $origHeight);

        // Apply circular clipping mask
        $circularImage = imagecreatetruecolor($outputSize, $outputSize);
        imagealphablending($circularImage, false);
        imagesavealpha($circularImage, true);

        $transparent = imagecolorallocatealpha($circularImage, 0, 0, 0, 127);
        imagefill($circularImage, 0, 0, $transparent);
        imagealphablending($circularImage, true);

        $center = $outputSize / 2;
        $radius = $outputSize / 2;

        for ($x = 0; $x < $outputSize; $x++) {
            for ($y = 0; $y < $outputSize; $y++) {
                $dist = sqrt(($x - $center) ** 2 + ($y - $center) ** 2);
                if ($dist <= $radius) {
                    $color = imagecolorat($resizedImage, $x, $y);
                    imagesetpixel($circularImage, $x, $y, $color);
                }
            }
        }

        // Save as PNG
        $filename = uniqid('logo_') . '.png';
        $storagePath = storage_path('app/public/' . $directory);
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0775, true);
        }

        imagepng($circularImage, $storagePath . '/' . $filename, 8);

        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        imagedestroy($circularImage);

        return $directory . '/' . $filename;
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
