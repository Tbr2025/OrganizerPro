# Plan: Enhanced Template Editor — Shapes, Gradients, Image Layers

## Context
The tournament template editor (`/admin/tournaments/{id}/templates/{id}/edit`) currently has limited shape options (only rect/circle), no gradient support, and no ability to upload PNG/SVG image layers. The user wants:
1. More shape types with gradient color fills
2. Upload PNG/SVG images as layers, managed per-template
3. Better color controls for shapes

## Files Modified

| File | Purpose |
|---|---|
| `resources/views/backend/pages/tournaments/templates/editor.blade.php` | Main editor UI (Fabric.js) |
| `app/Services/Poster/TemplateRenderService.php` | Server-side GD rendering |
| `app/Http/Controllers/Backend/Tournament/TournamentTemplateController.php` | Upload/delete overlay endpoints |

No new files or migrations needed — `overlay_images` JSON column already exists.

---

## 1. Add More Shape Types to Editor Sidebar

**File:** `editor.blade.php` — Shapes sidebar section (~line 197-207)

Add after existing Rectangle/Circle:
- **Triangle** — `fabric.Triangle` (native Fabric.js class)
- **Line** — `fabric.Line` (native)
- **Star** — `fabric.Polygon` with computed star vertex points
- **Diamond** — `fabric.Polygon` with 4 diamond vertices

**File:** `editor.blade.php` — `addShape()` method (~line 536)

Extend to handle new shape types:
```javascript
addShape(type, x, y) {
    let shape;
    const props = { fill: 'rgba(99, 102, 241, 0.5)', stroke: '#6366f1', strokeWidth: 2, left: x, top: y };

    if (type === 'rect') shape = new fabric.Rect({ ...props, width: 150, height: 100, rx: 8, ry: 8 });
    else if (type === 'circle') shape = new fabric.Circle({ ...props, radius: 60 });
    else if (type === 'triangle') shape = new fabric.Triangle({ ...props, width: 120, height: 120 });
    else if (type === 'line') shape = new fabric.Line([0, 0, 200, 0], { stroke: '#6366f1', strokeWidth: 4, left: x, top: y });
    else if (type === 'star') shape = new fabric.Polygon(this.starPoints(5, 60, 30), { ...props });
    else if (type === 'diamond') shape = new fabric.Polygon([{x:60,y:0},{x:120,y:80},{x:60,y:160},{x:0,y:80}], { ...props });

    if (shape) { shape.set({ shapeType: type, elementType: 'shape' }); this.canvas.add(shape); ... }
}

starPoints(spikes, outerR, innerR) {
    // Generates star polygon vertex array
}
```

---

## 2. Shape Color Properties Panel with Gradient Support

**File:** `editor.blade.php` — Add new Shape Properties panel in properties section

Add a **Shape Properties panel** (shown when a shape is selected) with:
- **Fill Type** selector: Solid / Linear Gradient / Radial Gradient
- **Solid**: Single color picker (current behavior)
- **Linear Gradient**: Two color stops + angle slider (0-360°)
- **Radial Gradient**: Two color stops
- **Stroke** color picker
- **Stroke Width** number input
- **Border Radius** (for rectangles)
- **More color presets** (expand from 5 to ~12 common colors including gradients)

**Fabric.js gradient implementation:**
```javascript
// Linear gradient
shape.set('fill', new fabric.Gradient({
    type: 'linear',
    coords: { x1: 0, y1: 0, x2: shape.width, y2: 0 },
    colorStops: [
        { offset: 0, color: '#6366f1' },
        { offset: 1, color: '#ec4899' }
    ]
}));

// Radial gradient
shape.set('fill', new fabric.Gradient({
    type: 'radial',
    coords: { x1: w/2, y1: h/2, r1: 0, x2: w/2, y2: h/2, r2: w/2 },
    colorStops: [...]
}));
```

**Save format** — gradient fills stored in layout_json as:
```json
{
    "fill": {
        "type": "linear",
        "angle": 90,
        "colorStops": [
            { "offset": 0, "color": "#6366f1" },
            { "offset": 1, "color": "#ec4899" }
        ]
    }
}
```

**Properties panel update logic** in `showProperties()`:
- Detect if selected object is a shape → show shape panel
- Read current fill (string=solid, object=gradient) → set UI controls
- Add `updateShapeFill()`, `updateShapeStroke()` methods

---

## 3. Upload Image Layers (PNG/SVG) — Per Template

**File:** `editor.blade.php` — Add "Upload Layer" section in Elements tab sidebar

Add an **"Image Layers"** section below Shapes with:
- Upload button (accepts PNG, SVG, JPG)
- List of uploaded layers for this template (from `$template->overlay_images`)
- Click to add to canvas, delete button to remove

**Upload flow:**
1. User clicks upload → file sent via AJAX to existing `uploadOverlay()` endpoint
2. Server stores file to `tournament_templates/{tournamentId}/overlays/`
3. Returns path + URL → added to template's overlay list in sidebar
4. User clicks the layer → `fabric.Image.fromURL()` adds it to canvas
5. On save, uploaded images on canvas are stored in `overlay_images` JSON

**File:** `editor.blade.php` — Add `addUploadedImage()` method:
```javascript
addUploadedImage(url, path) {
    fabric.Image.fromURL(url, (img) => {
        img.set({
            left: this.canvasWidth / 2 - img.width / 2,
            top: this.canvasHeight / 2 - img.height / 2,
            elementType: 'uploadedImage',
            imagePath: path,
        });
        img.scaleToWidth(Math.min(200, this.canvasWidth / 3));
        this.canvas.add(img);
        this.canvas.setActiveObject(img);
        this.saveHistory();
    }, { crossOrigin: 'anonymous' });
}
```

**File:** `editor.blade.php` — Update `save()` method:
- When serializing canvas objects, detect `elementType === 'uploadedImage'`
- Store in `overlay_images` array (separate from `layout_json`)
- Include: `path`, `x` (%), `y` (%), `width`, `height`, `rotation`, `opacity`, `zIndex`

**File:** `editor.blade.php` — Update `loadTemplate()`:
- After loading layout_json elements, also load overlay_images
- For each overlay, call `addUploadedImage()` to place on canvas

**SVG support:**
- `fabric.loadSVGFromURL()` for SVG files
- Store as image layer, render server-side by converting SVG to GD image via `imagecreatefromstring()` or using Imagick if available

---

## 4. Server-Side Rendering Updates

**File:** `TemplateRenderService.php`

### 4a. New shape types rendering
Update `renderShapeElement()` to handle: triangle, line, star, diamond
```php
if ($shapeType === 'triangle') {
    $points = [
        $drawX + $width/2, $drawY,           // top
        $drawX, $drawY + $height,             // bottom-left
        $drawX + $width, $drawY + $height,    // bottom-right
    ];
    imagefilledpolygon($canvas, $points, $fillColor);
}
// Similar for star (calculate polygon points), diamond
```

### 4b. Gradient rendering with GD
GD has no native gradient. Implement pixel-by-pixel line rendering:

```php
protected function renderGradientRect($canvas, $x, $y, $w, $h, $gradientConfig) {
    $stops = $gradientConfig['colorStops'];
    $angle = $gradientConfig['angle'] ?? 0;
    $color1 = $this->hexToRgb($stops[0]['color']);
    $color2 = $this->hexToRgb($stops[1]['color']);

    // For horizontal/vertical gradients, draw line by line
    for ($i = 0; $i < $h; $i++) {
        $ratio = $i / max($h - 1, 1);
        $r = (int)($color1['r'] + ($color2['r'] - $color1['r']) * $ratio);
        $g = (int)($color1['g'] + ($color2['g'] - $color1['g']) * $ratio);
        $b = (int)($color1['b'] + ($color2['b'] - $color1['b']) * $ratio);
        $lineColor = imagecolorallocate($canvas, $r, $g, $b);
        imageline($canvas, $x, $y + $i, $x + $w, $y + $i, $lineColor);
    }
}
```

For angled gradients, rotate the interpolation axis. Support basic angles: 0° (left→right), 90° (top→bottom), 45° (diagonal).

### 4c. Fix `renderUploadedImage()`
Replace the placeholder stub with actual image rendering (reuse `renderOverlayImage()` logic):
```php
protected function renderUploadedImage($canvas, $element, $x, $y, $canvasWidth): void {
    $path = $element['imagePath'] ?? $element['path'] ?? '';
    if (empty($path) || !Storage::disk('public')->exists($path)) {
        $this->drawPlaceholderBox(...);
        return;
    }
    $width = (int)($element['width'] ?? 150);
    $height = (int)($element['height'] ?? 150);
    $drawX = (int)($x - $width / 2);
    $drawY = (int)($y - $height / 2);
    $this->addImage($canvas, $path, $drawX, $drawY, $width, $height);
}
```

---

## 5. Save/Load Format Changes

**layout_json** element additions:
```json
// Shape with gradient
{
    "type": "shape",
    "shapeType": "triangle|star|diamond|line",
    "fill": "rgba(...)" | { "type": "linear|radial", "angle": 90, "colorStops": [...] },
    "stroke": "#hex",
    "strokeWidth": 2,
    ...
}

// Uploaded image in layout_json
{
    "type": "uploadedImage",
    "imagePath": "tournament_templates/3/overlays/filename.png",
    "x": 50, "y": 50,
    "width": 200, "height": 150,
    "rotation": 0, "opacity": 100,
    "zIndex": 5
}
```

No migration needed — layout_json is longText, overlay_images is JSON. Both already support arbitrary structures.

---

## Execution Status: COMPLETED

All changes implemented and deployed on 2026-03-26.

### Verification Checklist
1. Open template editor → sidebar shows Triangle, Line, Star, Diamond in Shapes section
2. Add a shape → properties panel shows Fill Type (Solid/Linear/Radial), color pickers, stroke controls
3. Set gradient fill → canvas shows gradient visually, save + preview renders gradient correctly
4. Upload a PNG → appears in sidebar list under "Image Layers"
5. Click uploaded PNG → appears on canvas, can resize/move/rotate
6. Save template → reload editor → all elements (shapes, gradients, uploaded images) restored correctly
7. Generate poster (preview/download) → server-side render matches editor view
