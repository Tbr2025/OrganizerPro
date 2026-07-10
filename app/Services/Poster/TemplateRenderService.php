<?php

namespace App\Services\Poster;

use App\Models\TournamentTemplate;
use App\Services\ImageBackgroundRemovalService;
use Illuminate\Support\Facades\Storage;

class TemplateRenderService extends PosterGeneratorService
{
    protected string $outputDirectory = 'generated_templates';
    protected bool $skipBlanks = false;
    protected int $renderScale = 1;

    /** Memoized font registry for resolving installed fonts to TTF files. */
    protected ?\App\Services\Fonts\FontService $fontService = null;

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
    public function renderTemplate(TournamentTemplate $template, array $data, bool $preview = false, bool $skipBlanks = false): string
    {
        $this->skipBlanks = $skipBlanks;
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

        // Render each item (catch errors per-element so one broken element doesn't fail the poster)
        // Scale factor for font sizes and element dimensions (editor → render canvas)
        $this->renderScale = $scale;
        foreach ($allItems as $item) {
            try {
                if (($item['_type'] ?? '') === 'overlay') {
                    $this->renderOverlayImage($canvas, $item, $width, $height);
                } else {
                    $this->renderElement($canvas, $item, $data, $width, $height);
                }
            } catch (\Throwable $e) {
                \Log::warning('Poster element render failed: ' . ($item['placeholder'] ?? $item['type'] ?? 'unknown') . ' - ' . $e->getMessage());
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
        // Skip hidden elements (layer visibility toggle)
        if (!empty($element['hidden'])) {
            return;
        }

        $placeholder = $element['placeholder'] ?? '';
        $type = $element['type'] ?? 'text';

        // Calculate actual position from percentage
        $x = (int) (($element['x'] ?? 50) / 100 * $canvasWidth);
        $y = (int) (($element['y'] ?? 50) / 100 * $canvasHeight);

        // Dispatch table/scorecard types early (they use $data directly, not $value)
        if ($type === 'tableArea') {
            $this->renderTableArea($canvas, $element, $data, $canvasWidth, $canvasHeight);
            return;
        } elseif ($type === 'scorecardTable') {
            $this->renderScorecardTable($canvas, $element, $data, $canvasWidth, $canvasHeight);
            return;
        } elseif ($type === 'fixtureArea' || ($placeholder === 'fixture_area' && is_array($data['fixture_area'] ?? null))) {
            $this->renderFixtureArea($canvas, $element, $data, $canvasWidth, $canvasHeight);
            return;
        }

        // Get value from data, element's static text, or placeholder display
        $staticText = $element['text'] ?? '';
        // Ignore broken JS interpolation text saved by template editor
        if (str_contains($staticText, 'item.placeholder') || str_contains($staticText, 'item.text')) {
            $staticText = '';
        }
        if ($this->skipBlanks) {
            $value = $data[$placeholder] ?? '';
            if (!$placeholder && $staticText) {
                // Custom/static text — use the text typed by the user in the editor
                $value = $staticText;
            } elseif ($placeholder && ($value === '' || $value === null)) {
                // Blank data-bound field: hide it on generation so unresolved
                // placeholders never show. Exception: person-photo placeholders
                // keep their silhouette fallback (a deliberate design default,
                // not a leftover placeholder box).
                if ($type === 'image' && $this->shouldRemoveBackground($placeholder)) {
                    // fall through with empty $value → silhouette in renderImageElement
                } else {
                    return; // Skip blank text + generic image placeholder boxes
                }
            }
        } else {
            $value = $data[$placeholder] ?? ($staticText ?: $this->getDisplayValue($placeholder));
        }

        // Ensure value is always a string — skip array data (handled by special element types)
        if (is_array($value)) {
            return;
        }
        $value = (string) ($value ?? '');

        if ($type === 'icon') {
            $this->renderIconElement($canvas, $element, $x, $y);
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
        $fontSize = (int) (($element['fontSize'] ?? 24) * $this->renderScale);
        $color = $element['color'] ?? '#ffffff';
        $fontWeight = $element['fontWeight'] ?? '700';
        $fontStyle = $element['fontStyle'] ?? 'normal';
        $textAlign = $element['textAlign'] ?? 'center';
        $textTransform = $element['textTransform'] ?? 'none';
        $rotation = (float) ($element['rotation'] ?? 0);
        $skewX = (float) ($element['skewX'] ?? 0);
        $opacity = (int) ($element['opacity'] ?? 100);
        $underline = (bool) ($element['underline'] ?? false);
        $linethrough = (bool) ($element['linethrough'] ?? false);
        // Text shadow (default 0 = off)
        $shadowData = $element['shadow'] ?? null;
        $shadowX = (int) (($shadowData['offsetX'] ?? 0) * $this->renderScale);
        $shadowY = (int) (($shadowData['offsetY'] ?? 0) * $this->renderScale);
        $shadowColor = $shadowData['color'] ?? '#000000';
        $shadow = ($shadowX !== 0 || $shadowY !== 0 || ($shadowData['blur'] ?? 0) > 0);
        // Text stroke
        $strokeColor = $element['stroke'] ?? null;
        $strokeWidth = (int) (($element['strokeWidth'] ?? 0) * $this->renderScale);

        // Apply text transform
        $text = match ($textTransform) {
            'uppercase' => strtoupper($text),
            'lowercase' => strtolower($text),
            'capitalize' => ucwords($text),
            default => $text,
        };

        // Map font weight + style to font file
        $fontFamily = $element['fontFamily'] ?? 'Montserrat';
        $fontFile = $this->getFontFile($fontWeight, $fontStyle, $fontFamily);

        // Auto Size / box width support.
        //  - autoSize ON  -> shrink the font so the full text fits one line in the box.
        //  - autoSize OFF -> keep the chosen size and wrap the text onto multiple lines.
        // baseFontSize is the user-chosen size; the stored fontSize may already be a
        // shrunk value from the editor (computed against example text), so always work
        // from baseFontSize here and re-fit against the real value.
        $autoSize = (bool) ($element['autoSize'] ?? false);
        $boxWidth = (float) ($element['width'] ?? 0) * $this->renderScale;
        $baseFontSize = (int) (($element['baseFontSize'] ?? ($element['fontSize'] ?? 24)) * $this->renderScale);
        $fontSize = $baseFontSize;
        if ($autoSize && $boxWidth > 0) {
            $fontSize = $this->shrinkFontToWidth($text, $fontFile, $baseFontSize, $boxWidth);
        }

        // If skew is applied, render to temp canvas and apply affine shear
        if ($skewX != 0) {
            $this->renderSkewedText($canvas, $text, $x, $y, $fontSize, $color, $fontFile, $textAlign, $rotation, $skewX, $shadow, $shadowX, $shadowY, $shadowColor, $strokeColor, $strokeWidth);
            return;
        }

        // Determine the line(s) to draw. Wrap mode (autoSize OFF, box width set)
        // splits the text into multiple lines that fit the box width.
        if (!$autoSize && $boxWidth > 0) {
            $lines = $this->wrapTextToWidth($text, $fontFile, $fontSize, $boxWidth);
        } else {
            $lines = [$text];
        }
        $lineHeight = (int) round($fontSize * 1.16);
        $blockHeight = $lineHeight * max(1, count($lines));
        // Vertically centre the block on the element's y (origin is center), so a
        // single line lands exactly at $y (matching the previous behaviour).
        $firstCenterY = $y - intdiv($blockHeight, 2) + intdiv($lineHeight, 2);

        // Horizontal anchor. The stored $x is the element's CENTER (Fabric originX
        // is 'center'). drawTextLine()/addText() interpret the anchor per-align:
        //   left  -> left edge of text, center -> text centre, right -> right edge.
        // Convert the box centre into the correct edge so left/right actually align
        // within the text box (previously everything collapsed to centre). Only when
        // unrotated and a real box width is known; otherwise keep the centre point.
        $anchorX = $x;
        if ($rotation == 0 && $boxWidth > 0) {
            $halfBox = intdiv((int) round($boxWidth), 2);
            $anchorX = match ($textAlign) {
                'left'  => $x - $halfBox,
                'right' => $x + $halfBox,
                default => $x,
            };
        }

        // If opacity < 100, render text onto a temp canvas and merge with opacity
        if ($opacity < 100) {
            $canvasW = imagesx($canvas);
            $canvasH = imagesy($canvas);
            $tempCanvas = imagecreatetruecolor($canvasW, $canvasH);
            imagealphablending($tempCanvas, true);
            imagesavealpha($tempCanvas, true);
            // Copy the current canvas into the temp so that areas WITHOUT this
            // element stay identical to the existing layers. imagecopymerge()
            // ignores the source alpha channel, so a transparent fill would be
            // treated as opaque black and darken the whole poster — copying the
            // canvas first keeps the opacity change scoped to this element only.
            imagecopy($tempCanvas, $canvas, 0, 0, 0, 0, $canvasW, $canvasH);

            foreach ($lines as $i => $line) {
                $ly = $firstCenterY + $i * $lineHeight;
                $this->drawTextLine($tempCanvas, $line, $anchorX, $ly, $fontSize, $color, $fontFile, $textAlign, -$rotation, $shadow, $shadowX, $shadowY, $shadowColor, $strokeColor, $strokeWidth, $underline, $linethrough);
            }

            // Merge temp canvas onto main canvas with opacity
            imagecopymerge($canvas, $tempCanvas, 0, 0, 0, 0, $canvasW, $canvasH, $opacity);
            imagedestroy($tempCanvas);
            return;
        }

        foreach ($lines as $i => $line) {
            $ly = $firstCenterY + $i * $lineHeight;
            $this->drawTextLine($canvas, $line, $anchorX, $ly, $fontSize, $color, $fontFile, $textAlign, -$rotation, $shadow, $shadowX, $shadowY, $shadowColor, $strokeColor, $strokeWidth, $underline, $linethrough);
        }
    }

    /**
     * Draw a single line of text including shadow, stroke, the fill, and
     * underline/linethrough decorations. $y is the vertical centre of the line.
     */
    protected function drawTextLine(\GdImage $canvas, string $text, int $x, int $y, int $fontSize, string $color, string $fontFile, string $textAlign, float $angle, bool $shadow, int $shadowX, int $shadowY, string $shadowColor, ?string $strokeColor, int $strokeWidth, bool $underline, bool $linethrough): void
    {
        if ($shadow) {
            $this->addText($canvas, $text, $x + $shadowX, $y + $shadowY, $fontSize, $shadowColor, $fontFile, $textAlign, $angle);
        }
        if ($strokeColor && $strokeWidth > 0) {
            $this->renderTextStroke($canvas, $text, $x, $y, $fontSize, $strokeColor, $strokeWidth, $fontFile, $textAlign, $angle);
        }
        $this->addText($canvas, $text, $x, $y, $fontSize, $color, $fontFile, $textAlign, $angle);
        if ($underline || $linethrough) {
            $this->renderTextDecoration($canvas, $text, $x, $y, $fontSize, $color, $fontFile, $textAlign, $angle, $underline, $linethrough);
        }
    }

    /**
     * Resolve a usable TTF path for a font file (mirrors PosterGeneratorService::addText
     * fallback) so width measurement and drawing use the identical font.
     */
    protected function fontPathFor(string $fontFile): ?string
    {
        $path = public_path('fonts/' . $fontFile);
        if (file_exists($path)) {
            return $path;
        }
        foreach (['Oswald-Bold.ttf', 'Montserrat-Medium.ttf'] as $fallback) {
            $path = public_path('fonts/' . $fallback);
            if (file_exists($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Shrink a font size (never grow above $maxSize) until the single-line text
     * width fits within $boxWidth. Returns the fitted size (floored at $minSize).
     */
    protected function shrinkFontToWidth(string $text, string $fontFile, int $maxSize, float $boxWidth, int $minSize = 8): int
    {
        if ($text === '' || $boxWidth <= 0) {
            return $maxSize;
        }
        $fontPath = $this->fontPathFor($fontFile);
        if (!$fontPath) {
            return $maxSize;
        }
        $size = $maxSize;
        // Proportional first guess to avoid a long step-down loop.
        $bbox = @imagettfbbox($size, 0, $fontPath, $text);
        if ($bbox) {
            $width = abs($bbox[2] - $bbox[0]);
            if ($width > $boxWidth && $width > 0) {
                $size = (int) max($minSize, floor($size * ($boxWidth / $width)));
            }
        }
        // Verify and step down for the last few pixels.
        while ($size > $minSize) {
            $bbox = @imagettfbbox($size, 0, $fontPath, $text);
            if (!$bbox) {
                break;
            }
            if (abs($bbox[2] - $bbox[0]) <= $boxWidth) {
                break;
            }
            $size--;
        }
        return $size;
    }

    /**
     * Greedy word-wrap: split text into lines that each fit within $boxWidth at
     * the given font size. Explicit newlines in the source are preserved.
     */
    protected function wrapTextToWidth(string $text, string $fontFile, int $fontSize, float $boxWidth): array
    {
        $fontPath = $this->fontPathFor($fontFile);
        if (!$fontPath || $boxWidth <= 0) {
            return [$text];
        }
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $paragraph) {
            $words = explode(' ', $paragraph);
            $line = '';
            foreach ($words as $word) {
                $candidate = $line === '' ? $word : $line . ' ' . $word;
                $bbox = @imagettfbbox($fontSize, 0, $fontPath, $candidate);
                $width = $bbox ? abs($bbox[2] - $bbox[0]) : 0;
                if ($width > $boxWidth && $line !== '') {
                    $lines[] = $line;
                    $line = $word;
                } else {
                    $line = $candidate;
                }
            }
            $lines[] = $line;
        }
        return $lines ?: [$text];
    }

    /**
     * Render text stroke by drawing text multiple times offset in 8 directions
     */
    protected function renderTextStroke(
        \GdImage $canvas, string $text, int $x, int $y,
        int $fontSize, string $strokeColor, int $strokeWidth,
        string $fontFile, string $textAlign, float $angle
    ): void {
        // Draw the text offset in 8 directions to simulate stroke
        $offsets = [
            [-1, -1], [0, -1], [1, -1],
            [-1,  0],          [1,  0],
            [-1,  1], [0,  1], [1,  1],
        ];
        for ($w = 1; $w <= $strokeWidth; $w++) {
            foreach ($offsets as [$dx, $dy]) {
                $this->addText($canvas, $text, $x + $dx * $w, $y + $dy * $w, $fontSize, $strokeColor, $fontFile, $textAlign, $angle);
            }
        }
    }

    /**
     * Render text with skew (shear) transformation
     * Uses manual row-by-row horizontal shift instead of imageaffine()
     * to properly preserve transparency (imageaffine fills empty areas with opaque black).
     */
    protected function renderSkewedText(
        \GdImage $canvas, string $text, int $x, int $y,
        int $fontSize, string $color, string $fontFile, string $textAlign,
        float $rotation, float $skewX, bool $shadow, int $shadowX, int $shadowY,
        string $shadowColor = '#000000', ?string $strokeColor = null, int $strokeWidth = 0
    ): void {
        if ($text === '') return;

        $fontPath = public_path('fonts/' . $fontFile);
        if (!file_exists($fontPath)) {
            $fontPath = public_path('fonts/Oswald-Bold.ttf');
            if (!file_exists($fontPath)) return;
        }

        // Measure text bounds
        $bbox = @imagettfbbox($fontSize, 0, $fontPath, $text);
        if (!$bbox) return;
        $textWidth = abs($bbox[2] - $bbox[0]);
        $textHeight = abs($bbox[7] - $bbox[1]);

        // Create temp canvas with padding for text drawing
        $padding = 20 + $strokeWidth;
        $tmpW = $textWidth + $padding * 2 + abs($shadowX) + 4;
        $tmpH = $textHeight + $padding * 2 + abs($shadowY) + 4;

        $tmp = imagecreatetruecolor($tmpW, $tmpH);
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
        $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
        imagefill($tmp, 0, 0, $transparent);
        imagealphablending($tmp, true);

        // Position text in temp canvas
        $drawX = $padding;
        $drawY = $padding + $textHeight;

        // Draw shadow
        if ($shadow) {
            $shadowRgb = $this->hexToRgb($shadowColor);
            $shadowGd = imagecolorallocate($tmp, $shadowRgb['r'], $shadowRgb['g'], $shadowRgb['b']);
            @imagettftext($tmp, $fontSize, 0, (int) ($drawX + $shadowX), (int) ($drawY + $shadowY), $shadowGd, $fontPath, $text);
        }

        // Draw stroke
        if ($strokeColor && $strokeWidth > 0) {
            $strokeRgb = $this->hexToRgb($strokeColor);
            $strokeGd = imagecolorallocate($tmp, $strokeRgb['r'], $strokeRgb['g'], $strokeRgb['b']);
            $offsets = [[-1,-1],[0,-1],[1,-1],[-1,0],[1,0],[-1,1],[0,1],[1,1]];
            for ($w = 1; $w <= $strokeWidth; $w++) {
                foreach ($offsets as [$dx, $dy]) {
                    @imagettftext($tmp, $fontSize, 0, (int)($drawX + $dx * $w), (int)($drawY + $dy * $w), $strokeGd, $fontPath, $text);
                }
            }
        }

        // Draw main text
        $rgb = $this->hexToRgb($color);
        $textColor = imagecolorallocate($tmp, $rgb['r'], $rgb['g'], $rgb['b']);
        @imagettftext($tmp, $fontSize, 0, (int) $drawX, (int) $drawY, $textColor, $fontPath, $text);

        // Apply skew manually: row-by-row horizontal shift (CSS skewX)
        // For skewX, x' = x + tan(angle) * y
        $skewTan = tan(deg2rad($skewX));

        // Calculate horizontal offset range to size the output canvas
        $minOffset = 0;
        $maxOffset = 0;
        for ($row = 0; $row < $tmpH; $row++) {
            $offset = (int) round($row * $skewTan);
            $minOffset = min($minOffset, $offset);
            $maxOffset = max($maxOffset, $offset);
        }

        $shiftX = -$minOffset; // shift all offsets to positive
        $skewedW = $tmpW + ($maxOffset - $minOffset);
        $skewedH = $tmpH;

        $skewed = imagecreatetruecolor($skewedW, $skewedH);
        imagealphablending($skewed, false);
        imagesavealpha($skewed, true);
        $transSkewed = imagecolorallocatealpha($skewed, 0, 0, 0, 127);
        imagefill($skewed, 0, 0, $transSkewed);

        // Copy each row with horizontal offset (preserves transparency perfectly)
        for ($row = 0; $row < $tmpH; $row++) {
            $offset = (int) round($row * $skewTan) + $shiftX;
            imagecopy($skewed, $tmp, $offset, $row, 0, $row, $tmpW, 1);
        }
        imagedestroy($tmp);

        // Apply rotation if needed (negate to match Fabric.js CW-positive convention → imagerotate CCW-positive)
        if ($rotation != 0) {
            imagealphablending($skewed, false);
            $transColor = imagecolorallocatealpha($skewed, 0, 0, 0, 127);
            $rotated = @imagerotate($skewed, -$rotation, $transColor);
            imagedestroy($skewed);
            if (!$rotated) return;
            imagesavealpha($rotated, true);
            $skewed = $rotated;
        }

        // Composite onto main canvas, centered at (x, y)
        $finalW = imagesx($skewed);
        $finalH = imagesy($skewed);

        // Adjust destination based on text alignment
        if ($textAlign === 'center') {
            $destX = $x - (int) ($finalW / 2);
        } elseif ($textAlign === 'right') {
            $destX = $x - $finalW;
        } else {
            $destX = $x;
        }
        $destY = $y - (int) ($finalH / 2);

        imagealphablending($canvas, true);
        imagecopy($canvas, $skewed, $destX, $destY, 0, 0, $finalW, $finalH);
        imagedestroy($skewed);
    }

    /**
     * Render underline / linethrough decoration on text
     */
    protected function renderTextDecoration(
        \GdImage $canvas, string $text, int $x, int $y,
        int $fontSize, string $color, string $fontFile,
        string $textAlign, float $angle, bool $underline, bool $linethrough
    ): void {
        $fontPath = public_path('fonts/' . $fontFile);
        if (!file_exists($fontPath)) {
            $fontPath = public_path('fonts/Oswald-Bold.ttf');
            if (!file_exists($fontPath)) return;
        }

        $bbox = @imagettfbbox($fontSize, $angle, $fontPath, $text);
        if (!$bbox) return;
        $textWidth = abs($bbox[2] - $bbox[0]);

        // Adjust y from center to baseline (same as addText)
        $baselineY = $y - (int) (($bbox[7] + $bbox[1]) / 2);

        $rgb = $this->hexToRgb($color);
        $lineColor = imagecolorallocate($canvas, $rgb['r'], $rgb['g'], $rgb['b']);
        $thickness = max(2, (int) ($fontSize / 14));

        // Calculate start X based on alignment
        $startX = match ($textAlign) {
            'center' => $x - ($textWidth / 2),
            'right' => $x - $textWidth,
            default => $x,
        };
        $endX = $startX + $textWidth;

        imagesetthickness($canvas, $thickness);

        if ($underline) {
            $lineY = $baselineY + (int) ($fontSize * 0.15);
            imageline($canvas, (int) $startX, $lineY, (int) $endX, $lineY, $lineColor);
        }

        if ($linethrough) {
            $lineY = $baselineY - (int) ($fontSize * 0.3);
            imageline($canvas, (int) $startX, $lineY, (int) $endX, $lineY, $lineColor);
        }

        imagesetthickness($canvas, 1);
    }

    /**
     * Render Font Awesome icon element
     */
    protected function renderIconElement(\GdImage $canvas, array $element, int $x, int $y): void
    {
        $unicode = $element['iconUnicode'] ?? '';
        if (!$unicode) return;

        $fontSize = (int) (($element['fontSize'] ?? 64) * $this->renderScale);
        $color = $element['color'] ?? '#ffffff';
        $rotation = (float) ($element['rotation'] ?? 0);

        $fontFile = public_path('fonts/fa-solid-900.ttf');
        if (!file_exists($fontFile)) return;

        $rgb = $this->hexToRgb($color);
        $textColor = imagecolorallocate($canvas, $rgb['r'], $rgb['g'], $rgb['b']);

        // Calculate bounding box for centering
        $bbox = imagettfbbox($fontSize, 0, $fontFile, $unicode);
        $textWidth = abs($bbox[2] - $bbox[0]);
        $textHeight = abs($bbox[7] - $bbox[1]);
        $drawX = $x - ($textWidth / 2);
        $drawY = $y + ($textHeight / 2);

        if ($rotation != 0) {
            // Render rotated icon via temp canvas
            $padding = 20;
            $tmpSize = max($textWidth, $textHeight) + $padding * 2;
            $tmp = imagecreatetruecolor($tmpSize, $tmpSize);
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
            imagefill($tmp, 0, 0, $transparent);
            imagealphablending($tmp, true);

            $tmpColor = imagecolorallocate($tmp, $rgb['r'], $rgb['g'], $rgb['b']);
            $tmpBbox = imagettfbbox($fontSize, 0, $fontFile, $unicode);
            $tmpX = ($tmpSize / 2) - (($tmpBbox[2] - $tmpBbox[0]) / 2);
            $tmpY = ($tmpSize / 2) + (abs($tmpBbox[7] - $tmpBbox[1]) / 2);
            imagettftext($tmp, $fontSize, 0, (int) $tmpX, (int) $tmpY, $tmpColor, $fontFile, $unicode);

            $rotated = imagerotate($tmp, $rotation, $transparent);
            imagesavealpha($rotated, true);

            $rw = imagesx($rotated);
            $rh = imagesy($rotated);
            imagealphablending($canvas, true);
            imagecopy($canvas, $rotated, $x - (int) ($rw / 2), $y - (int) ($rh / 2), 0, 0, $rw, $rh);
            imagedestroy($tmp);
            imagedestroy($rotated);
        } else {
            imagealphablending($canvas, true);
            imagettftext($canvas, $fontSize, 0, (int) $drawX, (int) $drawY, $textColor, $fontFile, $unicode);
        }
    }

    /**
     * Render image element (placeholder)
     */
    protected function renderImageElement(\GdImage $canvas, array $element, string $imagePath, int $x, int $y, int $canvasWidth): void
    {
        $width = max(1, (int) (($element['width'] ?? 100) * $this->renderScale));
        $height = max(1, (int) (($element['height'] ?? 100) * $this->renderScale));
        $borderRadius = (int) ($element['borderRadius'] ?? 0);
        $opacity = (int) ($element['opacity'] ?? 100);

        // Convert URL to storage path if needed
        $storagePath = $this->extractStoragePath($imagePath);

        // Check if it's a path or placeholder
        if (str_starts_with($imagePath, '[') || empty($storagePath) || !Storage::disk('public')->exists($storagePath)) {
            // For player/person images, fall back to default silhouette instead of placeholder box
            $placeholder = $element['placeholder'] ?? '';
            $defaultImagePath = 'defaults/default-player.png';
            if ($this->shouldRemoveBackground($placeholder) && Storage::disk('public')->exists($defaultImagePath)) {
                $storagePath = $defaultImagePath;
            } else {
                $this->drawPlaceholderBox($canvas, $x, $y, $width, $height, $placeholder ?: 'Image');
                return;
            }
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
            $drawWidth = $width;
            $drawHeight = (int) ($width / $srcRatio);
        } else {
            $drawHeight = $height;
            $drawWidth = (int) ($height * $srcRatio);
        }

        // Center the image at x, y
        $drawX = (int) ($x - $drawWidth / 2);
        $drawY = (int) ($y - $drawHeight / 2);

        if ($opacity < 100) {
            // Render with opacity using temp canvas approach
            $this->renderImageWithOpacity($canvas, $storagePath, $drawX, $drawY, $drawWidth, $drawHeight, $opacity, $borderRadius >= 50 ? $x : null, $borderRadius >= 50 ? $y : null);
        } elseif ($borderRadius >= 50) {
            $diameter = min($drawWidth, $drawHeight);
            $this->addCircularImage($canvas, $storagePath, $x, $y, $diameter);
        } else {
            $this->addImage($canvas, $storagePath, $drawX, $drawY, $drawWidth, $drawHeight);
        }
    }

    /**
     * Render an image with opacity using temp canvas + imagecopymerge
     */
    protected function renderImageWithOpacity(\GdImage $canvas, string $storagePath, int $drawX, int $drawY, int $width, int $height, int $opacity, ?int $circularCx = null, ?int $circularCy = null): void
    {
        $srcImage = $this->loadBackground($storagePath);
        if (!$srcImage) return;

        $srcWidth = imagesx($srcImage);
        $srcHeight = imagesy($srcImage);

        // Create resized image
        $resized = imagecreatetruecolor($width, $height);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent);
        imagecopyresampled($resized, $srcImage, 0, 0, 0, 0, $width, $height, $srcWidth, $srcHeight);
        imagedestroy($srcImage);

        // Apply circular mask if needed
        if ($circularCx !== null) {
            $diameter = min($width, $height);
            $mask = imagecreatetruecolor($width, $height);
            $black = imagecolorallocate($mask, 0, 0, 0);
            $white = imagecolorallocate($mask, 255, 255, 255);
            imagefill($mask, 0, 0, $black);
            imagefilledellipse($mask, (int)($width / 2), (int)($height / 2), $diameter, $diameter, $white);
            for ($px = 0; $px < $width; $px++) {
                for ($py = 0; $py < $height; $py++) {
                    if ((imagecolorat($mask, $px, $py) & 0xFF) === 0) {
                        imagesetpixel($resized, $px, $py, $transparent);
                    }
                }
            }
            imagedestroy($mask);
        }

        // Create temp canvas, copy the target region, overlay image, then merge back with opacity
        $temp = imagecreatetruecolor($width, $height);
        imagealphablending($temp, true);
        imagesavealpha($temp, true);
        // Copy current canvas region to temp
        imagecopy($temp, $canvas, 0, 0, $drawX, $drawY, $width, $height);
        // Copy resized image on top
        imagecopy($temp, $resized, 0, 0, 0, 0, $width, $height);
        // Merge temp back to canvas with opacity
        imagecopymerge($canvas, $temp, $drawX, $drawY, 0, 0, $width, $height, $opacity);

        imagedestroy($resized);
        imagedestroy($temp);
    }

    /**
     * Render uploaded image (stored with template)
     */
    protected function renderUploadedImage(\GdImage $canvas, array $element, int $x, int $y, int $canvasWidth): void
    {
        $path = $element['imagePath'] ?? $element['path'] ?? '';
        $width = (int) (($element['width'] ?? 150) * $this->renderScale);
        $height = (int) (($element['height'] ?? 150) * $this->renderScale);

        if (empty($path) || !Storage::disk('public')->exists($path)) {
            // On real poster generation, a missing uploaded image must be invisible
            // (never an ugly grey box). Only show the placeholder in editor previews.
            if (! $this->skipBlanks) {
                $this->drawPlaceholderBox($canvas, $x, $y, $width, $height, 'Uploaded Image');
            }
            return;
        }

        $drawX = (int) ($x - $width / 2);
        $drawY = (int) ($y - $height / 2);

        // Load and render the uploaded image
        $srcImage = $this->loadBackground($path);
        if (!$srcImage) {
            if (! $this->skipBlanks) {
                $this->drawPlaceholderBox($canvas, $x, $y, $width, $height, 'Uploaded Image');
            }
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
        // Support new format (raw width + scaleX) and old format (pre-scaled width)
        $scaleX = (float) ($element['scaleX'] ?? 1);
        $scaleY = (float) ($element['scaleY'] ?? 1);
        $width = (int) (($element['width'] ?? 150) * $scaleX * $this->renderScale);
        $height = (int) (($element['height'] ?? 100) * $scaleY * $this->renderScale);
        $fill = $element['fill'] ?? 'rgba(99, 102, 241, 0.5)';
        // Apply fill opacity to solid color fills
        $fillOpacity = (float) ($element['fillOpacity'] ?? 1);
        if ($fillOpacity < 1 && is_string($fill) && str_starts_with($fill, '#')) {
            $r = hexdec(substr($fill, 1, 2));
            $g = hexdec(substr($fill, 3, 2));
            $b = hexdec(substr($fill, 5, 2));
            $fill = "rgba($r,$g,$b,$fillOpacity)";
        }
        $stroke = $element['stroke'] ?? '#6366f1';
        $strokeWidth = (int) (($element['strokeWidth'] ?? 2) * $this->renderScale);
        $opacity = (int) ($element['opacity'] ?? 100);
        $shadowData = $element['shadow'] ?? null;

        $drawX = (int) ($x - $width / 2);
        $drawY = (int) ($y - $height / 2);

        // If opacity < 100, render shape onto a temp canvas and merge with opacity
        if ($opacity < 100) {
            $canvasW = imagesx($canvas);
            $canvasH = imagesy($canvas);
            $tempCanvas = imagecreatetruecolor($canvasW, $canvasH);
            imagealphablending($tempCanvas, true);
            imagesavealpha($tempCanvas, true);
            // Copy the current canvas into the temp so the opacity change stays
            // scoped to this shape only. imagecopymerge() ignores the source
            // alpha channel, so filling with transparent black would blend over
            // every other layer and darken the whole poster.
            imagecopy($tempCanvas, $canvas, 0, 0, 0, 0, $canvasW, $canvasH);

            // Render shape at full opacity onto temp canvas, then merge
            $tempElement = $element;
            $tempElement['opacity'] = 100;
            $this->renderShapeElement($tempCanvas, $tempElement, $x, $y, $canvasWidth);

            imagecopymerge($canvas, $tempCanvas, 0, 0, 0, 0, $canvasW, $canvasH, $opacity);
            imagedestroy($tempCanvas);
            return;
        }

        // Draw shadow first if present
        if ($shadowData) {
            $sx = (int) (($shadowData['offsetX'] ?? 0) * $this->renderScale);
            $sy = (int) (($shadowData['offsetY'] ?? 0) * $this->renderScale);
            if ($sx !== 0 || $sy !== 0 || ($shadowData['blur'] ?? 0) > 0) {
                $shadowColor = $this->parseColor($canvas, $shadowData['color'] ?? '#000000');
                $sdx = $drawX + $sx;
                $sdy = $drawY + $sy;
                if ($shapeType === 'rect') {
                    imagefilledrectangle($canvas, $sdx, $sdy, $sdx + $width, $sdy + $height, $shadowColor);
                } elseif ($shapeType === 'circle') {
                    $radius = min($width, $height) / 2;
                    imagefilledellipse($canvas, $x + $sx, $y + $sy, (int)($radius * 2), (int)($radius * 2), $shadowColor);
                } elseif ($shapeType === 'triangle') {
                    $points = [$sdx + $width / 2, $sdy, $sdx, $sdy + $height, $sdx + $width, $sdy + $height];
                    imagefilledpolygon($canvas, $points, $shadowColor);
                } elseif ($shapeType === 'star') {
                    $points = $this->calculateStarPoints(5, $width / 2, $width / 4, $x + $sx, $y + $sy);
                    imagefilledpolygon($canvas, $points, $shadowColor);
                } elseif ($shapeType === 'diamond') {
                    $points = [$x + $sx, $sdy, $sdx + $width, $y + $sy, $x + $sx, $sdy + $height, $sdx, $y + $sy];
                    imagefilledpolygon($canvas, $points, $shadowColor);
                }
            }
        }

        // Check if fill is a gradient config
        $isGradient = is_array($fill) && isset($fill['type']);

        if ($shapeType === 'rect') {
            // Determine border radii: per-corner or uniform from rx/ry
            $borderRadii = $element['borderRadii'] ?? null;
            if ($borderRadii && $this->renderScale !== 1) {
                foreach ($borderRadii as $k => $v) { $borderRadii[$k] = (int)($v * $this->renderScale); }
            }
            $rx = (int) (($element['rx'] ?? 0) * $this->renderScale);
            $ry = (int) (($element['ry'] ?? 0) * $this->renderScale);
            if (!$borderRadii && ($rx > 0 || $ry > 0)) {
                $r = max($rx, $ry);
                $borderRadii = ['tl' => $r, 'tr' => $r, 'br' => $r, 'bl' => $r];
            }
            $hasRadius = $borderRadii && (($borderRadii['tl'] ?? 0) > 0 || ($borderRadii['tr'] ?? 0) > 0 || ($borderRadii['br'] ?? 0) > 0 || ($borderRadii['bl'] ?? 0) > 0);

            if ($hasRadius) {
                $this->renderRoundedRect($canvas, $drawX, $drawY, $width, $height, $borderRadii, $isGradient ? $fill : null, $isGradient ? null : $fill);
            } elseif ($isGradient) {
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
     * Render scorecard table (batting or bowling) on canvas
     */
    protected function renderScorecardTable(\GdImage $canvas, array $element, array $data, int $canvasWidth, int $canvasHeight): void
    {
        $config = $element['scorecardConfig'] ?? [];
        $team = $config['team'] ?? null;
        $scorecardType = $config['scorecardType'] ?? null;

        // Fallback: extract team/type from placeholder (e.g. 'batting_table_a')
        if (!$team || !$scorecardType) {
            $placeholder = $element['placeholder'] ?? '';
            if (preg_match('/^(batting|bowling)_table_(a|b)$/', $placeholder, $m)) {
                $scorecardType = $scorecardType ?? $m[1];
                $team = $team ?? $m[2];
            }
        }
        $team = $team ?? 'a';
        $scorecardType = $scorecardType ?? 'batting';
        $maxRows = (int) ($config['maxRows'] ?? 3);

        // Determine data key
        $dataKey = $scorecardType === 'batting'
            ? ('batting_table_' . $team)
            : ('bowling_table_' . $team);

        $tableData = $data[$dataKey] ?? [];
        if (is_string($tableData)) {
            $tableData = json_decode($tableData, true) ?? [];
        }

        // Style config
        $transparentBg = !empty($config['transparentBg']);
        $headerBg = $config['headerBg'] ?? '#1e40af';
        $headerText = $config['headerText'] ?? '#ffffff';
        $rowBg = $config['rowBg'] ?? '#1e293b';
        $altRowBg = $config['altRowBg'] ?? '#334155';
        $textColor = $config['textColor'] ?? '#ffffff';
        $accentColor = $config['accentColor'] ?? '#FFD700';
        $fontSize = (int) (($config['fontSize'] ?? 14) * $this->renderScale);
        $rowHeight = (int) (($config['rowHeight'] ?? 40) * $this->renderScale);

        // Calculate element area
        $areaWidth = (int) (($element['width'] ?? 400) * $this->renderScale);
        $areaHeight = (int) (($element['height'] ?? 200) * $this->renderScale);
        $centerX = (int) (($element['x'] ?? 50) / 100 * $canvasWidth);
        $centerY = (int) (($element['y'] ?? 50) / 100 * $canvasHeight);
        $areaX = (int) ($centerX - $areaWidth / 2);
        $areaY = (int) ($centerY - $areaHeight / 2);

        // Team name from data
        $teamNameKey = 'team_' . $team . '_name';
        $teamName = $data[$teamNameKey] ?? ('Team ' . strtoupper($team));
        $typeLabel = strtoupper($scorecardType);

        // Header bar height
        $headerHeight = (int) ($rowHeight * 0.85);
        $colHeaderHeight = (int) ($rowHeight * 0.75);
        $headerFont = 'Montserrat-Bold.ttf';
        $bodyFont = 'Montserrat-Medium.ttf';
        $headerFontSize = (int) ($fontSize * 1.1);
        $colFontSize = (int) ($fontSize * 0.85);

        // --- Team header bar ---
        if (!$transparentBg) {
            $hdrColor = $this->parseColor($canvas, $headerBg);
            imagefilledrectangle($canvas, $areaX, $areaY, $areaX + $areaWidth, $areaY + $headerHeight, $hdrColor);
            // Accent line at bottom of header
            $accentParsed = $this->parseColor($canvas, $accentColor);
            imagefilledrectangle($canvas, $areaX, $areaY + $headerHeight - (2 * $this->renderScale), $areaX + $areaWidth, $areaY + $headerHeight, $accentParsed);
        }
        // Team name text
        $this->addText($canvas, strtoupper($teamName) . ' - ' . $typeLabel, $areaX + (int) ($areaWidth * 0.04), $areaY + (int) ($headerHeight * 0.65), $headerFontSize, $headerText, $headerFont, 'left');

        $currentY = $areaY + $headerHeight;

        // --- Column headers ---
        if (!$transparentBg) {
            $colHdrColor = $this->parseColor($canvas, $this->darkenColorHex($headerBg, 15));
            imagefilledrectangle($canvas, $areaX, $currentY, $areaX + $areaWidth, $currentY + $colHeaderHeight, $colHdrColor);
        }

        if ($scorecardType === 'batting') {
            $cols = $this->getScorecardBattingColumns($areaX, $areaWidth);
            $this->addText($canvas, 'Name', $areaX + $cols['name'], $currentY + (int) ($colHeaderHeight * 0.65), $colFontSize, $headerText, $headerFont, 'left');
            $this->addText($canvas, 'R', $areaX + $cols['c1'], $currentY + (int) ($colHeaderHeight * 0.65), $colFontSize, $headerText, $headerFont, 'center');
            $this->addText($canvas, 'B', $areaX + $cols['c2'], $currentY + (int) ($colHeaderHeight * 0.65), $colFontSize, $headerText, $headerFont, 'center');
            $this->addText($canvas, '4s', $areaX + $cols['c3'], $currentY + (int) ($colHeaderHeight * 0.65), $colFontSize, $headerText, $headerFont, 'center');
            $this->addText($canvas, '6s', $areaX + $cols['c4'], $currentY + (int) ($colHeaderHeight * 0.65), $colFontSize, $headerText, $headerFont, 'center');
        } else {
            $cols = $this->getScorecardBowlingColumns($areaX, $areaWidth);
            $this->addText($canvas, 'Name', $areaX + $cols['name'], $currentY + (int) ($colHeaderHeight * 0.65), $colFontSize, $headerText, $headerFont, 'left');
            $this->addText($canvas, 'O', $areaX + $cols['c1'], $currentY + (int) ($colHeaderHeight * 0.65), $colFontSize, $headerText, $headerFont, 'center');
            $this->addText($canvas, 'R', $areaX + $cols['c2'], $currentY + (int) ($colHeaderHeight * 0.65), $colFontSize, $headerText, $headerFont, 'center');
            $this->addText($canvas, 'W', $areaX + $cols['c3'], $currentY + (int) ($colHeaderHeight * 0.65), $colFontSize, $headerText, $headerFont, 'center');
            $this->addText($canvas, 'Econ', $areaX + $cols['c4'], $currentY + (int) ($colHeaderHeight * 0.65), $colFontSize, $headerText, $headerFont, 'center');
        }

        $currentY += $colHeaderHeight;

        // --- Data rows ---
        $rows = array_slice($tableData, 0, $maxRows);
        if (empty($rows)) {
            // No data placeholder
            if (!$transparentBg) {
                $noBg = $this->parseColor($canvas, $rowBg);
                imagefilledrectangle($canvas, $areaX, $currentY, $areaX + $areaWidth, $currentY + $rowHeight, $noBg);
            }
            $this->addText($canvas, 'No scorecard data', $areaX + (int) ($areaWidth / 2), $currentY + (int) ($rowHeight * 0.6), $fontSize, '#888888', $bodyFont, 'center');
            return;
        }

        foreach ($rows as $index => $row) {
            if (!$transparentBg) {
                $bgColor = ($index % 2 === 0) ? $rowBg : $altRowBg;
                $rowColor = $this->parseColor($canvas, $bgColor);
                imagefilledrectangle($canvas, $areaX, $currentY, $areaX + $areaWidth, $currentY + $rowHeight, $rowColor);
            }

            $textY = $currentY + (int) ($rowHeight * 0.62);

            if ($scorecardType === 'batting') {
                $cols = $this->getScorecardBattingColumns($areaX, $areaWidth);
                $name = mb_substr($row['name'] ?? '', 0, 18);
                $this->addText($canvas, $name, $areaX + $cols['name'], $textY, $fontSize, $textColor, $bodyFont, 'left');
                $this->addText($canvas, (string) ($row['runs'] ?? '-'), $areaX + $cols['c1'], $textY, $fontSize, $accentColor, $headerFont, 'center');
                $this->addText($canvas, (string) ($row['balls'] ?? '-'), $areaX + $cols['c2'], $textY, $fontSize, $textColor, $bodyFont, 'center');
                $this->addText($canvas, (string) ($row['fours'] ?? '-'), $areaX + $cols['c3'], $textY, $fontSize, $textColor, $bodyFont, 'center');
                $this->addText($canvas, (string) ($row['sixes'] ?? '-'), $areaX + $cols['c4'], $textY, $fontSize, $textColor, $bodyFont, 'center');
            } else {
                $cols = $this->getScorecardBowlingColumns($areaX, $areaWidth);
                $name = mb_substr($row['name'] ?? '', 0, 18);
                $this->addText($canvas, $name, $areaX + $cols['name'], $textY, $fontSize, $textColor, $bodyFont, 'left');
                $this->addText($canvas, (string) ($row['overs'] ?? '-'), $areaX + $cols['c1'], $textY, $fontSize, $textColor, $bodyFont, 'center');
                $this->addText($canvas, (string) ($row['runs'] ?? '-'), $areaX + $cols['c2'], $textY, $fontSize, $textColor, $bodyFont, 'center');
                $this->addText($canvas, (string) ($row['wickets'] ?? '-'), $areaX + $cols['c3'], $textY, $fontSize, $accentColor, $headerFont, 'center');
                $this->addText($canvas, (string) ($row['economy'] ?? '-'), $areaX + $cols['c4'], $textY, $fontSize, $textColor, $bodyFont, 'center');
            }

            // Subtle divider
            if (!$transparentBg && $index < count($rows) - 1) {
                $divider = imagecolorallocatealpha($canvas, 255, 255, 255, 110);
                imageline($canvas, $areaX + 4, $currentY + $rowHeight - 1, $areaX + $areaWidth - 4, $currentY + $rowHeight - 1, $divider);
            }

            $currentY += $rowHeight;
        }
    }

    /**
     * Get column positions for batting scorecard
     */
    protected function getScorecardBattingColumns(int $areaX, int $areaWidth): array
    {
        return [
            'name' => (int) ($areaWidth * 0.04),
            'c1' => (int) ($areaWidth * 0.60),  // R
            'c2' => (int) ($areaWidth * 0.72),  // B
            'c3' => (int) ($areaWidth * 0.84),  // 4s
            'c4' => (int) ($areaWidth * 0.94),  // 6s
        ];
    }

    /**
     * Get column positions for bowling scorecard
     */
    protected function getScorecardBowlingColumns(int $areaX, int $areaWidth): array
    {
        return [
            'name' => (int) ($areaWidth * 0.04),
            'c1' => (int) ($areaWidth * 0.55),  // O
            'c2' => (int) ($areaWidth * 0.66),  // R
            'c3' => (int) ($areaWidth * 0.77),  // W
            'c4' => (int) ($areaWidth * 0.90),  // Econ
        ];
    }

    /**
     * Darken a hex color by percentage
     */
    protected function darkenColorHex(string $hex, int $percent): string
    {
        $rgb = $this->hexToRgb($hex);
        $factor = 1 - ($percent / 100);
        return sprintf('#%02x%02x%02x',
            max(0, (int) ($rgb['r'] * $factor)),
            max(0, (int) ($rgb['g'] * $factor)),
            max(0, (int) ($rgb['b'] * $factor))
        );
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
     * Render a rectangle with per-corner border radius using arcs + rectangles
     */
    protected function renderRoundedRect(\GdImage $canvas, int $x, int $y, int $w, int $h, array $radii, ?array $gradientConfig = null, $solidFill = null): void
    {
        if ($w <= 0 || $h <= 0) return;

        // Clamp radii to half the min dimension
        $maxR = (int) (min($w, $h) / 2);
        $tl = min((int) ($radii['tl'] ?? 0), $maxR);
        $tr = min((int) ($radii['tr'] ?? 0), $maxR);
        $br = min((int) ($radii['br'] ?? 0), $maxR);
        $bl = min((int) ($radii['bl'] ?? 0), $maxR);

        // Create a mask image for the rounded rect shape (white = inside)
        $mask = imagecreatetruecolor($w, $h);
        $black = imagecolorallocate($mask, 0, 0, 0);
        $white = imagecolorallocate($mask, 255, 255, 255);
        imagefill($mask, 0, 0, $black);

        // Fill full rect in white
        imagefilledrectangle($mask, 0, 0, $w, $h, $white);

        // Cut out corners with black, then draw arcs back in white
        // Top-left corner
        if ($tl > 0) {
            imagefilledrectangle($mask, 0, 0, $tl - 1, $tl - 1, $black);
            imagefilledarc($mask, $tl, $tl, $tl * 2, $tl * 2, 180, 270, $white, IMG_ARC_PIE);
        }
        // Top-right corner
        if ($tr > 0) {
            imagefilledrectangle($mask, $w - $tr, 0, $w, $tr - 1, $black);
            imagefilledarc($mask, $w - $tr - 1, $tr, $tr * 2, $tr * 2, 270, 360, $white, IMG_ARC_PIE);
        }
        // Bottom-right corner
        if ($br > 0) {
            imagefilledrectangle($mask, $w - $br, $h - $br, $w, $h, $black);
            imagefilledarc($mask, $w - $br - 1, $h - $br - 1, $br * 2, $br * 2, 0, 90, $white, IMG_ARC_PIE);
        }
        // Bottom-left corner
        if ($bl > 0) {
            imagefilledrectangle($mask, 0, $h - $bl, $bl - 1, $h, $black);
            imagefilledarc($mask, $bl, $h - $bl - 1, $bl * 2, $bl * 2, 90, 180, $white, IMG_ARC_PIE);
        }

        // Create temp image with the fill
        $temp = imagecreatetruecolor($w, $h);
        imagealphablending($temp, false);
        imagesavealpha($temp, true);
        $transparent = imagecolorallocatealpha($temp, 0, 0, 0, 127);
        imagefill($temp, 0, 0, $transparent);
        imagealphablending($temp, true);

        if ($gradientConfig) {
            $this->renderGradientRect($temp, 0, 0, $w, $h, $gradientConfig);
        } else {
            $color = $this->parseColor($temp, $solidFill ?? '#6366f1');
            imagefilledrectangle($temp, 0, 0, $w, $h, $color);
        }

        // Apply mask: set pixels outside the rounded rect to transparent
        imagealphablending($temp, false);
        for ($px = 0; $px < $w; $px++) {
            for ($py = 0; $py < $h; $py++) {
                $maskPixel = imagecolorat($mask, $px, $py) & 0xFF;
                if ($maskPixel === 0) {
                    imagesetpixel($temp, $px, $py, $transparent);
                }
            }
        }
        imagedestroy($mask);

        // Composite onto main canvas
        imagealphablending($canvas, true);
        imagecopy($canvas, $temp, $x, $y, 0, 0, $w, $h);
        imagedestroy($temp);
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
        // Skip hidden overlays
        if (!empty($overlay['hidden'])) {
            return;
        }

        $path = $overlay['path'] ?? '';

        if (empty($path) || !Storage::disk('public')->exists($path)) {
            return; // Skip if overlay image doesn't exist
        }

        // Calculate position from percentage
        $x = (int) (($overlay['x'] ?? 50) / 100 * $canvasWidth);
        $y = (int) (($overlay['y'] ?? 50) / 100 * $canvasHeight);

        // Overlay dimensions are stored as actual pixel values
        $width = (int) (($overlay['width'] ?? 100) * $this->renderScale);
        $height = (int) (($overlay['height'] ?? 100) * $this->renderScale);

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

        // Apply opacity if specified
        $opacity = (int) ($overlay['opacity'] ?? 100);
        if ($opacity < 100) {
            $rw = imagesx($resized);
            $rh = imagesy($resized);
            $temp = imagecreatetruecolor($rw, $rh);
            imagealphablending($temp, true);
            imagesavealpha($temp, true);
            imagecopy($temp, $canvas, 0, 0, $drawX, $drawY, $rw, $rh);
            imagecopy($temp, $resized, 0, 0, 0, 0, $rw, $rh);
            imagecopymerge($canvas, $temp, $drawX, $drawY, 0, 0, $rw, $rh, $opacity);
            imagedestroy($temp);
        } else {
            // Copy to canvas with alpha blending
            imagealphablending($canvas, true);
            imagecopy($canvas, $resized, $drawX, $drawY, 0, 0, imagesx($resized), imagesy($resized));
        }

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
            'best_batsman_image',
            'best_bowler_image',
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

        // Player cut-outs tend to look flat/low-contrast after background removal
        // and scaling, so give them a mild auto-enhance (contrast + brightness).
        return $this->enhancePlayerImage($noBgPath ?? $storagePath);
    }

    /**
     * Apply a subtle, alpha-preserving contrast/brightness boost to a player
     * photo so it reads more punchy on the poster. The result is cached on the
     * public disk keyed by source path + mtime. Falls back to the original path
     * on any failure so rendering never breaks.
     */
    protected function enhancePlayerImage(string $storagePath): string
    {
        try {
            if (!Storage::disk('public')->exists($storagePath)) {
                return $storagePath;
            }

            $fullSource = Storage::disk('public')->path($storagePath);
            $cacheDir = 'poster-cache/enhanced';
            $cachePath = $cacheDir . '/' . md5($storagePath . '|' . @filemtime($fullSource)) . '.png';

            if (Storage::disk('public')->exists($cachePath)) {
                return $cachePath;
            }

            $img = $this->loadBackground($storagePath);
            if (!$img) {
                return $storagePath;
            }

            // Preserve transparency through the filters.
            imagealphablending($img, false);
            imagesavealpha($img, true);

            // In GD a NEGATIVE contrast value increases contrast.
            imagefilter($img, IMG_FILTER_CONTRAST, -14);
            // Small brightness lift to stop the contrast boost crushing shadows.
            imagefilter($img, IMG_FILTER_BRIGHTNESS, 6);

            Storage::disk('public')->makeDirectory($cacheDir);
            $tmp = tempnam(sys_get_temp_dir(), 'enh') . '.png';
            imagepng($img, $tmp);
            imagedestroy($img);

            Storage::disk('public')->put($cachePath, file_get_contents($tmp));
            @unlink($tmp);

            return $cachePath;
        } catch (\Throwable $e) {
            \Log::warning('Player image enhancement failed: ' . $e->getMessage());
            return $storagePath;
        }
    }

    /**
     * Get font file based on weight and style
     */
    protected function getFontFile(string $weight, string $fontStyle = 'normal', string $fontFamily = 'Montserrat'): string
    {
        $w = (int) $weight;

        // 1. Installed fonts (Font Manager) win — resolved to the SAME TTF the
        //    editor previews with, so generated posters match the live preview.
        try {
            $this->fontService ??= app(\App\Services\Fonts\FontService::class);
            $installed = $this->fontService->resolveFontFile($fontFamily, $w, $fontStyle);
            if ($installed) {
                return $installed;
            }
        } catch (\Throwable $e) {
            // Fall through to built-in fonts if anything goes wrong.
        }

        // 2. Built-in fonts shipped in public/fonts. Resolution is delegated to
        //    FontService::builtinFontFile() — the SAME mapping the editor's
        //    @font-face CSS uses — so the live preview and the generated poster
        //    pick the identical TTF for any (family, weight).
        if ($fontStyle === 'italic') {
            $italic = $this->fontService?->builtinFontFile($fontFamily, $w, 'italic');
            if ($italic && str_contains($italic, 'Italic')) {
                return $italic;
            }
            // Families without true italics fall back to Montserrat italic.
            return $w >= 600 ? 'Montserrat-BoldItalic.ttf' : 'Montserrat-Italic.ttf';
        }

        $builtin = $this->fontService?->builtinFontFile($fontFamily, $w, 'normal');
        if ($builtin) {
            return $builtin;
        }

        // 3. Default fallback for unknown families.
        return $w >= 700 ? 'Montserrat-Bold.ttf' : 'Montserrat-Medium.ttf';
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
            'team_a_score' => '150/6 (20.0)',
            'team_b_score' => '145/8 (20.0)',
            'team_a_score_wickets' => '150/6',
            'team_b_score_wickets' => '145/8',
            'team_a_runs' => '150',
            'team_b_runs' => '145',
            'team_a_wickets' => '6',
            'team_b_wickets' => '8',
            'team_a_overs' => '20.0',
            'team_b_overs' => '20.0',
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
            'result_summary' => 'MCC won by 5 runs',
            'winner_name' => 'MCC',
            'win_margin' => 'Won by 5 runs',
            'toss_result' => 'MCC won toss, chose to bat',
            'man_of_the_match_name' => 'Player Name',
            // Man of the Match stats
            'man_of_the_match_runs' => '59',
            'man_of_the_match_balls' => '36',
            'man_of_the_match_fours' => '9',
            'man_of_the_match_sixes' => '1',
            'man_of_the_match_overs' => '4',
            'man_of_the_match_wickets' => '2',
            'man_of_the_match_bowling_runs' => '25',
            'man_of_the_match_maidens' => '0',
            'man_of_the_match_batting_figures' => '59 (36) 9x4 1x6',
            'man_of_the_match_bowling_figures' => '4 - 0 - 25 - 2',
            'best_batsman_name' => 'Best Batsman',
            // Best Batsman stats
            'best_batsman_runs' => '75',
            'best_batsman_balls' => '45',
            'best_batsman_fours' => '8',
            'best_batsman_sixes' => '3',
            'best_batsman_batting_figures' => '75 (45) 8x4 3x6',
            'best_bowler_name' => 'Best Bowler',
            // Best Bowler stats
            'best_bowler_overs' => '4',
            'best_bowler_wickets' => '3',
            'best_bowler_bowling_runs' => '18',
            'best_bowler_maidens' => '1',
            'best_bowler_bowling_figures' => '4 - 1 - 18 - 3',
            'match_details' => 'Team A vs Team B',
            // Player info
            'player_type' => 'All Rounder',
            'batting_style' => 'Right Handed',
            'bowling_style' => 'Right Arm Medium',
            'award_name' => 'Player of the Match',
            'achievement_text' => '75 runs off 45 balls',
            'batting_figures' => '59 (36) 9x4 1x6',
            'bowling_figures' => '4 - 0 - 25 - 2',
            // Individual batting stats
            'batting_runs' => '59',
            'batting_balls' => '36',
            'batting_fours' => '9',
            'batting_sixes' => '1',
            // Individual bowling stats
            'bowling_overs' => '4',
            'bowling_runs' => '25',
            'bowling_maidens' => '0',
            'bowling_wickets' => '2',
            // Combined score+overs
            'team_a_score_overs' => '150/6 (20 Ov)',
            'team_b_score_overs' => '145/8 (20 Ov)',
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
            if ($placeholder === 'batting_table_a' || $placeholder === 'batting_table_b') {
                $data[$placeholder] = $customData[$placeholder] ?? $this->getSampleBattingData();
                continue;
            }
            if ($placeholder === 'bowling_table_a' || $placeholder === 'bowling_table_b') {
                $data[$placeholder] = $customData[$placeholder] ?? $this->getSampleBowlingData();
                continue;
            }
            if ($placeholder === 'fixture_area') {
                $data[$placeholder] = $customData[$placeholder] ?? $this->getSampleFixtureData();
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
     * Get sample batting scorecard data for preview
     */
    protected function getSampleBattingData(): array
    {
        return [
            ['name' => 'Virat K.', 'runs' => 72, 'balls' => 45, 'fours' => 8, 'sixes' => 3],
            ['name' => 'Rohit S.', 'runs' => 56, 'balls' => 38, 'fours' => 6, 'sixes' => 2],
            ['name' => 'KL Rahul', 'runs' => 41, 'balls' => 30, 'fours' => 4, 'sixes' => 1],
        ];
    }

    /**
     * Get sample bowling scorecard data for preview
     */
    protected function getSampleBowlingData(): array
    {
        return [
            ['name' => 'Jasprit B.', 'overs' => '4.0', 'runs' => 24, 'wickets' => 3, 'economy' => '6.00'],
            ['name' => 'Mohammed S.', 'overs' => '4.0', 'runs' => 32, 'wickets' => 2, 'economy' => '8.00'],
            ['name' => 'Ravindra J.', 'overs' => '3.0', 'runs' => 22, 'wickets' => 1, 'economy' => '7.33'],
        ];
    }

    /**
     * Get sample fixture data for preview
     */
    protected function getSampleFixtureData(): array
    {
        return [
            ['team_a' => 'Royal Strikers', 'team_b' => 'Thunder Kings', 'team_a_logo' => '', 'team_b_logo' => '', 'date' => 'Jun 15, 2026', 'time' => '06:00 PM', 'venue' => 'City Stadium', 'match_number' => '1'],
            ['team_a' => 'Mountrich CC', 'team_b' => 'Canadian CC', 'team_a_logo' => '', 'team_b_logo' => '', 'date' => 'Jun 16, 2026', 'time' => '06:00 PM', 'venue' => 'City Stadium', 'match_number' => '2'],
            ['team_a' => 'Thunder Kings', 'team_b' => 'Canadian CC', 'team_a_logo' => '', 'team_b_logo' => '', 'date' => 'Jun 17, 2026', 'time' => '06:00 PM', 'venue' => 'Sports Ground', 'match_number' => '3'],
            ['team_a' => 'Royal Strikers', 'team_b' => 'Mountrich CC', 'team_a_logo' => '', 'team_b_logo' => '', 'date' => 'Jun 18, 2026', 'time' => '06:00 PM', 'venue' => 'Sports Ground', 'match_number' => '4'],
            ['team_a' => 'Canadian CC', 'team_b' => 'Royal Strikers', 'team_a_logo' => '', 'team_b_logo' => '', 'date' => 'Jun 19, 2026', 'time' => '06:00 PM', 'venue' => 'City Stadium', 'match_number' => '5'],
        ];
    }

    /**
     * Render fixture area — dispatches to row or card layout
     */
    protected function renderFixtureArea(\GdImage $canvas, array $element, array $data, int $canvasWidth, int $canvasHeight): void
    {
        $config = $element['fixtureConfig'] ?? [];
        $fixtures = $data['fixture_area'] ?? [];
        if (is_string($fixtures)) {
            $fixtures = json_decode($fixtures, true) ?? [];
        }

        $layout = $config['layout'] ?? 'row';

        if ($layout === 'card') {
            $this->renderFixtureCards($canvas, $element, $config, $fixtures, $canvasWidth, $canvasHeight);
        } else {
            $this->renderFixtureRows($canvas, $element, $config, $fixtures, $canvasWidth, $canvasHeight);
        }
    }

    /**
     * Render fixture area as row list
     * Layout: [Team A logo + name] — [Date / Time / Venue center] — [Team B name + logo]
     */
    protected function renderFixtureRows(\GdImage $canvas, array $element, array $config, array $fixtures, int $canvasWidth, int $canvasHeight): void
    {
        $maxRows = (int) ($config['maxRows'] ?? 10);
        $transparentBg = ($config['transparentBg'] ?? true);
        $rowBg = $config['rowBg'] ?? '#0a1628';
        $altRowBg = $config['altRowBg'] ?? '#0f1d33';
        $textColor = $config['textColor'] ?? '#ffffff';
        $accentColor = $config['accentColor'] ?? '#d4a843';
        $mutedColor = $config['mutedColor'] ?? '#8899aa';
        $dividerColor = $config['dividerColor'] ?? $accentColor;
        $fontSize = (int) (($config['fontSize'] ?? 16) * $this->renderScale);
        $rowHeight = (int) (($config['rowHeight'] ?? 100) * $this->renderScale);

        // Toggle options
        $showTeamLogo = $config['showTeamLogo'] ?? true;
        $useShortName = !empty($config['useShortName']);
        $showMatchNum = !empty($config['showMatchNum']);
        $showVenue = $config['showVenue'] ?? true;
        $showDateTime = $config['showDateTime'] ?? true;
        $cardStyle = $config['cardStyle'] ?? 'flat';
        $showBorder = !empty($config['showBorder']);
        $rowGap = (int)(($config['rowGap'] ?? 4) * $this->renderScale);
        $rowPadding = (int)(($config['rowPadding'] ?? 16) * $this->renderScale);

        // Calculate element area
        $hasExplicitSize = isset($element['width']) && isset($element['height']);
        $defaultWidth = (int) ($canvasWidth * 0.88 / $this->renderScale);
        $areaWidth = (int) (($element['width'] ?? $defaultWidth) * $this->renderScale);

        if ($hasExplicitSize) {
            $areaHeight = (int) ($element['height'] * $this->renderScale);
            $centerX = (int) (($element['x'] ?? 50) / 100 * $canvasWidth);
            $centerY = (int) (($element['y'] ?? 50) / 100 * $canvasHeight);
            $areaX = (int) ($centerX - $areaWidth / 2);
            $areaY = (int) ($centerY - $areaHeight / 2);
        } else {
            $areaX = (int) ((($element['x'] ?? 50) / 100 * $canvasWidth) - $areaWidth / 2);
            $areaY = (int) (($element['y'] ?? 50) / 100 * $canvasHeight);
            $areaHeight = $canvasHeight - $areaY;
        }

        $boldFont = 'Montserrat-Bold.ttf';
        $mediumFont = 'Montserrat-Medium.ttf';
        $regularFont = 'Montserrat-Regular.ttf';
        $scale = $this->renderScale;

        // Font sizes
        $teamNameSize = (int) ($fontSize * 1.0);
        $dateSize = (int) ($fontSize * 0.78);
        $timeSize = (int) ($fontSize * 0.72);
        $venueSize = (int) ($fontSize * 0.65);
        $matchNumSize = (int) ($fontSize * 0.6);
        $logoDiameter = (int) ($rowHeight * 0.50);
        $padding = $rowPadding;

        $currentY = $areaY;
        $rows = array_slice($fixtures, 0, $maxRows);

        if (empty($rows)) {
            $this->addText($canvas, 'No upcoming fixtures', $areaX + (int)($areaWidth / 2), $currentY + (int)(40 * $scale), $fontSize, $mutedColor, $mediumFont, 'center');
            return;
        }

        // Dynamic zone widths based on what's visible
        $hasCenter = $showDateTime || $showVenue;
        if ($hasCenter) {
            $teamZoneW = (int)($areaWidth * 0.35);
            $centerZoneW = $areaWidth - (2 * $teamZoneW);
        } else {
            // No center content — teams get more space with VS in middle
            $teamZoneW = (int)($areaWidth * 0.45);
            $centerZoneW = $areaWidth - (2 * $teamZoneW);
        }

        $midX = $areaX + (int)($areaWidth / 2);
        $leftZoneEnd = $areaX + $teamZoneW;
        $rightZoneStart = $areaX + $teamZoneW + $centerZoneW;

        foreach ($rows as $index => $fixture) {
            $rowTop = $currentY;
            $rowBottom = $rowTop + $rowHeight;
            if ($rowBottom > $areaY + $areaHeight) break;

            $teamAName = $useShortName ? ($fixture['team_a_short'] ?? $fixture['team_a'] ?? 'TBD') : ($fixture['team_a'] ?? 'TBD');
            $teamBName = $useShortName ? ($fixture['team_b_short'] ?? $fixture['team_b'] ?? 'TBD') : ($fixture['team_b'] ?? 'TBD');
            $dateStr = $fixture['date'] ?? '';
            $timeStr = $fixture['time'] ?? '';
            $venue = $fixture['venue'] ?? '';
            $matchNum = $fixture['match_number'] ?? ($index + 1);
            $rowMidY = $rowTop + (int)($rowHeight / 2);

            // === ROW BACKGROUND & STYLE ===
            if (!$transparentBg) {
                $bgHex = ($index % 2 === 0) ? $rowBg : $altRowBg;
                if ($cardStyle === 'gradient') {
                    // Gradient: darker at top, lighter at bottom
                    $bgRgb = $this->hexToRgb($bgHex);
                    for ($gy = $rowTop; $gy < $rowBottom; $gy++) {
                        $progress = ($gy - $rowTop) / max(1, $rowBottom - $rowTop);
                        $r = min(255, (int)($bgRgb['r'] + (30 * $progress)));
                        $g = min(255, (int)($bgRgb['g'] + (30 * $progress)));
                        $b = min(255, (int)($bgRgb['b'] + (30 * $progress)));
                        $lineCol = imagecolorallocate($canvas, $r, $g, $b);
                        imageline($canvas, $areaX, $gy, $areaX + $areaWidth, $gy, $lineCol);
                    }
                } else {
                    imagefilledrectangle($canvas, $areaX, $rowTop, $areaX + $areaWidth, $rowBottom, $this->parseColor($canvas, $bgHex));
                }
            }

            // === ROW EFFECTS ===
            if ($cardStyle === 'stripe') {
                // Alternating left/right accent side bars
                $barW = (int)(4 * $scale);
                if ($index % 2 === 0) {
                    imagefilledrectangle($canvas, $areaX, $rowTop, $areaX + $barW, $rowBottom, $this->parseColor($canvas, $accentColor));
                } else {
                    imagefilledrectangle($canvas, $areaX + $areaWidth - $barW, $rowTop, $areaX + $areaWidth, $rowBottom, $this->parseColor($canvas, $accentColor));
                }
            } elseif ($cardStyle === 'glow') {
                // Accent glow line at bottom of each row
                $glowRgb = $this->hexToRgb($accentColor);
                $lineInset = (int)(20 * $scale);
                for ($g = 0; $g < 3; $g++) {
                    $alpha = 60 + ($g * 25);
                    $glowCol = imagecolorallocatealpha($canvas, $glowRgb['r'], $glowRgb['g'], $glowRgb['b'], $alpha);
                    imageline($canvas, $areaX + $lineInset, $rowBottom - $g, $areaX + $areaWidth - $lineInset, $rowBottom - $g, $glowCol);
                }
            }

            // === BORDER (independent toggle) ===
            if ($showBorder) {
                imagerectangle($canvas, $areaX, $rowTop, $areaX + $areaWidth, $rowBottom, $this->parseColor($canvas, $accentColor));
            }

            // === MATCH NUMBER (small badge at top-left of row) ===
            if ($showMatchNum) {
                $badgeText = 'Match ' . $matchNum;
                $this->addText($canvas, $badgeText, $areaX + $padding, $rowTop + (int)(10 * $scale), $matchNumSize, $mutedColor, $regularFont, 'left');
            }

            // === LEFT: Team A (name + logo, right-aligned toward center) ===
            if ($showTeamLogo) {
                $logoAX = $leftZoneEnd - $padding - (int)($logoDiameter / 2);
                if (!empty($fixture['team_a_logo'])) {
                    $this->addCircularImage($canvas, $fixture['team_a_logo'], $logoAX, $rowMidY, $logoDiameter);
                } else {
                    imagefilledellipse($canvas, $logoAX, $rowMidY, $logoDiameter, $logoDiameter, $this->parseColor($canvas, '#1e3a5f'));
                    $this->addText($canvas, mb_substr($teamAName, 0, 1), $logoAX, $rowMidY, (int)($logoDiameter * 0.38), '#ffffff', $boldFont, 'center');
                }
                $nameAX = $logoAX - (int)($logoDiameter / 2) - (int)(10 * $scale);
                $this->addText($canvas, $teamAName, $nameAX, $rowMidY, $teamNameSize, $textColor, $boldFont, 'right');
            } else {
                // No logo — name right-aligned at zone end
                $nameAX = $leftZoneEnd - $padding;
                $this->addText($canvas, $teamAName, $nameAX, $rowMidY, $teamNameSize, $textColor, $boldFont, 'right');
            }

            // === CENTER: Date, Time, Venue (stacked vertically) ===
            if ($hasCenter) {
                $centerLineY = $rowMidY;
                $lineGap = (int)(4 * $scale);
                $lines = [];

                if ($showDateTime && $dateStr) $lines[] = ['text' => $dateStr, 'size' => $dateSize, 'color' => $accentColor, 'font' => $boldFont];
                if ($showDateTime && $timeStr) $lines[] = ['text' => $timeStr, 'size' => $timeSize, 'color' => $textColor, 'font' => $mediumFont];
                if ($showVenue && $venue) $lines[] = ['text' => $venue, 'size' => $venueSize, 'color' => $mutedColor, 'font' => $regularFont];

                if (!empty($lines)) {
                    $totalHeight = 0;
                    foreach ($lines as $line) {
                        $totalHeight += $line['size'] + $lineGap;
                    }
                    $totalHeight -= $lineGap;
                    $startY = $centerLineY - (int)($totalHeight / 2) + (int)($lines[0]['size'] / 2);

                    foreach ($lines as $line) {
                        $this->addText($canvas, $line['text'], $midX, $startY, $line['size'], $line['color'], $line['font'], 'center');
                        $startY += $line['size'] + $lineGap;
                    }
                }
            } else {
                // No date/venue — just render VS in center
                $this->addText($canvas, 'VS', $midX, $rowMidY, (int)($fontSize * 0.9), $accentColor, $boldFont, 'center');
            }

            // === RIGHT: Team B (logo + name, left-aligned from center) ===
            if ($showTeamLogo) {
                $logoBX = $rightZoneStart + $padding + (int)($logoDiameter / 2);
                if (!empty($fixture['team_b_logo'])) {
                    $this->addCircularImage($canvas, $fixture['team_b_logo'], $logoBX, $rowMidY, $logoDiameter);
                } else {
                    imagefilledellipse($canvas, $logoBX, $rowMidY, $logoDiameter, $logoDiameter, $this->parseColor($canvas, '#1e3a5f'));
                    $this->addText($canvas, mb_substr($teamBName, 0, 1), $logoBX, $rowMidY, (int)($logoDiameter * 0.38), '#ffffff', $boldFont, 'center');
                }
                $nameBX = $logoBX + (int)($logoDiameter / 2) + (int)(10 * $scale);
                $this->addText($canvas, $teamBName, $nameBX, $rowMidY, $teamNameSize, $textColor, $boldFont, 'left');
            } else {
                $nameBX = $rightZoneStart + $padding;
                $this->addText($canvas, $teamBName, $nameBX, $rowMidY, $teamNameSize, $textColor, $boldFont, 'left');
            }

            // === DIVIDER LINE ===
            if ($index < count($rows) - 1 && $rowGap < (int)(2 * $scale)) {
                $divRgb = $this->hexToRgb($dividerColor);
                $divCol = imagecolorallocatealpha($canvas, $divRgb['r'], $divRgb['g'], $divRgb['b'], 90);
                $lineY = $rowBottom;
                $lineInset = (int)(30 * $scale);
                imageline($canvas, $areaX + $lineInset, $lineY, $areaX + $areaWidth - $lineInset, $lineY, $divCol);
            }

            $currentY += $rowHeight + $rowGap;
        }
    }

    /**
     * Render fixture area as card grid layout
     * Each card: [Match N] header bar, Team A logo + name VS Team B logo + name, date/time, venue
     */
    protected function renderFixtureCards(\GdImage $canvas, array $element, array $config, array $fixtures, int $canvasWidth, int $canvasHeight): void
    {
        $maxItems = (int) ($config['maxRows'] ?? 6);
        $columns = (int) ($config['cardColumns'] ?? 2);
        $transparentBg = !empty($config['transparentBg']);
        $rowBg = $config['rowBg'] ?? '#0f1d33';
        $textColor = $config['textColor'] ?? '#ffffff';
        $accentColor = $config['accentColor'] ?? '#d4a843';
        $mutedColor = $config['mutedColor'] ?? '#8899aa';
        $headerBg = $config['headerBg'] ?? '#1e40af';
        $headerText = $config['headerText'] ?? '#ffffff';
        $fontSize = (int) (($config['fontSize'] ?? 14) * $this->renderScale);

        // Toggle options
        $showTeamLogo = $config['showTeamLogo'] ?? true;
        $useShortName = !empty($config['useShortName']);
        $showMatchNum = $config['showMatchNum'] ?? false;
        $showVenue = $config['showVenue'] ?? true;
        $showDateTime = $config['showDateTime'] ?? true;
        $cardStyle = $config['cardStyle'] ?? 'flat';
        $showBorder = !empty($config['showBorder']);
        $rowGap = (int)(($config['rowGap'] ?? 4) * $this->renderScale);
        $rowPadding = (int)(($config['rowPadding'] ?? 16) * $this->renderScale);

        // Calculate element area
        $hasExplicitSize = isset($element['width']) && isset($element['height']);
        $defaultWidth = (int) ($canvasWidth * 0.88 / $this->renderScale);
        $areaWidth = (int) (($element['width'] ?? $defaultWidth) * $this->renderScale);

        if ($hasExplicitSize) {
            $areaHeight = (int) ($element['height'] * $this->renderScale);
            $centerX = (int) (($element['x'] ?? 50) / 100 * $canvasWidth);
            $centerY = (int) (($element['y'] ?? 50) / 100 * $canvasHeight);
            $areaX = (int) ($centerX - $areaWidth / 2);
            $areaY = (int) ($centerY - $areaHeight / 2);
        } else {
            $areaX = (int) ((($element['x'] ?? 50) / 100 * $canvasWidth) - $areaWidth / 2);
            $areaY = (int) (($element['y'] ?? 50) / 100 * $canvasHeight);
            $areaHeight = $canvasHeight - $areaY;
        }

        $boldFont = 'Montserrat-Bold.ttf';
        $mediumFont = 'Montserrat-Medium.ttf';
        $regularFont = 'Montserrat-Regular.ttf';
        $scale = $this->renderScale;

        $cards = array_slice($fixtures, 0, $maxItems);
        if (empty($cards)) {
            if (!$transparentBg) {
                imagefilledrectangle($canvas, $areaX, $areaY, $areaX + $areaWidth, $areaY + (int)(80 * $scale), $this->parseColor($canvas, $rowBg));
            }
            $this->addText($canvas, 'No upcoming fixtures', $areaX + (int)($areaWidth / 2), $areaY + (int)(40 * $scale), $fontSize, '#888888', $mediumFont, 'center');
            return;
        }

        // Card grid layout — use rowGap for vertical spacing, keep 12px horizontal gap
        $hGap = (int)(12 * $scale);
        $gap = $rowGap;
        $cardW = (int)(($areaWidth - ($columns - 1) * $hGap) / $columns);
        $headerBarH = $showMatchNum ? (int)(32 * $scale) : 0;
        $teamsRowH = (int)(70 * $scale);
        $infoRowH = ($showDateTime || $showVenue) ? (int)(44 * $scale) : 0;
        $cardH = $headerBarH + $teamsRowH + $infoRowH;

        $logoDiam = $showTeamLogo ? (int)($teamsRowH * 0.6) : 0;
        $teamFontSize = (int) ($fontSize * 0.78);
        $vsFontSize = (int) ($fontSize * 0.7);
        $dateFontSize = (int) ($fontSize * 0.65);
        $venueFontSize = (int) ($fontSize * 0.58);
        $matchLabelSize = (int) ($fontSize * 0.6);

        foreach ($cards as $index => $fixture) {
            $col = $index % $columns;
            $row = (int)($index / $columns);

            $cardX = $areaX + $col * ($cardW + $hGap);
            $cardY = $areaY + $row * ($cardH + $gap);

            // Skip if card goes beyond area
            if ($cardY + $cardH > $areaY + $areaHeight) break;

            $teamAName = $useShortName ? ($fixture['team_a_short'] ?? $fixture['team_a'] ?? 'TBD') : ($fixture['team_a'] ?? 'TBD');
            $teamBName = $useShortName ? ($fixture['team_b_short'] ?? $fixture['team_b'] ?? 'TBD') : ($fixture['team_b'] ?? 'TBD');
            $dateStr = $fixture['date'] ?? '';
            $timeStr = $fixture['time'] ?? '';
            $venue = $fixture['venue'] ?? '';
            $matchNum = $fixture['match_number'] ?? ($index + 1);

            // ====== CARD BACKGROUND & STYLE ======
            if (!$transparentBg) {
                if ($cardStyle === 'gradient') {
                    $bgRgb = $this->hexToRgb($rowBg);
                    for ($gy = $cardY; $gy < $cardY + $cardH; $gy++) {
                        $progress = ($gy - $cardY) / max(1, $cardH);
                        $r = min(255, (int)($bgRgb['r'] + (35 * $progress)));
                        $g = min(255, (int)($bgRgb['g'] + (35 * $progress)));
                        $b = min(255, (int)($bgRgb['b'] + (35 * $progress)));
                        $lineCol = imagecolorallocate($canvas, $r, $g, $b);
                        imageline($canvas, $cardX, $gy, $cardX + $cardW, $gy, $lineCol);
                    }
                } else {
                    imagefilledrectangle($canvas, $cardX, $cardY, $cardX + $cardW, $cardY + $cardH, $this->parseColor($canvas, $rowBg));
                }
            }

            // ====== CARD EFFECTS ======
            if ($cardStyle === 'stripe') {
                $barW = (int)(4 * $scale);
                if ($index % 2 === 0) {
                    imagefilledrectangle($canvas, $cardX, $cardY, $cardX + $barW, $cardY + $cardH, $this->parseColor($canvas, $accentColor));
                } else {
                    imagefilledrectangle($canvas, $cardX + $cardW - $barW, $cardY, $cardX + $cardW, $cardY + $cardH, $this->parseColor($canvas, $accentColor));
                }
            } elseif ($cardStyle === 'glow') {
                $glowRgb = $this->hexToRgb($accentColor);
                $glowInset = (int)(10 * $scale);
                for ($g = 0; $g < 3; $g++) {
                    $alpha = 60 + ($g * 25);
                    $glowCol = imagecolorallocatealpha($canvas, $glowRgb['r'], $glowRgb['g'], $glowRgb['b'], $alpha);
                    imageline($canvas, $cardX + $glowInset, $cardY + $cardH - $g, $cardX + $cardW - $glowInset, $cardY + $cardH - $g, $glowCol);
                }
            }

            // ====== BORDER (independent toggle) ======
            if ($showBorder) {
                imagerectangle($canvas, $cardX, $cardY, $cardX + $cardW, $cardY + $cardH, $this->parseColor($canvas, $accentColor));
            }

            // ====== HEADER BAR: "MATCH N" (only if showMatchNum) ======
            if ($showMatchNum) {
                $hdrRgb = $this->hexToRgb($headerBg);
                $hdrCol = imagecolorallocate($canvas, $hdrRgb['r'], $hdrRgb['g'], $hdrRgb['b']);
                imagefilledrectangle($canvas, $cardX, $cardY, $cardX + $cardW, $cardY + $headerBarH, $hdrCol);
                $this->addText($canvas, 'MATCH ' . $matchNum, $cardX + (int)($cardW / 2), $cardY + (int)($headerBarH / 2), $matchLabelSize, $headerText, $boldFont, 'center');
            }

            // ====== TEAMS ROW ======
            $teamsCenterY = $cardY + $headerBarH + (int)($teamsRowH / 2);
            $cardCenterX = $cardX + (int)($cardW / 2);

            // VS badge (center)
            $vsR = (int)(14 * $scale);
            $acRgb = $this->hexToRgb($accentColor);
            $vsBadge = imagecolorallocate($canvas, $acRgb['r'], $acRgb['g'], $acRgb['b']);
            imagefilledellipse($canvas, $cardCenterX, $teamsCenterY, $vsR * 2, $vsR * 2, $vsBadge);
            $this->addText($canvas, 'VS', $cardCenterX, $teamsCenterY, (int)($vsFontSize * 0.6), '#000000', $boldFont, 'center');

            // Team A (left half)
            $leftHalfW = (int)(($cardW / 2) - $vsR - (8 * $scale));
            $logoAX = $cardX + (int)($leftHalfW * 0.35);

            if ($showTeamLogo) {
                if (!empty($fixture['team_a_logo'])) {
                    $this->addCircularImage($canvas, $fixture['team_a_logo'], $logoAX, $teamsCenterY - (int)(6 * $scale), $logoDiam);
                } else {
                    imagefilledellipse($canvas, $logoAX, $teamsCenterY - (int)(6 * $scale), $logoDiam, $logoDiam, $this->parseColor($canvas, '#1a2d4a'));
                    $this->addText($canvas, mb_substr($teamAName, 0, 1), $logoAX, $teamsCenterY - (int)(6 * $scale), (int)($logoDiam * 0.4), '#ffffff', $boldFont, 'center');
                }
                $this->addText($canvas, $teamAName, $logoAX, $teamsCenterY + (int)($logoDiam * 0.42), (int)($teamFontSize * 0.82), $textColor, $boldFont, 'center');
            } else {
                $this->addText($canvas, $teamAName, $logoAX + (int)($leftHalfW * 0.15), $teamsCenterY, (int)($teamFontSize * 0.9), $textColor, $boldFont, 'center');
            }

            // Team B (right half)
            $logoBX = $cardX + $cardW - (int)($leftHalfW * 0.35);
            if ($showTeamLogo) {
                if (!empty($fixture['team_b_logo'])) {
                    $this->addCircularImage($canvas, $fixture['team_b_logo'], $logoBX, $teamsCenterY - (int)(6 * $scale), $logoDiam);
                } else {
                    imagefilledellipse($canvas, $logoBX, $teamsCenterY - (int)(6 * $scale), $logoDiam, $logoDiam, $this->parseColor($canvas, '#1a2d4a'));
                    $this->addText($canvas, mb_substr($teamBName, 0, 1), $logoBX, $teamsCenterY - (int)(6 * $scale), (int)($logoDiam * 0.4), '#ffffff', $boldFont, 'center');
                }
                $this->addText($canvas, $teamBName, $logoBX, $teamsCenterY + (int)($logoDiam * 0.42), (int)($teamFontSize * 0.82), $textColor, $boldFont, 'center');
            } else {
                $this->addText($canvas, $teamBName, $logoBX - (int)($leftHalfW * 0.15), $teamsCenterY, (int)($teamFontSize * 0.9), $textColor, $boldFont, 'center');
            }

            // ====== INFO ROW: Date/Time + Venue ======
            if ($showDateTime || $showVenue) {
                $infoY = $cardY + $headerBarH + $teamsRowH;
                $infoCenterY = $infoY + (int)($infoRowH / 2);

                // Subtle top border
                $borderCol = imagecolorallocatealpha($canvas, 255, 255, 255, 110);
                imageline($canvas, $cardX + (int)(10 * $scale), $infoY, $cardX + $cardW - (int)(10 * $scale), $infoY, $borderCol);

                if ($showDateTime) {
                    $dateTimeStr = trim(($dateStr ?: '') . ($timeStr ? '  |  ' . $timeStr : ''));
                    if ($dateTimeStr) {
                        $this->addText($canvas, $dateTimeStr, $cardCenterX, $showVenue && $venue ? $infoCenterY - (int)(7 * $scale) : $infoCenterY, $dateFontSize, $accentColor, $mediumFont, 'center');
                    }
                }
                if ($showVenue && $venue) {
                    $this->addText($canvas, $venue, $cardCenterX, $showDateTime ? $infoCenterY + (int)(9 * $scale) : $infoCenterY, $venueFontSize, $mutedColor, $regularFont, 'center');
                }
            }
        }
    }

    /**
     * Render template and return as base64 for preview
     * @param bool $optimizeForWeb Compress output for faster transfer
     */
    public function renderToBase64(TournamentTemplate $template, array $data = [], bool $optimizeForWeb = true, bool $skipBlanks = false): string
    {
        $path = $this->renderTemplate($template, $data, true, $skipBlanks);
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
     * Generate a poster filename prefixed with APP_NAME.
     * Format: app-name-label-timestamp.png
     */
    public static function posterFilename(string $label): string
    {
        $prefix = \Illuminate\Support\Str::slug(config('app.name', 'app'));
        return $prefix . '-' . $label . '-' . now()->format('Ymd-His') . '.png';
    }

    /**
     * Render and save permanently
     */
    public function renderAndSave(TournamentTemplate $template, array $data, string $customFilename = null, bool $skipBlanks = true): string
    {
        // skipBlanks defaults to true: real poster generation should hide any
        // placeholder whose value is empty instead of falling back to the
        // editor's sample text (e.g. "John Doe").
        $path = $this->renderTemplate($template, $data, false, $skipBlanks);

        if ($customFilename && Storage::disk('public')->exists($path)) {
            $newPath = $this->outputDirectory . '/' . $customFilename;
            Storage::disk('public')->move($path, $newPath);
            return $newPath;
        }

        return $path;
    }
}
