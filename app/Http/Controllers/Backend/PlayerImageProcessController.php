<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlayerImageProcessController extends Controller
{
    /**
     * Process a cropped player image: save, remove background, resize.
     * Accepts base64 data URL from Cropper.js.
     */
    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|string',
        ]);

        $dataUrl = $request->input('image');

        // Parse base64 data URL
        if (!preg_match('/^data:image\/(png|jpe?g);base64,/', $dataUrl, $matches)) {
            return response()->json(['success' => false, 'message' => 'Invalid image data.'], 422);
        }

        $imageData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $dataUrl));
        if ($imageData === false) {
            return response()->json(['success' => false, 'message' => 'Failed to decode image.'], 422);
        }

        // Ensure directory exists
        $dir = storage_path('app/public/player_images');
        File::ensureDirectoryExists($dir);

        // Save original temp file
        $originalFilename = 'original-' . Str::random(10) . '.png';
        $inputPath = $dir . '/' . $originalFilename;
        file_put_contents($inputPath, $imageData);

        // Define output path for background removal
        $outputFilename = 'processed-' . Str::random(10) . '.png';
        $outputPath = $dir . '/' . $outputFilename;

        // Run background removal via Python script
        $pythonScript = resource_path('scripts/remove_bg.py');
        $bgRemoved = false;

        if (File::exists($pythonScript)) {
            if (app()->environment('production')) {
                $pythonBinary = '/usr/bin/python3';
                $cachePath = storage_path('app/rembg_cache');
                File::ensureDirectoryExists($cachePath);
                $command = 'U2NET_HOME=' . escapeshellarg($cachePath) . ' ' .
                    escapeshellcmd($pythonBinary) . ' ' .
                    escapeshellarg($pythonScript) . ' ' .
                    escapeshellarg($inputPath) . ' ' .
                    escapeshellarg($outputPath) . ' 2>&1';
            } else {
                $pythonBinary = PHP_OS_FAMILY === 'Windows'
                    ? base_path('venv/Scripts/python.exe')
                    : 'python3';
                $command = '"' . $pythonBinary . '" "' . $pythonScript . '" "' . $inputPath . '" "' . $outputPath . '"';
            }

            try {
                set_time_limit(300);
                shell_exec($command);

                if (File::exists($outputPath)) {
                    File::delete($inputPath);
                    $bgRemoved = true;
                }
            } catch (\Exception $e) {
                \Log::warning("Background removal failed in AJAX process: " . $e->getMessage());
            }
        }

        // If background removal failed/skipped, use original as output
        if (!$bgRemoved) {
            rename($inputPath, $outputPath);
        }

        // Resize to max 800x1067 (3:4 portrait) while preserving transparency
        $this->resizeImage($outputPath, 800, 1067);

        $relativePath = 'player_images/' . $outputFilename;
        $url = Storage::url($relativePath);

        return response()->json([
            'success' => true,
            'path' => $relativePath,
            'url' => $url,
        ]);
    }

    /**
     * Resize an image to fit within max dimensions, preserving aspect ratio and transparency.
     */
    private function resizeImage(string $path, int $maxWidth, int $maxHeight): void
    {
        $sourceImage = imagecreatefrompng($path);
        if (!$sourceImage) {
            // Try loading as JPEG
            $sourceImage = imagecreatefromjpeg($path);
            if (!$sourceImage) return;
        }

        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);

        // Only resize if larger than max
        if ($origWidth <= $maxWidth && $origHeight <= $maxHeight) {
            imagedestroy($sourceImage);
            return;
        }

        $scale = min($maxWidth / $origWidth, $maxHeight / $origHeight);
        $newWidth = (int)($origWidth * $scale);
        $newHeight = (int)($origHeight * $scale);

        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        imagepng($resizedImage, $path);

        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
    }
}
