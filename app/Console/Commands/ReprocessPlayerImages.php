<?php

namespace App\Console\Commands;

use App\Models\Player;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ReprocessPlayerImages extends Command
{
    protected $signature = 'players:reprocess-images {--player= : Specific player ID} {--dry-run : Show what would be processed}';
    protected $description = 'Remove backgrounds from existing player images using rembg';

    public function handle()
    {
        $query = Player::whereNotNull('image_path')->where('image_path', '!=', '');

        if ($playerId = $this->option('player')) {
            $query->where('id', $playerId);
        }

        // Only process images that haven't been processed yet (not starting with "processed-")
        $players = $query->get()->filter(function ($player) {
            return !Str::startsWith(basename($player->image_path), 'processed-');
        });

        $this->info("Found {$players->count()} unprocessed player images.");

        if ($this->option('dry-run')) {
            foreach ($players as $player) {
                $this->line("  [{$player->id}] {$player->name}: {$player->image_path}");
            }
            return 0;
        }

        $pythonScript = resource_path('scripts/remove_bg.py');
        $rembgEnv = base_path('rembg-env/bin/python');
        $pythonBinary = file_exists($rembgEnv) ? $rembgEnv : 'python3';
        $cachePath = storage_path('app/rembg_cache');
        File::ensureDirectoryExists($cachePath);

        $success = 0;
        $failed = 0;

        foreach ($players as $player) {
            $inputPath = storage_path('app/public/' . $player->image_path);

            if (!File::exists($inputPath)) {
                $this->warn("  [{$player->id}] {$player->name}: File not found - {$player->image_path}");
                $failed++;
                continue;
            }

            $outputFilename = 'processed-' . Str::random(10) . '.png';
            $outputPath = storage_path('app/public/player_images/' . $outputFilename);

            $command = 'U2NET_HOME=' . escapeshellarg($cachePath) . ' ' .
                escapeshellcmd($pythonBinary) . ' ' .
                escapeshellarg($pythonScript) . ' ' .
                escapeshellarg($inputPath) . ' ' .
                escapeshellarg($outputPath) . ' 2>&1';

            $this->info("  [{$player->id}] {$player->name}: Processing...");
            $output = shell_exec($command);

            if (File::exists($outputPath)) {
                // Resize to 425px width preserving transparency
                $sourceImage = imagecreatefrompng($outputPath);
                $origWidth = imagesx($sourceImage);
                $origHeight = imagesy($sourceImage);

                $targetWidth = 425;
                $scale = $targetWidth / $origWidth;
                $newWidth = $targetWidth;
                $newHeight = (int)($origHeight * $scale);

                $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
                imagepng($resizedImage, $outputPath);

                imagedestroy($sourceImage);
                imagedestroy($resizedImage);

                // Delete old image
                File::delete($inputPath);

                // Update DB
                $player->update(['image_path' => 'player_images/' . $outputFilename]);
                $this->info("    -> Done: player_images/{$outputFilename}");
                $success++;
            } else {
                $this->error("    -> Failed: {$output}");
                $failed++;
            }
        }

        $this->info("Completed: {$success} success, {$failed} failed.");
        return 0;
    }
}
