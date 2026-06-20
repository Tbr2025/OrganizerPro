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
            // Old Chrome UA => Google returns plain .ttf (one @font-face per variant).
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/534.30 (KHTML, like Gecko) Chrome/12.0.742.112 Safari/534.30',
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
