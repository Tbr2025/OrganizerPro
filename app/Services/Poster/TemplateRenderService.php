<?php

namespace App\Services\Poster;

use App\Models\TournamentTemplate;
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
            $this->renderImageElement($canvas, $element, $value, $x, $y);
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
     * Render image element
     */
    protected function renderImageElement(\GdImage $canvas, array $element, string $imagePath, int $x, int $y): void
    {
        $width = (int) ($element['width'] ?? 100);
        $height = (int) ($element['height'] ?? 100);
        $borderRadius = (int) ($element['borderRadius'] ?? 0);

        // Scale dimensions based on canvas (layout uses 540px canvas, actual is 1080px)
        $scale = imagesx($canvas) / 540;
        $width = (int) ($width * $scale);
        $height = (int) ($height * $scale);

        // Check if it's a path or placeholder
        if (str_starts_with($imagePath, '[') || empty($imagePath) || !Storage::disk('public')->exists($imagePath)) {
            // Draw placeholder box
            $this->drawPlaceholderBox($canvas, $x, $y, $width, $height, $element['placeholder'] ?? 'Image');
            return;
        }

        // Center the image at x, y
        $drawX = $x - ($width / 2);
        $drawY = $y - ($height / 2);

        if ($borderRadius >= 50) {
            // Circular image
            $diameter = min($width, $height);
            $this->addCircularImage($canvas, $imagePath, $x, $y, $diameter);
        } else {
            // Regular image
            $this->addImage($canvas, $imagePath, (int) $drawX, (int) $drawY, $width, $height);
        }
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

        // Calculate position from percentage
        $x = (int) (($overlay['x'] ?? 50) / 100 * $canvasWidth);
        $y = (int) (($overlay['y'] ?? 50) / 100 * $canvasHeight);

        // Get dimensions - scale from editor (540px base) to output
        $scale = $canvasWidth / 540;
        $width = (int) (($overlay['width'] ?? 100) * $scale);
        $height = (int) (($overlay['height'] ?? 100) * $scale);

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
     */
    public function renderToBase64(TournamentTemplate $template, array $data = []): string
    {
        $path = $this->renderTemplate($template, $data, true);
        $fullPath = Storage::disk('public')->path($path);

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
