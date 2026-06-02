<?php

namespace App\Jobs;

use App\Services\ImageBackgroundRemovalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RemoveImageBackground implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        public string $imagePath,
        public ?string $model = null,
        public ?int $modelId = null,
        public string $column = 'captain_image'
    ) {}

    public function handle(): void
    {
        $service = new ImageBackgroundRemovalService();
        $processedPath = $service->removeBackgroundQueued($this->imagePath);

        if ($this->model && $this->modelId) {
            // Model mode: update DB column
            if ($processedPath) {
                DB::table((new $this->model)->getTable())
                    ->where('id', $this->modelId)
                    ->update([$this->column => $processedPath]);

                Log::info("Background removed for {$this->model}#{$this->modelId}: {$processedPath}");
            } else {
                Log::info("Background removal skipped for {$this->model}#{$this->modelId} (no change needed)");
            }
        } else {
            // File-only mode: overwrite original with processed result, create .done marker
            if ($processedPath && $processedPath !== $this->imagePath) {
                Storage::disk('public')->delete($this->imagePath);
                Storage::disk('public')->move($processedPath, $this->imagePath);
            }
            Storage::disk('public')->put($this->imagePath . '.done', '1');

            Log::info("Background removed (file-only): {$this->imagePath}");
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Background removal job failed for {$this->imagePath}: {$e->getMessage()}");

        // In file-only mode, still create .done marker so polling stops
        if (!$this->model) {
            Storage::disk('public')->put($this->imagePath . '.done', '1');
        }
    }
}
