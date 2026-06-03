# Fix Editor vs Generated Poster Misalignment

## Context
After changing fonts and editing templates, the generated poster doesn't match the editor. Three related issues:
1. **Text renders at wrong vertical position** in generated poster
2. **Shapes don't scale** when using 540px canvas (renderScale=2)
3. **Position appears shifted** after save/reload (related to #1 since text metrics differ)

## Root Cause Analysis

### Issue 1: Vertical Text Misalignment (Main Problem)
**File:** `app/Services/Poster/PosterGeneratorService.php`, line 104

The editor (Fabric.js) saves text Y position as the **center point** of the text element. But PHP GD's `imagettftext()` treats Y as the **text baseline** (bottom of capital letters). No vertical adjustment is applied.

Different fonts have different ascender/descender heights, so the vertical shift varies per font — explaining why it got worse after switching fonts.

```
Editor:  Y = center of text bounding box
Server:  Y = text baseline (above descenders)
Result:  Text shifted down, amount varies per font
```

**Fix:** In `addText()`, after calculating text bounds with `imagettfbbox()`, compute vertical centering:
```php
// bbox[7] = top of text (negative), bbox[1] = bottom (positive for descenders)
// Midpoint relative to baseline = (bbox[1] + bbox[7]) / 2
$adjustedY = (int) ($y - ($bbox[1] + $bbox[7]) / 2);
```

### Issue 2: Shapes Missing renderScale
**File:** `app/Services/Poster/TemplateRenderService.php`, line 662-663

Shapes don't multiply width/height by `$this->renderScale`, unlike text (fontSize) and images. On 540px canvases (scale=2), shapes render at half their expected size.

**Fix:** Apply renderScale to shape dimensions:
```php
$width = (int) (($element['width'] ?? 150) * $this->renderScale);
$height = (int) (($element['height'] ?? 100) * $this->renderScale);
```

Also apply to strokeWidth:
```php
$strokeWidth = (int) (($element['strokeWidth'] ?? 2) * $this->renderScale);
```

### Issue 3: Skewed Text Centering
**File:** `app/Services/Poster/TemplateRenderService.php`, `renderSkewedText()` method

Same vertical centering issue as Issue 1. The skewed text temp canvas positions text at `$drawY = $padding + $textHeight`, which doesn't account for the center-point origin from the editor.

The final placement also needs centering: the temp canvas is placed onto the main canvas centered at (x, y).

## Files to Modify

### 1. `app/Services/Poster/PosterGeneratorService.php`
**Method:** `addText()` (line 59)
- After `$bbox = @imagettfbbox(...)`, calculate vertical center offset
- Replace `$y` with `$adjustedY` in `imagettftext()` call

### 2. `app/Services/Poster/TemplateRenderService.php`
**Method:** `renderShapeElement()` (line 659)
- Apply `$this->renderScale` to width, height, strokeWidth, rx, ry

**Method:** `renderSkewedText()` (line 268)
- Apply same vertical centering as `addText()` when positioning text in temp canvas

## Verification
1. Open template editor, place text elements with different fonts (Roboto, Poppins, Bangers, Montserrat)
2. Place shapes (rect, circle) near text elements for reference
3. Save template
4. Click "Render HD" — verify text positions match editor placement
5. Check that shapes align correctly with text
6. Test with a 540x540 canvas to verify renderScale works for shapes
