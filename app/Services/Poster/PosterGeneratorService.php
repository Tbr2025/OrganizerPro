<?php

namespace App\Services\Poster;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class PosterGeneratorService
{
    protected string $outputDirectory = 'generated_posters';
    protected int $defaultWidth = 1080;
    protected int $defaultHeight = 1080;

    /**
     * Create a blank canvas with background color
     */
    protected function createCanvas(int $width, int $height, string $bgColor = '#000000'): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);

        $rgb = $this->hexToRgb($bgColor);
        $color = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
        imagefill($image, 0, 0, $color);

        return $image;
    }

    /**
     * Load background image from path
     */
    protected function loadBackground(string $path): ?\GdImage
    {
        $fullPath = Storage::disk('public')->path($path);

        if (!file_exists($fullPath)) {
            return null;
        }

        $info = getimagesize($fullPath);
        $mime = $info['mime'] ?? '';

        return match ($mime) {
            'image/png' => imagecreatefrompng($fullPath),
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($fullPath),
            default => null,
        };
    }

    /**
     * Add text to image
     */
    protected function addText(
        \GdImage $image,
        string $text,
        int $x,
        int $y,
        int $size = 24,
        string $color = '#FFFFFF',
        string $fontFile = 'Montserrat-Bold.ttf',
        string $align = 'left',
        float $angle = 0
    ): void {
        $fontPath = public_path('fonts/' . $fontFile);

        if (!file_exists($fontPath)) {
            // Fallback to Oswald-Bold or Montserrat-Medium
            $fontPath = public_path('fonts/Oswald-Bold.ttf');
            if (!file_exists($fontPath)) {
                $fontPath = public_path('fonts/Montserrat-Medium.ttf');
            }
        }

        $rgb = $this->hexToRgb($color);
        $textColor = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);

        // Calculate text bounds for alignment
        $bbox = imagettfbbox($size, $angle, $fontPath, $text);
        $textWidth = abs($bbox[4] - $bbox[0]);

        // Adjust x based on alignment
        $adjustedX = match ($align) {
            'center' => $x - ($textWidth / 2),
            'right' => $x - $textWidth,
            default => $x,
        };

        imagettftext($image, $size, $angle, (int) $adjustedX, $y, $textColor, $fontPath, $text);
    }

    /**
     * Overlay an image on the canvas
     */
    protected function addImage(
        \GdImage $canvas,
        string $imagePath,
        int $x,
        int $y,
        ?int $width = null,
        ?int $height = null
    ): void {
        $overlayImage = $this->loadBackground($imagePath);

        if (!$overlayImage) {
            return;
        }

        $srcWidth = imagesx($overlayImage);
        $srcHeight = imagesy($overlayImage);

        // Calculate dimensions maintaining aspect ratio
        if ($width && !$height) {
            $height = (int) ($srcHeight * ($width / $srcWidth));
        } elseif ($height && !$width) {
            $width = (int) ($srcWidth * ($height / $srcHeight));
        } elseif (!$width && !$height) {
            $width = $srcWidth;
            $height = $srcHeight;
        }

        // Create resized image
        $resized = imagecreatetruecolor($width, $height);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        imagecopyresampled(
            $resized,
            $overlayImage,
            0, 0, 0, 0,
            $width, $height,
            $srcWidth, $srcHeight
        );

        // Copy to canvas
        imagecopy($canvas, $resized, $x, $y, 0, 0, $width, $height);

        imagedestroy($overlayImage);
        imagedestroy($resized);
    }

    /**
     * Add circular image (for logos, player photos)
     */
    protected function addCircularImage(
        \GdImage $canvas,
        string $imagePath,
        int $centerX,
        int $centerY,
        int $diameter
    ): void {
        $sourceImage = $this->loadBackground($imagePath);

        if (!$sourceImage) {
            return;
        }

        $srcWidth = imagesx($sourceImage);
        $srcHeight = imagesy($sourceImage);

        // Create circular mask
        $circle = imagecreatetruecolor($diameter, $diameter);
        imagealphablending($circle, false);
        imagesavealpha($circle, true);
        $transparent = imagecolorallocatealpha($circle, 0, 0, 0, 127);
        imagefill($circle, 0, 0, $transparent);

        // Resize source to fit
        $resized = imagecreatetruecolor($diameter, $diameter);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        imagecopyresampled(
            $resized,
            $sourceImage,
            0, 0, 0, 0,
            $diameter, $diameter,
            $srcWidth, $srcHeight
        );

        // Apply circular mask
        for ($x = 0; $x < $diameter; $x++) {
            for ($y = 0; $y < $diameter; $y++) {
                $dx = $x - $diameter / 2;
                $dy = $y - $diameter / 2;
                if ($dx * $dx + $dy * $dy <= ($diameter / 2) * ($diameter / 2)) {
                    $color = imagecolorat($resized, $x, $y);
                    imagesetpixel($circle, $x, $y, $color);
                }
            }
        }

        // Copy to canvas
        $x = $centerX - $diameter / 2;
        $y = $centerY - $diameter / 2;
        imagecopy($canvas, $circle, (int) $x, (int) $y, 0, 0, $diameter, $diameter);

        imagedestroy($sourceImage);
        imagedestroy($resized);
        imagedestroy($circle);
    }

    /**
     * Save image to storage
     */
    protected function saveImage(\GdImage $image, string $filename): string
    {
        $path = $this->outputDirectory . '/' . $filename;
        $fullPath = Storage::disk('public')->path($path);

        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        imagepng($image, $fullPath);
        imagedestroy($image);

        return $path;
    }

    /**
     * Convert hex color to RGB
     */
    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Generate unique filename
     */
    protected function generateFilename(string $prefix = 'poster'): string
    {
        return $prefix . '-' . Str::uuid() . '.png';
    }

    /**
     * Abstract method to generate poster
     */
    abstract public function generate($model): string;
}
