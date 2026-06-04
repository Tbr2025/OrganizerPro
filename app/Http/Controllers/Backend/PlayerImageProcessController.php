<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Jobs\RemoveImageBackground;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlayerImageProcessController extends Controller
{
    /**
     * Process a cropped player image: save, resize, queue background removal.
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

        // Save and resize immediately
        $outputFilename = 'processed-' . Str::random(10) . '.png';
        $outputPath = $dir . '/' . $outputFilename;
        file_put_contents($outputPath, $imageData);

        // Enforce 3:4 aspect ratio via center-crop, then resize
        $this->enforceAspectRatio($outputPath, 3, 4);
        $this->resizeImage($outputPath, 800, 1067);

        $relativePath = 'player_images/' . $outputFilename;

        // Check if image already has transparency — skip bg removal if so
        $needsBgRemoval = !$this->hasTransparency($outputPath);

        if ($needsBgRemoval) {
            // Dispatch to queue instead of running synchronously
            RemoveImageBackground::dispatch($relativePath);
        }

        return response()->json([
            'success' => true,
            'path' => $relativePath,
            'url' => Storage::url($relativePath),
            'bgProcessing' => $needsBgRemoval,
        ]);
    }

    /**
     * Check background removal status for a given image path.
     */
    public function status(Request $request): JsonResponse
    {
        $path = $request->input('path');

        if (!$path || !Storage::disk('public')->exists($path)) {
            return response()->json(['done' => false, 'url' => null]);
        }

        $done = Storage::disk('public')->exists($path . '.done');

        return response()->json([
            'done' => $done,
            'url' => $done ? Storage::url($path) . '?t=' . time() : null,
        ]);
    }

    /**
     * Check if a PNG image already has transparent pixels (sampled from corners).
     */
    private function hasTransparency(string $filePath): bool
    {
        $info = @getimagesize($filePath);
        if (!$info || $info[2] !== IMAGETYPE_PNG) {
            return false;
        }

        $image = @imagecreatefrompng($filePath);
        if (!$image) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $transparentCount = 0;
        $sampleSize = 5;

        $corners = [
            [0, 0],
            [$width - $sampleSize, 0],
            [0, $height - $sampleSize],
            [$width - $sampleSize, $height - $sampleSize],
        ];

        foreach ($corners as [$startX, $startY]) {
            for ($x = max(0, $startX); $x < min($width, $startX + $sampleSize); $x++) {
                for ($y = max(0, $startY); $y < min($height, $startY + $sampleSize); $y++) {
                    $rgba = imagecolorat($image, $x, $y);
                    $alpha = ($rgba >> 24) & 0x7F;
                    if ($alpha > 64) {
                        $transparentCount++;
                    }
                }
            }
        }

        imagedestroy($image);

        $totalSamples = count($corners) * $sampleSize * $sampleSize;
        return ($transparentCount / $totalSamples) > 0.2;
    }

    /**
     * Enforce a specific aspect ratio by center-cropping the image.
     */
    private function enforceAspectRatio(string $path, int $ratioW, int $ratioH): void
    {
        $sourceImage = @imagecreatefrompng($path);
        if (!$sourceImage) {
            $sourceImage = @imagecreatefromjpeg($path);
            if (!$sourceImage) return;
        }

        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);

        $targetRatio = $ratioW / $ratioH;
        $currentRatio = $origWidth / $origHeight;

        // Already correct ratio (within tolerance)
        if (abs($currentRatio - $targetRatio) < 0.01) {
            imagedestroy($sourceImage);
            return;
        }

        if ($currentRatio > $targetRatio) {
            // Too wide — crop sides
            $newWidth = (int)($origHeight * $targetRatio);
            $newHeight = $origHeight;
        } else {
            // Too tall — crop top/bottom
            $newWidth = $origWidth;
            $newHeight = (int)($origWidth / $targetRatio);
        }

        $cropX = (int)(($origWidth - $newWidth) / 2);
        $cropY = (int)(($origHeight - $newHeight) / 2);

        $croppedImage = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($croppedImage, false);
        imagesavealpha($croppedImage, true);
        imagecopyresampled($croppedImage, $sourceImage, 0, 0, $cropX, $cropY, $newWidth, $newHeight, $newWidth, $newHeight);

        imagepng($croppedImage, $path);
        imagedestroy($sourceImage);
        imagedestroy($croppedImage);
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
