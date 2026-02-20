<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Process;

class ImageBackgroundRemovalService
{
    /**
     * Remove background from an image
     * Tries Python rembg first, falls back to GD-based removal
     */
    public function removeBackground(string $imagePath): ?string
    {
        $fullPath = Storage::disk('public')->path($imagePath);

        if (!file_exists($fullPath)) {
            \Log::error("Image file not found: {$fullPath}");
            return null;
        }

        // Generate output path (always PNG for transparency)
        $pathInfo = pathinfo($imagePath);
        $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '-nobg.png';
        $outputFullPath = Storage::disk('public')->path($outputPath);

        // Try Python rembg first (best quality)
        $result = $this->removeBackgroundWithRembg($fullPath, $outputFullPath);

        if ($result) {
            // Delete original file
            Storage::disk('public')->delete($imagePath);
            \Log::info("Background removed with rembg: {$outputPath}");
            return $outputPath;
        }

        // Fallback to GD-based removal
        $result = $this->removeBackgroundWithGD($fullPath, $outputFullPath);

        if ($result) {
            Storage::disk('public')->delete($imagePath);
            \Log::info("Background removed with GD: {$outputPath}");
            return $outputPath;
        }

        \Log::warning("Background removal failed, keeping original image");
        return null;
    }

    /**
     * Remove background using Python rembg library
     * Install: pip install rembg
     */
    protected function removeBackgroundWithRembg(string $inputPath, string $outputPath): bool
    {
        try {
            // Check if rembg is available
            $checkResult = Process::run('rembg --version');

            if (!$checkResult->successful()) {
                // Try with python -m
                $checkResult = Process::run('python -m rembg.cli --version');

                if (!$checkResult->successful()) {
                    \Log::info('rembg not installed. Install with: pip install rembg[cli]');
                    return false;
                }

                // Use python -m format
                $result = Process::timeout(120)->run([
                    'python', '-m', 'rembg.cli', 'i', $inputPath, $outputPath
                ]);
            } else {
                // Use rembg directly
                $result = Process::timeout(120)->run([
                    'rembg', 'i', $inputPath, $outputPath
                ]);
            }

            return $result->successful() && file_exists($outputPath);
        } catch (\Exception $e) {
            \Log::error('rembg error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove background using GD library
     * Works best with solid color backgrounds (white, green screen, etc.)
     */
    protected function removeBackgroundWithGD(string $inputPath, string $outputPath): bool
    {
        try {
            $imageInfo = @getimagesize($inputPath);
            if (!$imageInfo) {
                return false;
            }

            $mime = $imageInfo['mime'];
            $width = $imageInfo[0];
            $height = $imageInfo[1];

            // Load source image
            $srcImage = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($inputPath),
                'image/png' => @imagecreatefrompng($inputPath),
                'image/gif' => @imagecreatefromgif($inputPath),
                'image/webp' => @imagecreatefromwebp($inputPath),
                default => null,
            };

            if (!$srcImage) {
                return false;
            }

            // Create output image with alpha channel
            $newImage = imagecreatetruecolor($width, $height);
            imagesavealpha($newImage, true);
            imagealphablending($newImage, false);
            $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
            imagefill($newImage, 0, 0, $transparent);

            // Sample corners to detect background color
            $bgColor = $this->detectBackgroundColor($srcImage, $width, $height);

            // Flood fill from corners to remove background
            $this->floodFillRemove($srcImage, $newImage, $width, $height, $bgColor);

            // Save as PNG
            $result = imagepng($newImage, $outputPath, 9);

            // Cleanup
            imagedestroy($srcImage);
            imagedestroy($newImage);

            return $result && file_exists($outputPath);
        } catch (\Exception $e) {
            \Log::error('GD background removal error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Detect the most likely background color by sampling corners
     */
    protected function detectBackgroundColor(\GdImage $image, int $width, int $height): array
    {
        $samples = [];
        $sampleSize = 10;

        // Sample from all four corners
        $corners = [
            [0, 0],
            [$width - $sampleSize, 0],
            [0, $height - $sampleSize],
            [$width - $sampleSize, $height - $sampleSize],
        ];

        foreach ($corners as [$startX, $startY]) {
            for ($x = $startX; $x < $startX + $sampleSize && $x < $width; $x++) {
                for ($y = $startY; $y < $startY + $sampleSize && $y < $height; $y++) {
                    $rgb = imagecolorat($image, $x, $y);
                    $key = $rgb;
                    $samples[$key] = ($samples[$key] ?? 0) + 1;
                }
            }
        }

        // Get most common color
        arsort($samples);
        $mostCommon = array_key_first($samples);

        return [
            'r' => ($mostCommon >> 16) & 0xFF,
            'g' => ($mostCommon >> 8) & 0xFF,
            'b' => $mostCommon & 0xFF,
        ];
    }

    /**
     * Remove background using flood fill algorithm from edges
     */
    protected function floodFillRemove(\GdImage $src, \GdImage $dest, int $width, int $height, array $bgColor): void
    {
        $tolerance = 35; // Color tolerance for background detection
        $visited = [];

        // Copy all pixels first
        imagealphablending($dest, true);
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($src, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $color = imagecolorallocate($dest, $r, $g, $b);
                imagesetpixel($dest, $x, $y, $color);
            }
        }
        imagealphablending($dest, false);

        // Flood fill from edges
        $queue = new \SplQueue();

        // Add edge pixels to queue
        for ($x = 0; $x < $width; $x++) {
            $queue->enqueue([$x, 0]);
            $queue->enqueue([$x, $height - 1]);
        }
        for ($y = 0; $y < $height; $y++) {
            $queue->enqueue([0, $y]);
            $queue->enqueue([$width - 1, $y]);
        }

        $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
        $directions = [[0, 1], [0, -1], [1, 0], [-1, 0]];

        while (!$queue->isEmpty()) {
            [$x, $y] = $queue->dequeue();
            $key = "{$x},{$y}";

            if (isset($visited[$key]) || $x < 0 || $x >= $width || $y < 0 || $y >= $height) {
                continue;
            }

            $visited[$key] = true;

            $rgb = imagecolorat($src, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            // Check if this pixel is similar to background
            $diff = abs($r - $bgColor['r']) + abs($g - $bgColor['g']) + abs($b - $bgColor['b']);

            if ($diff <= $tolerance * 3) {
                // Make transparent
                imagesetpixel($dest, $x, $y, $transparent);

                // Add neighbors to queue
                foreach ($directions as [$dx, $dy]) {
                    $nx = $x + $dx;
                    $ny = $y + $dy;
                    if (!isset($visited["{$nx},{$ny}"])) {
                        $queue->enqueue([$nx, $ny]);
                    }
                }
            }
        }
    }
}
