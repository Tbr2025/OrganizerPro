<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Jobs\RemoveImageBackground;
use App\Models\ActualTeam;
use App\Models\Player;
use App\Services\PlayerImageService;
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
        if (! preg_match('/^data:image\/(png|jpe?g);base64,/', $dataUrl, $matches)) {
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

        // Enforce 3:4 aspect ratio via center-crop, then cap at poster resolution.
        // A full-bleed player slot on a 1080x1350 poster asks for ~1000x1350 of
        // artwork, so the old 800x1067 ceiling still meant the renderer upscaled.
        $this->enforceAspectRatio($outputPath, 3, 4);
        app(PlayerImageService::class)->capSize($outputPath);

        $relativePath = 'player_images/' . $outputFilename;

        // Check if image already has transparency — skip bg removal if so
        $skipBgRemoval = $request->boolean('skip_bg_removal', false);
        $needsBgRemoval = ! $skipBgRemoval && ! $this->hasTransparency($outputPath);

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
     * Replace a player's stored photo with an already-processed upload.
     *
     * The cropper posts the file to process() first, which crops, caps it at
     * poster resolution and queues background removal; this action just points
     * the player at the result. Split that way so replacing a photo needs no
     * trip through the full player edit form, whose field visibility is driven
     * by per-tournament form config and can hide the photo field entirely.
     *
     * The superseded file is left on disk on purpose. Nothing guarantees it is
     * referenced only here — a team's captain_image and a player's image_path
     * both point into player_images/ and can name the same file — so deleting
     * it would blank the other record's photo. A stray PNG is the cheaper bug.
     */
    public function replacePlayerPhoto(Request $request, Player $player): JsonResponse
    {
        $path = $this->validateProcessedPath($request);

        if ($path === null) {
            return response()->json(['success' => false, 'message' => 'Processed image not found. Please re-upload.'], 422);
        }

        $previous = $player->image_path;
        $player->update(['image_path' => $path]);

        $this->forgetPosterCache($previous, $path);

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => Storage::url($path) . '?t=' . time(),
        ]);
    }

    /**
     * Replace a team's captain / featured-player image the same way.
     *
     * This is the image match posters draw, so it is the one organisers most
     * often need to swap late — after a player changes kit, or when the first
     * upload turned out too small to render sharply.
     */
    public function replaceCaptainPhoto(Request $request, ActualTeam $actualTeam): JsonResponse
    {
        $path = $this->validateProcessedPath($request);

        if ($path === null) {
            return response()->json(['success' => false, 'message' => 'Processed image not found. Please re-upload.'], 422);
        }

        $previous = $actualTeam->captain_image;
        $actualTeam->update(['captain_image' => $path]);

        $this->forgetPosterCache($previous, $path);

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => Storage::url($path) . '?t=' . time(),
        ]);
    }

    /**
     * Confirm the posted path really is a file this controller just wrote.
     *
     * Without the prefix check the path is attacker-controlled and would let
     * any authenticated admin point a player record at an arbitrary file on
     * the public disk.
     */
    private function validateProcessedPath(Request $request): ?string
    {
        $validated = $request->validate([
            'path' => 'required|string|max:500',
        ]);

        $path = ltrim($validated['path'], '/');

        if (! str_starts_with($path, 'player_images/') || str_contains($path, '..')) {
            return null;
        }

        return Storage::disk('public')->exists($path) ? $path : null;
    }

    /**
     * Drop cached poster derivatives of an image that is being replaced.
     *
     * enhancePlayerImage() and flipImage() key their caches on path + mtime, so
     * a *new* file gets a new key on its own — but the cut-out written next to
     * the original ("-nobg.png") is keyed on the path alone and would otherwise
     * be reused for whatever ends up at that path later.
     */
    private function forgetPosterCache(?string $previous, string $current): void
    {
        // Re-saving without picking a new file posts the path already stored;
        // there is nothing stale to drop, and binning a good cut-out would just
        // make the next poster pay to regenerate it.
        if (! $previous || $previous === $current) {
            return;
        }

        $path = $previous;

        $info = pathinfo($path);
        $noBg = ($info['dirname'] ?? '.') . '/' . ($info['filename'] ?? '') . '-nobg.png';

        Storage::disk('public')->delete([$noBg, $path . '.done']);
    }

    /**
     * Check background removal status for a given image path.
     */
    public function status(Request $request): JsonResponse
    {
        $path = $request->input('path');

        if (! $path || ! Storage::disk('public')->exists($path)) {
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
        if (! $info || $info[2] !== IMAGETYPE_PNG) {
            return false;
        }

        $image = @imagecreatefrompng($filePath);
        if (! $image) {
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
        if (! $sourceImage) {
            $sourceImage = @imagecreatefromjpeg($path);
            if (! $sourceImage) {
                return;
            }
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

}
