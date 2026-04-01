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

        if ($type === 'tableArea') {
            $this->renderTableArea($canvas, $element, $data, $canvasWidth, $canvasHeight);
            return;
        } elseif ($type === 'image') {
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

        // Only remove background from player/captain photos, not logos
        $placeholder = $element['placeholder'] ?? '';
        if ($this->shouldRemoveBackground($placeholder)) {
            $storagePath = $this->getBackgroundRemovedImage($storagePath);
        }

        // Load source image to get actual dimensions for aspect ratio
        $srcImage = $this->loadBackground($storagePath);
        if (!$srcImage) {
            $this->drawPlaceholderBox($canvas, $x, $y, $width, $height, $element['placeholder'] ?? 'Image');
            return;
        }
        $srcWidth = imagesx($srcImage);
        $srcHeight = imagesy($srcImage);
        imagedestroy($srcImage);

        // Calculate "contain" dimensions — fit within bounding box, preserve aspect ratio
        $srcRatio = $srcWidth / $srcHeight;
        $boxRatio = $width / $height;

        if ($srcRatio > $boxRatio) {
            // Source is wider than box — fit by width
            $drawWidth = $width;
            $drawHeight = (int) ($width / $srcRatio);
        } else {
            // Source is taller than box — fit by height
            $drawHeight = $height;
            $drawWidth = (int) ($height * $srcRatio);
        }

        // Center the image at x, y
        $drawX = $x - ($drawWidth / 2);
        $drawY = $y - ($drawHeight / 2);

        if ($borderRadius >= 50) {
            // Circular image
            $diameter = min($drawWidth, $drawHeight);
            $this->addCircularImage($canvas, $storagePath, $x, $y, $diameter);
        } else {
            // Regular image — aspect ratio preserved
            $this->addImage($canvas, $storagePath, (int) $drawX, (int) $drawY, $drawWidth, $drawHeight);
        }
    }

    /**
     * Render uploaded image (stored with template)
     */
    protected function renderUploadedImage(\GdImage $canvas, array $element, int $x, int $y, int $canvasWidth): void
    {
        $path = $element['imagePath'] ?? $element['path'] ?? '';
        $width = (int) ($element['width'] ?? 150);
        $height = (int) ($element['height'] ?? 150);

        if (empty($path) || !Storage::disk('public')->exists($path)) {
            $this->drawPlaceholderBox($canvas, $x, $y, $width, $height, 'Uploaded Image');
            return;
        }

        $drawX = (int) ($x - $width / 2);
        $drawY = (int) ($y - $height / 2);

        // Load and render the uploaded image
        $srcImage = $this->loadBackground($path);
        if (!$srcImage) {
            $this->drawPlaceholderBox($canvas, $x, $y, $width, $height, 'Uploaded Image');
            return;
        }

        $srcWidth = imagesx($srcImage);
        $srcHeight = imagesy($srcImage);

        // Create resized image with alpha support
        $resized = imagecreatetruecolor($width, $height);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent);

        imagecopyresampled($resized, $srcImage, 0, 0, 0, 0, $width, $height, $srcWidth, $srcHeight);

        // Handle rotation
        $rotation = $element['rotation'] ?? 0;
        if ($rotation != 0) {
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            $rotated = imagerotate($resized, -$rotation, $transparent);
            if ($rotated) {
                imagedestroy($resized);
                $resized = $rotated;
                $width = imagesx($resized);
                $height = imagesy($resized);
                $drawX = (int) ($x - $width / 2);
                $drawY = (int) ($y - $height / 2);
            }
        }

        imagealphablending($canvas, true);
        imagecopy($canvas, $resized, $drawX, $drawY, 0, 0, imagesx($resized), imagesy($resized));

        imagedestroy($srcImage);
        imagedestroy($resized);
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

        $drawX = (int) ($x - $width / 2);
        $drawY = (int) ($y - $height / 2);

        // Check if fill is a gradient config
        $isGradient = is_array($fill) && isset($fill['type']);

        if ($shapeType === 'rect') {
            if ($isGradient) {
                $this->renderGradientRect($canvas, $drawX, $drawY, $width, $height, $fill);
            } else {
                $fillColor = $this->parseColor($canvas, $fill);
                imagefilledrectangle($canvas, $drawX, $drawY, $drawX + $width, $drawY + $height, $fillColor);
            }
        } elseif ($shapeType === 'circle') {
            $radius = min($width, $height) / 2;
            if ($isGradient) {
                $this->renderGradientEllipse($canvas, $x, $y, (int)($radius * 2), (int)($radius * 2), $fill);
            } else {
                $fillColor = $this->parseColor($canvas, $fill);
                imagefilledellipse($canvas, $x, $y, (int)($radius * 2), (int)($radius * 2), $fillColor);
            }
        } elseif ($shapeType === 'triangle') {
            $points = [
                $drawX + $width / 2, $drawY,             // top center
                $drawX, $drawY + $height,                 // bottom-left
                $drawX + $width, $drawY + $height,        // bottom-right
            ];
            if ($isGradient) {
                $this->renderGradientPolygon($canvas, $points, 3, $drawX, $drawY, $width, $height, $fill);
            } else {
                $fillColor = $this->parseColor($canvas, $fill);
                imagefilledpolygon($canvas, $points, $fillColor);
            }
        } elseif ($shapeType === 'line') {
            $strokeColor = $this->parseColor($canvas, $stroke);
            imagesetthickness($canvas, $strokeWidth);
            imageline($canvas, $drawX, $y, $drawX + $width, $y, $strokeColor);
            return; // Lines don't have fill
        } elseif ($shapeType === 'star') {
            $points = $this->calculateStarPoints(5, $width / 2, $width / 4, $x, $y);
            if ($isGradient) {
                $this->renderGradientPolygon($canvas, $points, 10, $drawX, $drawY, $width, $height, $fill);
            } else {
                $fillColor = $this->parseColor($canvas, $fill);
                imagefilledpolygon($canvas, $points, $fillColor);
            }
        } elseif ($shapeType === 'diamond') {
            $points = [
                $x, $drawY,                  // top
                $drawX + $width, $y,         // right
                $x, $drawY + $height,        // bottom
                $drawX, $y,                  // left
            ];
            if ($isGradient) {
                $this->renderGradientPolygon($canvas, $points, 4, $drawX, $drawY, $width, $height, $fill);
            } else {
                $fillColor = $this->parseColor($canvas, $fill);
                imagefilledpolygon($canvas, $points, $fillColor);
            }
        }

        // Draw stroke for non-line shapes
        if ($shapeType !== 'line' && $strokeWidth > 0) {
            $strokeColor = $this->parseColor($canvas, $stroke);
            imagesetthickness($canvas, $strokeWidth);

            if ($shapeType === 'rect') {
                imagerectangle($canvas, $drawX, $drawY, $drawX + $width, $drawY + $height, $strokeColor);
            } elseif ($shapeType === 'circle') {
                $radius = min($width, $height) / 2;
                imageellipse($canvas, $x, $y, (int)($radius * 2), (int)($radius * 2), $strokeColor);
            } elseif ($shapeType === 'triangle') {
                $points = [$drawX + $width / 2, $drawY, $drawX, $drawY + $height, $drawX + $width, $drawY + $height];
                imagepolygon($canvas, $points, $strokeColor);
            } elseif ($shapeType === 'star') {
                $points = $this->calculateStarPoints(5, $width / 2, $width / 4, $x, $y);
                imagepolygon($canvas, $points, $strokeColor);
            } elseif ($shapeType === 'diamond') {
                $points = [$x, $drawY, $drawX + $width, $y, $x, $drawY + $height, $drawX, $y];
                imagepolygon($canvas, $points, $strokeColor);
            }
        }
    }

    /**
     * Render point table area element
     */
    protected function renderTableArea(\GdImage $canvas, array $element, array $data, int $canvasWidth, int $canvasHeight): void
    {
        $tableData = $data['table_data'] ?? [];
        if (empty($tableData) || !is_array($tableData)) {
            return;
        }

        $config = $element['tableConfig'] ?? [];
        $headerBg = $config['headerBg'] ?? '#1e40af';
        $headerText = $config['headerText'] ?? '#ffffff';
        $evenRowBg = $config['evenRowBg'] ?? '#1e293b';
        $oddRowBg = $config['oddRowBg'] ?? '#334155';
        $qualifiedBg = $config['qualifiedBg'] ?? '#064e3b';
        $textColor = $config['textColor'] ?? '#ffffff';
        $pointsColor = $config['pointsColor'] ?? '#FFD700';
        $fontSize = (int) ($config['fontSize'] ?? 16);
        $configRowHeight = (int) ($config['rowHeight'] ?? 80);
        $showTeamLogo = $config['showTeamLogo'] ?? true;
        $showNRR = $config['showNRR'] ?? true;
        $showLegend = $config['showLegend'] ?? true;

        // Calculate element area from percentage position
        $areaWidth = (int) ($element['width'] ?? 900);
        $areaHeight = (int) ($element['height'] ?? 500);
        $centerX = (int) (($element['x'] ?? 50) / 100 * $canvasWidth);
        $centerY = (int) (($element['y'] ?? 50) / 100 * $canvasHeight);
        $areaX = (int) ($centerX - $areaWidth / 2);
        $areaY = (int) ($centerY - $areaHeight / 2);

        // Header row height — taller for breathing room
        $headerRowHeight = max(44, (int) ($configRowHeight * 0.65));
        $legendHeight = $showLegend ? 36 : 0;
        $availableHeight = $areaHeight - $headerRowHeight - $legendHeight;
        $teamCount = count($tableData);
        $rowHeight = min($configRowHeight, (int) ($availableHeight / max($teamCount, 1)));
        // Enforce minimum row height for readability
        $rowHeight = max(36, $rowHeight);

        // Adaptive font size based on row height
        $bodyFontSize = min($fontSize, (int) ($rowHeight * 0.38));
        $headerFontSize = (int) ($bodyFontSize * 0.95);
        $logoDiameter = min((int) ($rowHeight * 0.55), 36);

        // Calculate column positions (center-aligned for stats)
        $columns = $this->calculateTableColumns($areaWidth, $showNRR, $showTeamLogo);
        // Width of each stat column for center alignment
        $statColWidth = (int) ($areaWidth * 0.075);

        // --- Draw header row ---
        $headerColor = $this->parseColor($canvas, $headerBg);
        imagefilledrectangle($canvas, $areaX, $areaY, $areaX + $areaWidth, $areaY + $headerRowHeight, $headerColor);

        // Header bottom accent line
        $accentColor = $this->parseColor($canvas, $pointsColor);
        imagefilledrectangle($canvas, $areaX, $areaY + $headerRowHeight - 2, $areaX + $areaWidth, $areaY + $headerRowHeight, $accentColor);

        $headerFont = 'Montserrat-Bold.ttf';
        $headerTextY = $areaY + (int) ($headerRowHeight * 0.62);

        $this->addText($canvas, '#', $areaX + $columns['pos'] + 10, $headerTextY, $headerFontSize, $headerText, $headerFont, 'center');
        $this->addText($canvas, 'Team', $areaX + $columns['teamLabel'], $headerTextY, $headerFontSize, $headerText, $headerFont, 'left');
        $this->addText($canvas, 'P', $areaX + $columns['played'] + (int) ($statColWidth / 2), $headerTextY, $headerFontSize, $headerText, $headerFont, 'center');
        $this->addText($canvas, 'W', $areaX + $columns['won'] + (int) ($statColWidth / 2), $headerTextY, $headerFontSize, $headerText, $headerFont, 'center');
        $this->addText($canvas, 'L', $areaX + $columns['lost'] + (int) ($statColWidth / 2), $headerTextY, $headerFontSize, $headerText, $headerFont, 'center');
        $this->addText($canvas, 'T', $areaX + $columns['tied'] + (int) ($statColWidth / 2), $headerTextY, $headerFontSize, $headerText, $headerFont, 'center');
        if ($showNRR) {
            $nrrColWidth = (int) ($areaWidth * 0.13);
            $this->addText($canvas, 'NRR', $areaX + $columns['nrr'] + (int) ($nrrColWidth / 2), $headerTextY, $headerFontSize, $headerText, $headerFont, 'center');
        }
        $ptsColWidth = (int) ($areaWidth * 0.09);
        $this->addText($canvas, 'Pts', $areaX + $columns['pts'] + (int) ($ptsColWidth / 2), $headerTextY, $headerFontSize, $headerText, $headerFont, 'center');

        // --- Draw team rows ---
        $currentY = $areaY + $headerRowHeight;
        $bodyFont = 'Montserrat-Medium.ttf';
        $dividerColor = imagecolorallocatealpha($canvas, 255, 255, 255, 100);

        foreach ($tableData as $index => $team) {
            $isQualified = !empty($team['qualified']);
            $rowBg = $isQualified ? $qualifiedBg : (($index % 2 === 0) ? $evenRowBg : $oddRowBg);

            $rowColor = $this->parseColor($canvas, $rowBg);
            imagefilledrectangle($canvas, $areaX, $currentY, $areaX + $areaWidth, $currentY + $rowHeight, $rowColor);

            // Subtle divider line at bottom of row
            if ($index < $teamCount - 1) {
                $this->drawHorizontalLine($canvas, $areaX + 8, $areaX + $areaWidth - 8, $currentY + $rowHeight - 1, $dividerColor);
            }

            $textY = $currentY + (int) ($rowHeight * 0.62);

            // Position number — center-aligned
            $this->addText($canvas, (string) ($team['position'] ?? ($index + 1)), $areaX + $columns['pos'] + 10, $textY, $bodyFontSize, $textColor, 'Montserrat-Bold.ttf', 'center');

            // Team logo (circular) — properly sized and spaced
            $teamNameX = $areaX + $columns['teamLabel'];
            if ($showTeamLogo && !empty($team['team_logo'])) {
                $logoPath = $this->extractStoragePath($team['team_logo']);
                if (!empty($logoPath) && \Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath)) {
                    $logoCenterX = $areaX + $columns['logoCenter'];
                    $logoCenterY = $currentY + (int) ($rowHeight / 2);
                    $this->addCircularImage($canvas, $logoPath, $logoCenterX, $logoCenterY, $logoDiameter);
                    $teamNameX = $logoCenterX + (int) ($logoDiameter / 2) + 8;
                }
            }

            // Team name
            $maxTeamNameLen = $showNRR ? 20 : 24;
            $teamName = mb_substr($team['team_name'] ?? 'Unknown', 0, $maxTeamNameLen);
            $this->addText($canvas, $teamName, $teamNameX, $textY, $bodyFontSize, $textColor, $bodyFont, 'left');

            // Stats — center-aligned in their columns
            $this->addText($canvas, (string) ($team['matches_played'] ?? 0), $areaX + $columns['played'] + (int) ($statColWidth / 2), $textY, $bodyFontSize, $textColor, 'Montserrat-Bold.ttf', 'center');
            $this->addText($canvas, (string) ($team['won'] ?? 0), $areaX + $columns['won'] + (int) ($statColWidth / 2), $textY, $bodyFontSize, '#4ADE80', 'Montserrat-Bold.ttf', 'center');
            $this->addText($canvas, (string) ($team['lost'] ?? 0), $areaX + $columns['lost'] + (int) ($statColWidth / 2), $textY, $bodyFontSize, '#F87171', 'Montserrat-Bold.ttf', 'center');
            $this->addText($canvas, (string) ($team['tied'] ?? 0), $areaX + $columns['tied'] + (int) ($statColWidth / 2), $textY, $bodyFontSize, '#FBBF24', 'Montserrat-Bold.ttf', 'center');

            // NRR — center-aligned
            if ($showNRR) {
                $nrr = (float) ($team['net_run_rate'] ?? 0);
                $nrrText = ($nrr >= 0 ? '+' : '') . number_format($nrr, 3);
                $nrrColor = $nrr >= 0 ? '#4ADE80' : '#F87171';
                $nrrColWidth = (int) ($areaWidth * 0.13);
                $this->addText($canvas, $nrrText, $areaX + $columns['nrr'] + (int) ($nrrColWidth / 2), $textY, $bodyFontSize, $nrrColor, $bodyFont, 'center');
            }

            // Points — bold, center-aligned, slightly larger
            $ptsColWidth = (int) ($areaWidth * 0.09);
            $ptsFontSize = (int) ($bodyFontSize * 1.2);
            $this->addText($canvas, (string) ($team['points'] ?? 0), $areaX + $columns['pts'] + (int) ($ptsColWidth / 2), $textY, $ptsFontSize, $pointsColor, 'Montserrat-Bold.ttf', 'center');

            $currentY += $rowHeight;
        }

        // Legend
        if ($showLegend) {
            $legendY = $currentY + 8;
            $qualifiedLegendColor = $this->parseColor($canvas, $qualifiedBg);
            imagefilledrectangle($canvas, $areaX + 10, $legendY, $areaX + 28, $legendY + 16, $qualifiedLegendColor);
            $this->addText($canvas, '= Qualified for next round', $areaX + 36, $legendY + 13, (int) ($bodyFontSize * 0.8), '#AAAAAA', 'Montserrat-Medium.ttf', 'left');
        }
    }

    /**
     * Draw a horizontal divider line
     */
    protected function drawHorizontalLine(\GdImage $canvas, int $x1, int $x2, int $y, int $color): void
    {
        imageline($canvas, $x1, $y, $x2, $y, $color);
    }

    /**
     * Calculate proportional column x-positions for table area
     */
    protected function calculateTableColumns(int $areaWidth, bool $showNRR, bool $showTeamLogo): array
    {
        $padding = (int) ($areaWidth * 0.015);

        if ($showNRR) {
            return [
                'pos' => $padding,
                'logoCenter' => (int) ($areaWidth * 0.065),
                'teamLabel' => (int) ($areaWidth * 0.10),
                'team' => (int) ($areaWidth * 0.04),
                'played' => (int) ($areaWidth * 0.40),
                'won' => (int) ($areaWidth * 0.48),
                'lost' => (int) ($areaWidth * 0.555),
                'tied' => (int) ($areaWidth * 0.63),
                'nrr' => (int) ($areaWidth * 0.705),
                'pts' => (int) ($areaWidth * 0.86),
            ];
        }

        return [
            'pos' => $padding,
            'logoCenter' => (int) ($areaWidth * 0.065),
            'teamLabel' => (int) ($areaWidth * 0.10),
            'team' => (int) ($areaWidth * 0.04),
            'played' => (int) ($areaWidth * 0.48),
            'won' => (int) ($areaWidth * 0.56),
            'lost' => (int) ($areaWidth * 0.64),
            'tied' => (int) ($areaWidth * 0.72),
            'nrr' => 0,
            'pts' => (int) ($areaWidth * 0.82),
        ];
    }

    /**
     * Calculate star polygon points for GD rendering
     */
    protected function calculateStarPoints(int $spikes, float $outerR, float $innerR, int $cx, int $cy): array
    {
        $points = [];
        $step = M_PI / $spikes;
        for ($i = 0; $i < 2 * $spikes; $i++) {
            $r = ($i % 2 === 0) ? $outerR : $innerR;
            $angle = $i * $step - M_PI / 2;
            $points[] = (int) ($cx + $r * cos($angle));
            $points[] = (int) ($cy + $r * sin($angle));
        }
        return $points;
    }

    /**
     * Render gradient-filled rectangle
     */
    protected function renderGradientRect(\GdImage $canvas, int $x, int $y, int $w, int $h, array $gradientConfig): void
    {
        $stops = $gradientConfig['colorStops'] ?? [];
        if (count($stops) < 2) return;

        $color1 = $this->hexToRgb($stops[0]['color'] ?? '#6366f1');
        $color2 = $this->hexToRgb($stops[1]['color'] ?? '#ec4899');
        $angle = (int) ($gradientConfig['angle'] ?? 90);

        imagealphablending($canvas, true);

        if ($angle === 0 || $angle === 360 || $angle === 180) {
            // Horizontal gradient
            $reverse = ($angle === 180);
            for ($i = 0; $i < $w; $i++) {
                $ratio = $i / max($w - 1, 1);
                if ($reverse) $ratio = 1 - $ratio;
                $lineColor = $this->interpolateColor($canvas, $color1, $color2, $ratio);
                imageline($canvas, $x + $i, $y, $x + $i, $y + $h, $lineColor);
            }
        } else {
            // Vertical or angled gradient — render vertically (default for 90°, 270°)
            $reverse = ($angle > 180 && $angle < 360);
            for ($i = 0; $i < $h; $i++) {
                $ratio = $i / max($h - 1, 1);
                if ($reverse) $ratio = 1 - $ratio;
                $lineColor = $this->interpolateColor($canvas, $color1, $color2, $ratio);
                imageline($canvas, $x, $y + $i, $x + $w, $y + $i, $lineColor);
            }
        }
    }

    /**
     * Render gradient-filled ellipse using a temporary image
     */
    protected function renderGradientEllipse(\GdImage $canvas, int $cx, int $cy, int $w, int $h, array $gradientConfig): void
    {
        $stops = $gradientConfig['colorStops'] ?? [];
        if (count($stops) < 2) return;

        $color1 = $this->hexToRgb($stops[0]['color'] ?? '#6366f1');
        $color2 = $this->hexToRgb($stops[1]['color'] ?? '#ec4899');
        $type = $gradientConfig['type'] ?? 'linear';

        // Create temp image for the gradient shape
        $temp = imagecreatetruecolor($w, $h);
        imagealphablending($temp, false);
        imagesavealpha($temp, true);
        $transparent = imagecolorallocatealpha($temp, 0, 0, 0, 127);
        imagefill($temp, 0, 0, $transparent);

        imagealphablending($temp, true);

        if ($type === 'radial') {
            // Radial gradient from center
            $maxR = max($w, $h) / 2;
            for ($r = (int) $maxR; $r >= 0; $r--) {
                $ratio = $r / max($maxR, 1);
                $c = $this->interpolateColor($temp, $color1, $color2, $ratio);
                imagefilledellipse($temp, (int)($w / 2), (int)($h / 2), $r * 2, $r * 2, $c);
            }
        } else {
            // Linear gradient, draw line-by-line then mask
            for ($i = 0; $i < $h; $i++) {
                $ratio = $i / max($h - 1, 1);
                $c = $this->interpolateColor($temp, $color1, $color2, $ratio);
                imageline($temp, 0, $i, $w, $i, $c);
            }
        }

        // Mask with ellipse — clear pixels outside ellipse
        $mask = imagecreatetruecolor($w, $h);
        imagealphablending($mask, false);
        $black = imagecolorallocate($mask, 0, 0, 0);
        $white = imagecolorallocate($mask, 255, 255, 255);
        imagefill($mask, 0, 0, $black);
        imagefilledellipse($mask, (int)($w / 2), (int)($h / 2), $w, $h, $white);

        for ($px = 0; $px < $w; $px++) {
            for ($py = 0; $py < $h; $py++) {
                $maskColor = imagecolorat($mask, $px, $py) & 0xFF;
                if ($maskColor === 0) {
                    imagesetpixel($temp, $px, $py, $transparent);
                }
            }
        }
        imagedestroy($mask);

        imagealphablending($canvas, true);
        imagecopy($canvas, $temp, $cx - (int)($w / 2), $cy - (int)($h / 2), 0, 0, $w, $h);
        imagedestroy($temp);
    }

    /**
     * Render gradient-filled polygon using a temporary image
     */
    protected function renderGradientPolygon(\GdImage $canvas, array $points, int $numPoints, int $bx, int $by, int $bw, int $bh, array $gradientConfig): void
    {
        $stops = $gradientConfig['colorStops'] ?? [];
        if (count($stops) < 2) return;

        $color1 = $this->hexToRgb($stops[0]['color'] ?? '#6366f1');
        $color2 = $this->hexToRgb($stops[1]['color'] ?? '#ec4899');

        // Create temp image with gradient
        $temp = imagecreatetruecolor($bw, $bh);
        imagealphablending($temp, false);
        imagesavealpha($temp, true);
        $transparent = imagecolorallocatealpha($temp, 0, 0, 0, 127);
        imagefill($temp, 0, 0, $transparent);
        imagealphablending($temp, true);

        for ($i = 0; $i < $bh; $i++) {
            $ratio = $i / max($bh - 1, 1);
            $c = $this->interpolateColor($temp, $color1, $color2, $ratio);
            imageline($temp, 0, $i, $bw, $i, $c);
        }

        // Mask: only keep pixels inside polygon
        // Translate polygon points to temp image coordinates
        $localPoints = [];
        for ($i = 0; $i < count($points); $i += 2) {
            $localPoints[] = $points[$i] - $bx;
            $localPoints[] = $points[$i + 1] - $by;
        }

        $mask = imagecreatetruecolor($bw, $bh);
        imagealphablending($mask, false);
        $black = imagecolorallocate($mask, 0, 0, 0);
        $white = imagecolorallocate($mask, 255, 255, 255);
        imagefill($mask, 0, 0, $black);
        imagefilledpolygon($mask, $localPoints, $white);

        for ($px = 0; $px < $bw; $px++) {
            for ($py = 0; $py < $bh; $py++) {
                $maskColor = imagecolorat($mask, $px, $py) & 0xFF;
                if ($maskColor === 0) {
                    imagesetpixel($temp, $px, $py, $transparent);
                }
            }
        }
        imagedestroy($mask);

        imagealphablending($canvas, true);
        imagecopy($canvas, $temp, $bx, $by, 0, 0, $bw, $bh);
        imagedestroy($temp);
    }

    /**
     * Interpolate between two colors
     */
    protected function interpolateColor(\GdImage $canvas, array $c1, array $c2, float $ratio): int
    {
        $r = (int) ($c1['r'] + ($c2['r'] - $c1['r']) * $ratio);
        $g = (int) ($c1['g'] + ($c2['g'] - $c1['g']) * $ratio);
        $b = (int) ($c1['b'] + ($c2['b'] - $c1['b']) * $ratio);
        return imagecolorallocate($canvas, max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
    }

    /**
     * Convert hex color to RGB array
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
     * Parse color string to GD color
     */
    protected function parseColor(\GdImage $canvas, string|array $color): int
    {
        // If gradient config was passed, use first color stop
        if (is_array($color)) {
            $firstColor = $color['colorStops'][0]['color'] ?? '#ffffff';
            return $this->parseColor($canvas, $firstColor);
        }
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
     * Check if background removal should be applied for this placeholder type
     */
    protected function shouldRemoveBackground(string $placeholder): bool
    {
        $bgRemovalPlaceholders = [
            'player_image',
            'player_photo',
            'team_a_captain_image',
            'team_b_captain_image',
            'captain_image',
            'man_of_the_match_image',
            'award_player_image',
        ];

        return in_array($placeholder, $bgRemovalPlaceholders);
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
            'match_time' => '09:00 PM',
            'match_day' => date('l'),
            // Venue
            'venue' => 'DCS YouSelects Arena',
            'ground_name' => 'Ground-2',
            'match_stage' => 'Group Stage',
            'match_number' => '1',
            // Results
            'result_summary' => 'Team Alpha won by 5 runs',
            'winner_name' => 'Team Alpha',
            'man_of_the_match_name' => 'Player Name',
            'match_details' => 'Team A vs Team B',
            // Player info
            'player_type' => 'All Rounder',
            'batting_style' => 'Right Handed',
            'bowling_style' => 'Right Arm Medium',
            'award_name' => 'Player of the Match',
            'achievement_text' => '75 runs off 45 balls',
            'batting_figures' => '59 (36) 9x4 1x6',
            'bowling_figures' => '4 - 0 - 25 - 2',
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
            'table_data' => '[Table Data]',
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
            if ($placeholder === 'table_data') {
                $data[$placeholder] = $customData[$placeholder] ?? $this->getSampleTableData();
                continue;
            }
            $data[$placeholder] = $customData[$placeholder] ?? $this->getDisplayValue($placeholder);
        }

        return $data;
    }

    /**
     * Get sample point table data for preview
     */
    protected function getSampleTableData(): array
    {
        return [
            ['position' => 1, 'team_name' => 'Mountrich CC', 'team_logo' => '', 'matches_played' => 5, 'won' => 4, 'lost' => 1, 'tied' => 0, 'net_run_rate' => 1.250, 'points' => 8, 'qualified' => true],
            ['position' => 2, 'team_name' => 'Canadian CC', 'team_logo' => '', 'matches_played' => 5, 'won' => 3, 'lost' => 2, 'tied' => 0, 'net_run_rate' => 0.450, 'points' => 6, 'qualified' => true],
            ['position' => 3, 'team_name' => 'Thunder Kings', 'team_logo' => '', 'matches_played' => 5, 'won' => 2, 'lost' => 3, 'tied' => 0, 'net_run_rate' => -0.320, 'points' => 4, 'qualified' => false],
            ['position' => 4, 'team_name' => 'Royal Strikers', 'team_logo' => '', 'matches_played' => 5, 'won' => 1, 'lost' => 4, 'tied' => 0, 'net_run_rate' => -1.100, 'points' => 2, 'qualified' => false],
        ];
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
