<?php

declare(strict_types=1);

namespace App\Services\Fonts;

use App\Models\Font;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Central font registry shared by the Fabric.js editor (browser @font-face) and
 * the PHP-GD poster renderer. Both sides resolve to the SAME .ttf file so the
 * live preview and the generated poster use identical glyphs.
 *
 * Built-in fonts (shipped in public/fonts and mapped in TemplateRenderService)
 * remain available; installed fonts are additive and never alter old templates.
 */
class FontService
{
    /**
     * Built-in families that already ship with TTF files in public/fonts and are
     * always available in the editor dropdown.
     */
    public const BUILTIN_FAMILIES = [
        'Roboto', 'Open Sans', 'Montserrat', 'Poppins',
        'Oswald', 'Bebas Neue', 'Anton', 'Bangers',
    ];

    /**
     * Built-in family => [weight => bundled TTF file]. These are the SAME files
     * the GD renderer resolves to (see TemplateRenderService::getFontFile), so
     * emitting @font-face from them makes the editor preview byte-identical to
     * the generated poster (instead of loading a possibly-different CDN cut and
     * falling back for weights that were never preloaded).
     */
    public const BUILTIN_VARIANTS = [
        'Montserrat' => [300 => 'Montserrat-Light.ttf', 400 => 'Montserrat-Regular.ttf', 500 => 'Montserrat-Medium.ttf', 600 => 'Montserrat-SemiBold.ttf', 700 => 'Montserrat-Bold.ttf', 800 => 'Montserrat-ExtraBold.ttf', 900 => 'Montserrat-Black.ttf'],
        'Oswald' => [200 => 'Oswald-ExtraLight.ttf', 300 => 'Oswald-Light.ttf', 400 => 'Oswald-Regular.ttf', 500 => 'Oswald-Medium.ttf', 600 => 'Oswald-SemiBold.ttf', 700 => 'Oswald-Bold.ttf'],
        'Poppins' => [400 => 'Poppins-Regular.ttf', 500 => 'Poppins-Medium.ttf', 700 => 'Poppins-Bold.ttf'],
        'Roboto' => [400 => 'Roboto-Regular.ttf', 500 => 'Roboto-Medium.ttf', 700 => 'Roboto-Bold.ttf'],
        'Open Sans' => [400 => 'OpenSans-Regular.ttf', 600 => 'OpenSans-SemiBold.ttf', 700 => 'OpenSans-Bold.ttf'],
        'Bebas Neue' => [400 => 'BebasNeue-Regular.ttf'],
        'Anton' => [400 => 'Anton-Regular.ttf'],
        'Bangers' => [400 => 'Bangers-Regular.ttf'],
    ];

    /**
     * Italic variants available among the bundled built-ins.
     */
    public const BUILTIN_ITALICS = [
        'Montserrat' => [400 => 'Montserrat-Italic.ttf', 700 => 'Montserrat-BoldItalic.ttf'],
    ];

    /**
     * Resolve a built-in family + weight to its bundled TTF — the single source
     * of truth shared by the GD renderer (TemplateRenderService::getFontFile)
     * and the editor's @font-face CSS, so both pick the IDENTICAL file for any
     * (family, weight). Returns null if the family isn't a built-in.
     *
     * Picks the nearest available weight (ties prefer the heavier), since a
     * bundled family may only ship a subset of weights.
     */
    public function builtinFontFile(string $family, int $weight, string $style = 'normal'): ?string
    {
        $key = $this->builtinFamilyKey($family);
        if ($key === null) {
            return null;
        }

        if ($style === 'italic' && !empty(self::BUILTIN_ITALICS[$key])) {
            return $this->pickNearestWeight(self::BUILTIN_ITALICS[$key], $weight);
        }

        return $this->pickNearestWeight(self::BUILTIN_VARIANTS[$key] ?? [], $weight);
    }

    /** Case-insensitive match of a family name to a BUILTIN_VARIANTS key. */
    private function builtinFamilyKey(string $family): ?string
    {
        foreach (array_keys(self::BUILTIN_VARIANTS) as $key) {
            if (strcasecmp($key, $family) === 0) {
                return $key;
            }
        }

        return null;
    }

    /**
     * From a [weight => file] map, return the file for the weight closest to
     * the requested one (ties resolve to the heavier weight).
     *
     * @param  array<int|string, string>  $weightToFile
     */
    private function pickNearestWeight(array $weightToFile, int $weight): ?string
    {
        $bestWeight = null;
        foreach (array_keys($weightToFile) as $w) {
            $w = (int) $w;
            if ($bestWeight === null) {
                $bestWeight = $w;
                continue;
            }
            $d = abs($w - $weight);
            $bd = abs($bestWeight - $weight);
            if ($d < $bd || ($d === $bd && $w > $bestWeight)) {
                $bestWeight = $w;
            }
        }

        return $bestWeight === null ? null : ($weightToFile[$bestWeight] ?? null);
    }

    /**
     * @font-face CSS for the built-in families. Each face spans a FULL weight
     * range (not just the bundled weights) so the browser always finds an exact
     * match for any requested weight — pointing at the SAME bundled TTF the GD
     * renderer resolves to. This prevents the browser from synthesizing a
     * faux-bold (or falling back) for a weight the renderer can't produce, which
     * was making the editor preview differ from the generated poster.
     */
    public function builtinFontFaceCss(): string
    {
        $css = '';
        foreach (array_keys(self::BUILTIN_VARIANTS) as $family) {
            $css .= $this->familyFaceRanges($family, 'normal');
            if (!empty(self::BUILTIN_ITALICS[$family])) {
                $css .= $this->familyFaceRanges($family, 'italic');
            }
        }

        return $css;
    }

    /**
     * Emit @font-face rules for one family/style, partitioning the 1..1000
     * weight axis into contiguous ranges that each map (via the shared
     * resolver) to a single bundled TTF.
     */
    private function familyFaceRanges(string $family, string $style): string
    {
        $variants = $style === 'italic'
            ? (self::BUILTIN_ITALICS[$family] ?? [])
            : (self::BUILTIN_VARIANTS[$family] ?? []);

        // Keep only weights whose file actually exists on disk.
        $variants = array_filter($variants, fn ($file) => File::exists($this->fontsPath($file)));
        if (empty($variants)) {
            return '';
        }

        $css = '';
        $currentFile = null;
        $rangeLo = 1;
        for ($w = 1; $w <= 1000; $w++) {
            $file = $this->pickNearestWeight($variants, $w);
            if ($file !== $currentFile) {
                if ($currentFile !== null) {
                    $css .= $this->fontFaceRule($family, $currentFile, $style, $rangeLo, $w - 1);
                }
                $currentFile = $file;
                $rangeLo = $w;
            }
        }
        $css .= $this->fontFaceRule($family, $currentFile, $style, $rangeLo, 1000);

        return $css;
    }

    private function fontFaceRule(string $family, string $file, string $style, int $lo, int $hi): string
    {
        $url = asset('fonts/' . $file);

        return "@font-face{font-family:'" . addslashes($family) . "';"
            . "font-style:{$style};font-weight:{$lo} {$hi};font-display:swap;"
            . "src:url('{$url}') format('truetype');}\n";
    }

    /** Memoized active fonts (avoids a DB query per rendered text element). */
    private ?\Illuminate\Support\Collection $activeFontsCache = null;

    /** Absolute path to the public fonts directory (TTFs live here). */
    public function fontsPath(string $sub = ''): string
    {
        return public_path('fonts' . ($sub ? '/' . ltrim($sub, '/') : ''));
    }

    /** @return \Illuminate\Support\Collection<int, Font> */
    private function activeFonts(): \Illuminate\Support\Collection
    {
        return $this->activeFontsCache ??= Font::active()->get();
    }

    /**
     * Install a Google Font: download a TTF for each requested weight/style and
     * persist a Font record. Uses the legacy CSS endpoint with an old browser
     * User-Agent so Google serves a single full TrueType file per variant.
     *
     * @param  int[]  $weights
     * @return Font
     *
     * @throws \RuntimeException when the family/weights can't be resolved.
     */
    public function installGoogleFont(string $family, array $weights, bool $includeItalic = false): Font
    {
        $family = trim($family);
        if ($family === '') {
            throw new \RuntimeException('Font family is required.');
        }

        $weights = array_values(array_unique(array_map('intval', $weights)));
        if (empty($weights)) {
            $weights = [400, 700];
        }

        // Build legacy css?family=Family:400,700,400italic param list.
        $variantParams = [];
        foreach ($weights as $w) {
            $variantParams[] = (string) $w;
            if ($includeItalic) {
                $variantParams[] = $w . 'italic';
            }
        }

        $url = 'https://fonts.googleapis.com/css?family='
            . str_replace('%20', '+', rawurlencode($family))
            . ':' . implode(',', $variantParams);

        $response = Http::withHeaders([
            // Old Android UA => Google serves plain .ttf (one @font-face per
            // variant). Newer UAs return .woff/.woff2 which GD cannot read.
            'User-Agent' => 'Mozilla/5.0 (Linux; U; Android 2.3.7; en-us; Nexus One Build/FRF91) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1',
        ])->timeout(30)->get($url);

        if (!$response->ok()) {
            throw new \RuntimeException("Could not fetch '{$family}' from Google Fonts (HTTP {$response->status()}).");
        }

        $css = $response->body();
        $parsed = $this->parseFontFaceCss($css);

        if (empty($parsed)) {
            throw new \RuntimeException("No matching font files found for '{$family}'. Check the family name and weights.");
        }

        $slug = Str::slug($family);
        $dir = $this->fontsPath('google');
        File::ensureDirectoryExists($dir);

        $variants = [];
        foreach ($parsed as $face) {
            $ttf = Http::timeout(30)->get($face['src']);
            if (!$ttf->ok()) {
                continue;
            }
            $suffix = $slug . '-' . $face['weight'] . ($face['style'] === 'italic' ? 'i' : '');
            $relative = 'google/' . $suffix . '.ttf';
            File::put($dir . '/' . $suffix . '.ttf', $ttf->body());

            $variants[] = [
                'weight' => $face['weight'],
                'style'  => $face['style'],
                'file'   => $relative,
            ];
        }

        if (empty($variants)) {
            throw new \RuntimeException("Could not download any font files for '{$family}'.");
        }

        return Font::updateOrCreate(
            ['slug' => $slug],
            ['name' => $family, 'source' => 'google', 'variants' => $variants, 'is_active' => true]
        );
    }

    /**
     * Install / add a custom uploaded font variant (TTF or OTF).
     */
    public function installCustomFont(string $family, int $weight, string $style, UploadedFile $file): Font
    {
        $family = trim($family);
        if ($family === '') {
            throw new \RuntimeException('Font family is required.');
        }
        $style = $style === 'italic' ? 'italic' : 'normal';

        $slug = Str::slug($family);
        $dir = $this->fontsPath('custom');
        File::ensureDirectoryExists($dir);

        // GD's imagettftext only reads TrueType; keep the original extension but
        // store .ttf when possible (OTF often works too, but TTF is safest).
        $ext = strtolower($file->getClientOriginalExtension() ?: 'ttf');
        $ext = in_array($ext, ['ttf', 'otf']) ? $ext : 'ttf';
        $suffix = $slug . '-' . $weight . ($style === 'italic' ? 'i' : '');
        $relative = 'custom/' . $suffix . '.' . $ext;
        $file->move($dir, $suffix . '.' . $ext);

        $font = Font::firstOrNew(['slug' => $slug]);
        $variants = collect($font->exists ? ($font->variants ?? []) : []);

        // Replace any existing variant with the same weight+style.
        $variants = $variants->reject(
            fn ($v) => (int) ($v['weight'] ?? 400) === $weight && ($v['style'] ?? 'normal') === $style
        )->values();
        $variants->push(['weight' => $weight, 'style' => $style, 'file' => $relative]);

        $font->fill([
            'name'      => $font->exists ? $font->name : $family,
            'source'    => 'custom',
            'variants'  => $variants->all(),
            'is_active' => true,
        ])->save();

        return $font->refresh();
    }

    /**
     * Parse @font-face blocks from a Google CSS response into a flat list of
     * { weight, style, src } entries (keeping only .ttf sources).
     *
     * @return array<int, array{weight:int, style:string, src:string}>
     */
    public function parseFontFaceCss(string $css): array
    {
        $faces = [];
        if (!preg_match_all('/@font-face\s*\{([^}]*)\}/i', $css, $blocks)) {
            return $faces;
        }

        foreach ($blocks[1] as $block) {
            if (!preg_match('/src\s*:\s*url\(([^)]+\.ttf)\)/i', $block, $srcM)) {
                continue;
            }
            $src = trim($srcM[1], "'\" ");

            $weight = 400;
            if (preg_match('/font-weight\s*:\s*(\d+)/i', $block, $wM)) {
                $weight = (int) $wM[1];
            }
            $style = 'normal';
            if (preg_match('/font-style\s*:\s*(italic)/i', $block)) {
                $style = 'italic';
            }

            // Dedupe by weight+style (legacy endpoint already returns one each).
            $key = $weight . '-' . $style;
            $faces[$key] = ['weight' => $weight, 'style' => $style, 'src' => $src];
        }

        return array_values($faces);
    }

    /**
     * Family names for the editor font dropdown: built-ins + installed fonts.
     *
     * @return string[]
     */
    public function editorFontList(): array
    {
        $installed = Font::active()->orderBy('name')->pluck('name')->all();

        return collect(self::BUILTIN_FAMILIES)
            ->merge($installed)
            ->unique(fn ($name) => Str::slug($name))
            ->values()
            ->all();
    }

    /**
     * @font-face CSS for all installed fonts, pointing at the SAME TTF files the
     * server renders with. Injected into the editor head so the browser preview
     * matches the generated poster exactly.
     */
    public function fontFaceCss(): string
    {
        $css = '';
        foreach ($this->activeFonts() as $font) {
            foreach ($font->variants ?? [] as $v) {
                if (empty($v['file'])) {
                    continue;
                }
                $url = asset('fonts/' . $v['file']);
                $weight = (int) ($v['weight'] ?? 400);
                $style = ($v['style'] ?? 'normal') === 'italic' ? 'italic' : 'normal';
                $css .= "@font-face{font-family:'" . addslashes($font->name) . "';"
                    . "font-style:{$style};font-weight:{$weight};font-display:swap;"
                    . "src:url('{$url}') format('truetype');}\n";
            }
        }

        return $css;
    }

    /**
     * Resolve an installed font's TTF file for the GD renderer.
     * Returns a path relative to public/fonts (e.g. "google/poppins-700.ttf"),
     * or null if the family isn't an installed font (caller uses built-ins).
     */
    public function resolveFontFile(string $family, int $weight, string $style = 'normal'): ?string
    {
        $slug = Str::slug($family);
        $font = $this->activeFonts()->firstWhere('slug', $slug);
        if (!$font) {
            return null;
        }

        $file = $font->resolveVariantFile($weight, $style);
        if (!$file) {
            return null;
        }

        // Only return it if the file actually exists on disk.
        return File::exists($this->fontsPath($file)) ? $file : null;
    }
}
