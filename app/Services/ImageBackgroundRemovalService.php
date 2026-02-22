<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Http;

class ImageBackgroundRemovalService
{
    /**
     * Remove background from an image
     * Tries multiple methods in order:
     * 1. Python rembg (if installed)
     * 2. remove.bg API (if API key configured)
     * 3. GD-based removal (fallback for simple backgrounds)
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

        // Try Python rembg first (best quality, free)
        $result = $this->removeBackgroundWithRembg($fullPath, $outputFullPath);
        if ($result) {
            Storage::disk('public')->delete($imagePath);
            \Log::info("Background removed with rembg: {$outputPath}");
            return $outputPath;
        }

        // Try remove.bg API (if configured)
        $apiKey = config('services.removebg.api_key');
        if ($apiKey) {
            $result = $this->removeBackgroundWithAPI($fullPath, $outputFullPath, $apiKey);
            if ($result) {
                Storage::disk('public')->delete($imagePath);
                \Log::info("Background removed with remove.bg API: {$outputPath}");
                return $outputPath;
            }
        }

        // Fallback to GD-based removal (works for solid color backgrounds)
        $result = $this->removeBackgroundWithGD($fullPath, $outputFullPath);
        if ($result) {
            Storage::disk('public')->delete($imagePath);
            \Log::info("Background removed with GD: {$outputPath}");
            return $outputPath;
        }

        \Log::warning("Background removal failed, keeping original image: {$imagePath}");
        return null;
    }

    /**
     * Remove background using remove.bg API
     */
    protected function removeBackgroundWithAPI(string $inputPath, string $outputPath, string $apiKey): bool
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders(['X-Api-Key' => $apiKey])
                ->attach('image_file', file_get_contents($inputPath), basename($inputPath))
                ->post('https://api.remove.bg/v1.0/removebg', [
                    'size' => 'auto',
                    'format' => 'png',
                ]);

            if ($response->successful()) {
                file_put_contents($outputPath, $response->body());
                return file_exists($outputPath) && filesize($outputPath) > 0;
            }

            \Log::warning('remove.bg API error: ' . $response->body());
            return false;
        } catch (\Exception $e) {
            \Log::error('remove.bg API error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove background using Python rembg library
     * Install: pip install rembg[cpu]
     */
    protected function removeBackgroundWithRembg(string $inputPath, string $outputPath): bool
    {
        try {
            // Set environment variables for www-data user
            $env = [
                'HOME' => '/var/www',
                'NUMBA_CACHE_DIR' => '/var/www/.cache/numba',
                'PATH' => '/usr/local/bin:/usr/bin:/bin',
            ];

            // Check if rembg is available
            $checkResult = Process::env($env)->run('rembg --version 2>&1');

            if (!$checkResult->successful() || !str_contains($checkResult->output(), 'rembg')) {
                // Try with python3 -m
                $checkResult = Process::env($env)->run('python3 -m rembg.cli --version 2>&1');

                if (!$checkResult->successful()) {
                    \Log::info('rembg not installed. Install with: pip3 install rembg[cpu]');
                    return false;
                }

                // Use python3 -m format
                $result = Process::env($env)->timeout(180)->run([
                    'python3', '-m', 'rembg.cli', 'i', $inputPath, $outputPath
                ]);
            } else {
                // Use rembg directly
                \Log::info('Running rembg background removal...');
                $result = Process::env($env)->timeout(180)->run([
                    'rembg', 'i', $inputPath, $outputPath
                ]);
            }

            if ($result->successful() && file_exists($outputPath)) {
                \Log::info('rembg completed successfully');
                return true;
            }

            \Log::warning('rembg failed: ' . $result->errorOutput());
            return false;
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
                \Log::warning('GD: Could not get image info');
                return false;
            }

            $mime = $imageInfo['mime'];
            $width = $imageInfo[0];
            $height = $imageInfo[1];

            // Skip very large images to avoid memory issues
            if ($width * $height > 4000000) { // ~4MP limit
                \Log::warning('GD: Image too large for background removal');
                return false;
            }

            // Load source image
            $srcImage = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($inputPath),
                'image/png' => @imagecreatefrompng($inputPath),
                'image/gif' => @imagecreatefromgif($inputPath),
                'image/webp' => @imagecreatefromwebp($inputPath),
                default => null,
            };

            if (!$srcImage) {
                \Log::warning('GD: Could not load image');
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

            // Check if background is likely solid (white, grey, or near-white)
            $isLikelyBackground = (
                ($bgColor['r'] > 200 && $bgColor['g'] > 200 && $bgColor['b'] > 200) || // White/light grey
                (abs($bgColor['r'] - $bgColor['g']) < 20 && abs($bgColor['g'] - $bgColor['b']) < 20) // Grey tones
            );

            if (!$isLikelyBackground) {
                \Log::info('GD: Background color not suitable for removal (not white/grey)');
                imagedestroy($srcImage);
                imagedestroy($newImage);
                return false;
            }

            // Use simple threshold-based removal instead of flood fill (faster)
            $this->thresholdRemove($srcImage, $newImage, $width, $height, $bgColor);

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
     * Simple threshold-based background removal (faster than flood fill)
     */
    protected function thresholdRemove(\GdImage $src, \GdImage $dest, int $width, int $height, array $bgColor): void
    {
        $tolerance = 40;
        $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);

        imagealphablending($dest, false);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($src, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // Calculate color difference from background
                $diff = abs($r - $bgColor['r']) + abs($g - $bgColor['g']) + abs($b - $bgColor['b']);

                // Check if pixel is on edge (likely background)
                $isEdge = ($x < 5 || $x >= $width - 5 || $y < 5 || $y >= $height - 5);

                // More aggressive removal on edges, less aggressive in center
                $threshold = $isEdge ? $tolerance * 3 : $tolerance * 2;

                if ($diff <= $threshold && $this->isNearWhiteOrGrey($r, $g, $b)) {
                    imagesetpixel($dest, $x, $y, $transparent);
                } else {
                    $color = imagecolorallocate($dest, $r, $g, $b);
                    imagesetpixel($dest, $x, $y, $color);
                }
            }
        }
    }

    /**
     * Check if color is white, near-white, or grey
     */
    protected function isNearWhiteOrGrey(int $r, int $g, int $b): bool
    {
        // Check if it's a grey tone (R, G, B are similar)
        $isGrey = abs($r - $g) < 30 && abs($g - $b) < 30 && abs($r - $b) < 30;

        // Check if it's light colored
        $isLight = ($r + $g + $b) / 3 > 180;

        return $isGrey && $isLight;
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
