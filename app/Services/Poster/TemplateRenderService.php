<?php

namespace App\Services\Poster;

use App\Models\TournamentTemplate;
use App\Services\ImageBackgroundRemovalService;
use Illuminate\Support\Facades\Storage;

class TemplateRenderService extends PosterGeneratorService
{
    protected string $outputDirectory = 'generated_templates';

    /**
     * Generate poster from template model (required by abstract)
     */
    public function generate($model): string
    {
        if ($model instanceof TournamentTemplate) {
            return $this->renderTemplate($model, []);
        }
        throw new \InvalidArgumentException('Model must be TournamentTemplate');
    }

    /**
     * Render a template with given data
     */
    public function renderTemplate(TournamentTemplate $template, array $data, bool $preview = false): string
    {
        // Get template dimensions from saved values (scaled up from editor canvas)
        // Editor uses 540px base, we render at 2x for HD output
        $editorWidth = $template->canvas_width ?? 1080;
        $editorHeight = $template->canvas_height ?? 1080;

        // Scale factor: if editor dimensions are 540-based, scale to 1080+
        $scale = $editorWidth <= 540 ? 2 : 1;
        $width = $editorWidth * $scale;
        $height = $editorHeight * $scale;

        // Create canvas
        if ($template->background_image && Storage::disk('public')->exists($template->background_image)) {
            $canvas = $this->loadBackground($template->background_image);
            if ($canvas) {
                // Resize background to match target dimensions
                $bgWidth = imagesx($canvas);
                $bgHeight = imagesy($canvas);

                if ($bgWidth !== $width || $bgHeight !== $height) {
                    $resizedCanvas = imagecreatetruecolor($width, $height);
                    imagealphablending($resizedCanvas, false);
                    imagesavealpha($resizedCanvas, true);
                    imagecopyresampled($resizedCanvas, $canvas, 0, 0, 0, 0, $width, $height, $bgWidth, $bgHeight);
                    imagedestroy($canvas);
                    $canvas = $resizedCanvas;
                }
            } else {
                $canvas = $this->createCanvas($width, $height, '#1a1a2e');
            }
        } else {
            $canvas = $this->createCanvas($width, $height, '#1a1a2e');
        }

        // Get layout elements and overlay images
        $layout = $template->layout_json ?? [];
        $overlays = $template->overlay_images ?? [];

        // Mark overlays with their type
        foreach ($overlays as &$overlay) {
            $overlay['_type'] = 'overlay';
        }

        // Mark layout elements with their type
        foreach ($layout as &$element) {
            $element['_type'] = 'element';
        }

        // Merge and sort all items by z-index
        $allItems = array_merge($layout, $overlays);
        usort($allItems, fn($a, $b) => ($a['zIndex'] ?? 1) <=> ($b['zIndex'] ?? 1));

        // Render each item
        foreach ($allItems as $item) {
            if (($item['_type'] ?? '') === 'overlay') {
                $this->renderOverlayImage($canvas, $item, $width, $height);
            } else {
                $this->renderElement($canvas, $item, $data, $width, $height);
            }
        }

        // Save and return path
        $filename = $this->generateFilename('template-' . $template->id . ($preview ? '-preview' : ''));
        return $this->saveImage($canvas, $filename);
    }

    /**
     * Render single element on canvas
     */
    protected function renderElement(\GdImage $canvas, array $element, array $data, int $canvasWidth, int $canvasHeight): void
    {
        $placeholder = $element['placeholder'] ?? '';
        $type = $element['type'] ?? 'text';

        // Calculate actual position from percentage
        $x = (int) (($element['x'] ?? 50) / 100 * $canvasWidth);
        $y = (int) (($element['y'] ?? 50) / 100 * $canvasHeight);

        // Get value from data or use placeholder display
        $value = $data[$placeholder] ?? $this->getDisplayValue($placeholder);

        if ($type === 'image') {
            $this->renderImageElement($canvas, $element, $value, $x, $y, $canvasWidth);
        } elseif ($type === 'uploadedImage') {
            // Uploaded images are stored as base64 or path in the element
            $this->renderUploadedImage($canvas, $element, $x, $y, $canvasWidth);
        } elseif ($type === 'shape') {
            $this->renderShapeElement($canvas, $element, $x, $y, $canvasWidth);
        } else {
            $this->renderTextElement($canvas, $element, $value, $x, $y);
        }
    }

    /**
     * Render text element
     */
    protected function renderTextElement(\GdImage $canvas, array $element, string $text, int $x, int $y): void
    {
        $fontSize = (int) ($element['fontSize'] ?? 24);
        $color = $element['color'] ?? '#ffffff';
        $fontWeight = $element['fontWeight'] ?? '700';
        $textAlign = $element['textAlign'] ?? 'center';
        $textTransform = $element['textTransform'] ?? 'none';
        $rotation = (float) ($element['rotation'] ?? 0);
        $opacity = (int) ($element['opacity'] ?? 100);
        $shadow = $element['shadow'] ?? true;
        $shadowBlur = $element['shadowBlur'] ?? 4;
        $shadowX = $element['shadowX'] ?? 2;
        $shadowY = $element['shadowY'] ?? 2;

        // Apply text transform
        $text = match ($textTransform) {
            'uppercase' => strtoupper($text),
            'lowercase' => strtolower($text),
            'capitalize' => ucwords($text),
            default => $text,
        };

        // Map font weight to font file
        $fontFile = $this->getFontFile($fontWeight);

        // Draw shadow first if enabled
        if ($shadow) {
            $this->addText(
                $canvas,
                $text,
                $x + $shadowX,
                $y + $shadowY,
                $fontSize,
                '#000000',
                $fontFile,
                $textAlign,
                -$rotation
            );
        }

        // Draw main text
        $this->addText(
            $canvas,
            $text,
            $x,
            $y,
            $fontSize,
            $color,
            $fontFile,
            $textAlign,
            -$rotation
        );
    }

    /**
     * Render image element (placeholder)
     */
    protected function renderImageElement(\GdImage $canvas, array $element, string $imagePath, int $x, int $y, int $canvasWidth): void
    {
        $width = (int) ($element['width'] ?? 100);
        $height = (int) ($element['height'] ?? 100);
        $borderRadius = (int) ($element['borderRadius'] ?? 0);

        // Scale dimensions: element sizes are stored relative to 1080px canvas
        // No additional scaling needed if canvas is already at target size

        // Convert URL to storage path if needed
        $storagePath = $this->extractStoragePath($imagePath);

        // Check if it's a path or placeholder
        if (str_starts_with($imagePath, '[') || empty($storagePath) || !Storage::disk('public')->exists($storagePath)) {
            // Draw placeholder box
            $this->drawPlaceholderBox($canvas, $x, $y, $width, $height, $element['placeholder'] ?? 'Image');
            return;
        }

        // Remove background from image
        $storagePath = $this->getBackgroundRemovedImage($storagePath);

        // Center the image at x, y
        $drawX = $x - ($width / 2);
        $drawY = $y - ($height / 2);

        if ($borderRadius >= 50) {
            // Circular image
            $diameter = min($width, $height);
            $this->addCircularImage($canvas, $storagePath, $x, $y, $diameter);
        } else {
            // Regular image
            $this->addImage($canvas, $storagePath, (int) $drawX, (int) $drawY, $width, $height);
        }
    }

    /**
     * Render uploaded image (stored with template)
     */
    protected function renderUploadedImage(\GdImage $canvas, array $element, int $x, int $y, int $canvasWidth): void
    {
        // For uploaded images, we'd need to store the image data/path
        // Currently just draw a placeholder since we don't have the actual image data
        $width = (int) ($element['width'] ?? 150);
        $height = (int) ($element['height'] ?? 150);
        $this->drawPlaceholderBox($canvas, $x, $y, $width, $height, 'Uploaded Image');
    }

    /**
     * Render shape element
     */
    protected function renderShapeElement(\GdImage $canvas, array $element, int $x, int $y, int $canvasWidth): void
    {
        $shapeType = $element['shapeType'] ?? 'rect';
        $width = (int) ($element['width'] ?? 150);
        $height = (int) ($element['height'] ?? 100);
        $fill = $element['fill'] ?? 'rgba(99, 102, 241, 0.5)';
        $stroke = $element['stroke'] ?? '#6366f1';
        $strokeWidth = (int) ($element['strokeWidth'] ?? 2);

        // Parse fill color
        $fillColor = $this->parseColor($canvas, $fill);

        $drawX = (int) ($x - $width / 2);
        $drawY = (int) ($y - $height / 2);

        if ($shapeType === 'rect') {
            imagefilledrectangle($canvas, $drawX, $drawY, $drawX + $width, $drawY + $height, $fillColor);
        } elseif ($shapeType === 'circle') {
            $radius = min($width, $height) / 2;
            imagefilledellipse($canvas, $x, $y, (int)($radius * 2), (int)($radius * 2), $fillColor);
        } elseif ($shapeType === 'line') {
            $strokeColor = $this->parseColor($canvas, $stroke);
            imagesetthickness($canvas, $strokeWidth);
            imageline($canvas, $drawX, $y, $drawX + $width, $y, $strokeColor);
        }
    }

    /**
     * Parse color string to GD color
     */
    protected function parseColor(\GdImage $canvas, string $color): int
    {
        // Handle rgba format
        if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/', $color, $matches)) {
            $r = (int) $matches[1];
            $g = (int) $matches[2];
            $b = (int) $matches[3];
            $a = isset($matches[4]) ? (int) ((1 - (float) $matches[4]) * 127) : 0;
            return imagecolorallocatealpha($canvas, $r, $g, $b, $a);
        }

        // Handle hex format
        if (str_starts_with($color, '#')) {
            $hex = ltrim($color, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            return imagecolorallocate($canvas, $r, $g, $b);
        }

        // Default to white
        return imagecolorallocate($canvas, 255, 255, 255);
    }

    /**
     * Extract storage path from full URL or return as-is if already a path
     */
    protected function extractStoragePath(string $path): string
    {
        if (empty($path)) {
            return '';
        }

        // If it's already a storage path (no http), return as-is
        if (!str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
            return $path;
        }

        // Extract path after /storage/
        if (preg_match('#/storage/(.+)$#', $path, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Draw placeholder box for missing images
     */
    protected function drawPlaceholderBox(\GdImage $canvas, int $x, int $y, int $width, int $height, string $label): void
    {
        $drawX = (int) ($x - $width / 2);
        $drawY = (int) ($y - $height / 2);

        // Draw semi-transparent box
        $color = imagecolorallocatealpha($canvas, 255, 255, 255, 100);
        imagefilledrectangle($canvas, $drawX, $drawY, $drawX + $width, $drawY + $height, $color);

        // Draw border
        $borderColor = imagecolorallocatealpha($canvas, 255, 255, 255, 80);
        imagerectangle($canvas, $drawX, $drawY, $drawX + $width, $drawY + $height, $borderColor);

        // Add label
        $this->addText($canvas, '[' . str_replace('_', ' ', $label) . ']', $x, $y, 12, '#ffffff', 'Montserrat-Medium.ttf', 'center');
    }

    /**
     * Render static overlay image
     */
    protected function renderOverlayImage(\GdImage $canvas, array $overlay, int $canvasWidth, int $canvasHeight): void
    {
        $path = $overlay['path'] ?? '';

        if (empty($path) || !Storage::disk('public')->exists($path)) {
            return; // Skip if overlay image doesn't exist
        }

        // Remove background from overlay image
        $path = $this->getBackgroundRemovedImage($path);

        // Calculate position from percentage
        $x = (int) (($overlay['x'] ?? 50) / 100 * $canvasWidth);
        $y = (int) (($overlay['y'] ?? 50) / 100 * $canvasHeight);

        // Overlay dimensions are stored as actual pixel values
        $width = (int) ($overlay['width'] ?? 100);
        $height = (int) ($overlay['height'] ?? 100);

        // Center the image at x, y
        $drawX = (int) ($x - $width / 2);
        $drawY = (int) ($y - $height / 2);

        // Load and render the overlay image
        $overlayImage = $this->loadBackground($path);
        if (!$overlayImage) {
            return;
        }

        $srcWidth = imagesx($overlayImage);
        $srcHeight = imagesy($overlayImage);

        // Create resized image with alpha support
        $resized = imagecreatetruecolor($width, $height);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent);

        imagecopyresampled(
            $resized,
            $overlayImage,
            0, 0, 0, 0,
            $width, $height,
            $srcWidth, $srcHeight
        );

        // Apply opacity if specified
        $opacity = $overlay['opacity'] ?? 100;
        if ($opacity < 100) {
            // Note: GD doesn't have a direct opacity function, but we can use imagecopymerge
            // For simplicity, we'll just copy with full opacity for now
            // A more complex implementation would process each pixel
        }

        // Handle rotation if specified
        $rotation = $overlay['rotation'] ?? 0;
        if ($rotation != 0) {
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            $rotated = imagerotate($resized, -$rotation, $transparent);
            if ($rotated) {
                imagedestroy($resized);
                $resized = $rotated;
                // Adjust position for rotated image
                $width = imagesx($resized);
                $height = imagesy($resized);
                $drawX = (int) ($x - $width / 2);
                $drawY = (int) ($y - $height / 2);
            }
        }

        // Copy to canvas with alpha blending
        imagealphablending($canvas, true);
        imagecopy($canvas, $resized, $drawX, $drawY, 0, 0, imagesx($resized), imagesy($resized));

        imagedestroy($overlayImage);
        imagedestroy($resized);
    }

    /**
     * Get background-removed version of an image, with caching
     */
    protected function getBackgroundRemovedImage(string $storagePath): string
    {
        $bgService = new ImageBackgroundRemovalService();
        $noBgPath = $bgService->removeBackgroundNonDestructive($storagePath);
        return $noBgPath ?? $storagePath;
    }

    /**
     * Get font file based on weight
     */
    protected function getFontFile(string $weight): string
    {
        // Map weights to available fonts (Oswald + Montserrat-Medium available)
        return match ($weight) {
            '300' => 'Oswald-Light.ttf',
            '400' => 'Oswald-Regular.ttf',
            '500' => 'Oswald-Medium.ttf',
            '600' => 'Oswald-SemiBold.ttf',
            '700' => 'Oswald-Bold.ttf',
            '900' => 'Oswald-Bold.ttf',
            default => 'Oswald-Bold.ttf',
        };
    }

    /**
     * Get display value for placeholder
     */
    protected function getDisplayValue(string $placeholder): string
    {
        return match ($placeholder) {
            'player_name' => 'John Doe',
            'jersey_name' => 'J. DOE',
            'jersey_number' => '10',
            'team_name' => 'Sample Team FC',
            'tournament_name' => 'Tournament Name',
            // Team A placeholders
            'team_a_name' => 'Mountrich Cricket Club',
            'team_a_short_name' => 'MCC',
            'team_a_location' => 'Ernakulam',
            'team_a_captain_name' => 'Captain Alpha',
            // Team B placeholders
            'team_b_name' => 'Canadian Cricket Club',
            'team_b_short_name' => 'CCC',
            'team_b_location' => 'Kottayam',
            'team_b_captain_name' => 'Captain Beta',
            // Scores
            'team_a_score' => '150/6',
            'team_b_score' => '145/8',
            // Match date variants
            'match_date' => date('M d, Y'),
            'match_date_day' => date('d'),
            'match_date_month' => strtoupper(date('M')),
            'match_date_weekday' => strtoupper(date('D')),
            'match_time' => '09:00PM',
            'match_day' => date('l'),
            // Venue
            'venue' => 'DCS YouSelects Arena',
            'ground_name' => 'Ground-2',
            'match_stage' => 'Group Stage',
            'match_number' => '1',
            // Results
            'result_summary' => 'Team Alpha won by 5 runs',
            'winner_name' => 'Team Alpha',
            'man_of_the_match_name' => 'John Doe',
            // Player info
            'player_type' => 'All Rounder',
            'batting_style' => 'Right Handed',
            'bowling_style' => 'Right Arm Medium',
            'award_name' => 'Player of the Match',
            'achievement_text' => '75 runs off 45 balls',
            // Tournament info
            'description' => 'Cricket Tournament',
            'start_date' => date('M d, Y'),
            'end_date' => date('M d, Y', strtotime('+30 days')),
            'location' => 'City Sports Complex',
            'contact_phone' => '+1 234 567 8900',
            'contact_email' => 'info@example.com',
            'title' => 'Champions',
            'season' => 'Season 1',
            'year' => date('Y'),
            'group_name' => 'Group A',
            'last_updated' => date('M d, Y H:i'),
            default => ucwords(str_replace('_', ' ', $placeholder)),
        };
    }

    /**
     * Get sample data for all placeholders of a template type
     */
    public function getSampleData(string $templateType, ?array $customData = null): array
    {
        $placeholders = TournamentTemplate::getDefaultPlaceholders($templateType);
        $data = [];

        foreach ($placeholders as $placeholder) {
            $data[$placeholder] = $customData[$placeholder] ?? $this->getDisplayValue($placeholder);
        }

        return $data;
    }

    /**
     * Render template and return as base64 for preview
     * @param bool $optimizeForWeb Compress output for faster transfer
     */
    public function renderToBase64(TournamentTemplate $template, array $data = [], bool $optimizeForWeb = true): string
    {
        $path = $this->renderTemplate($template, $data, true);
        $fullPath = Storage::disk('public')->path($path);

        if ($optimizeForWeb) {
            // Load the PNG and convert to optimized JPEG for preview
            $image = @imagecreatefrompng($fullPath);
            if ($image) {
                ob_start();
                imagejpeg($image, null, 85); // 85% quality JPEG for faster transfer
                $imageData = ob_get_clean();
                imagedestroy($image);

                // Clean up preview file
                Storage::disk('public')->delete($path);

                return 'data:image/jpeg;base64,' . base64_encode($imageData);
            }
        }

        // Fallback to PNG if JPEG conversion fails
        $imageData = file_get_contents($fullPath);
        $base64 = base64_encode($imageData);

        // Clean up preview file
        Storage::disk('public')->delete($path);

        return 'data:image/png;base64,' . $base64;
    }

    /**
     * Render and save permanently
     */
    public function renderAndSave(TournamentTemplate $template, array $data, string $customFilename = null): string
    {
        $path = $this->renderTemplate($template, $data, false);

        if ($customFilename) {
            $newPath = $this->outputDirectory . '/' . $customFilename;
            Storage::disk('public')->move($path, $newPath);
            return $newPath;
        }

        return $path;
    }
}
