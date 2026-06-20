<?php

namespace Tests\Unit;

use App\Models\Font;
use App\Services\Fonts\FontService;
use App\Services\Poster\TemplateRenderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FontServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_parse_font_face_css_extracts_weight_style_and_ttf(): void
    {
        $css = "
            @font-face { font-family: 'Rubik'; font-style: normal; font-weight: 400; src: url(https://x/rubik-400.ttf) format('truetype'); }
            @font-face { font-family: 'Rubik'; font-style: italic; font-weight: 700; src: url(https://x/rubik-700i.ttf) format('truetype'); }
        ";

        $faces = (new FontService())->parseFontFaceCss($css);

        $this->assertCount(2, $faces);
        $this->assertSame(400, $faces[0]['weight']);
        $this->assertSame('normal', $faces[0]['style']);
        $this->assertStringEndsWith('rubik-400.ttf', $faces[0]['src']);
        $this->assertSame(700, $faces[1]['weight']);
        $this->assertSame('italic', $faces[1]['style']);
    }

    public function test_model_resolves_best_variant_file(): void
    {
        $font = new Font([
            'name' => 'X', 'slug' => 'x', 'source' => 'custom',
            'variants' => [
                ['weight' => 400, 'style' => 'normal', 'file' => 'a.ttf'],
                ['weight' => 700, 'style' => 'normal', 'file' => 'b.ttf'],
                ['weight' => 400, 'style' => 'italic', 'file' => 'c.ttf'],
            ],
        ]);

        $this->assertSame('b.ttf', $font->resolveVariantFile(700, 'normal')); // exact
        $this->assertSame('c.ttf', $font->resolveVariantFile(400, 'italic')); // exact italic
        $this->assertSame('b.ttf', $font->resolveVariantFile(800, 'normal')); // nearest weight
        $this->assertSame('c.ttf', $font->resolveVariantFile(300, 'italic')); // nearest same-style
    }

    public function test_resolve_font_file_returns_existing_installed_file(): void
    {
        Font::create([
            'name' => 'MyFam', 'slug' => 'myfam', 'source' => 'custom', 'is_active' => true,
            'variants' => [['weight' => 700, 'style' => 'normal', 'file' => 'Montserrat-Bold.ttf']],
        ]);

        $svc = new FontService();
        $this->assertSame('Montserrat-Bold.ttf', $svc->resolveFontFile('MyFam', 700, 'normal'));
        $this->assertSame('Montserrat-Bold.ttf', $svc->resolveFontFile('myfam', 400, 'normal')); // nearest
        $this->assertNull($svc->resolveFontFile('Nonexistent', 400, 'normal'));
    }

    public function test_resolve_font_file_null_when_file_missing_on_disk(): void
    {
        Font::create([
            'name' => 'Ghost', 'slug' => 'ghost', 'source' => 'custom', 'is_active' => true,
            'variants' => [['weight' => 400, 'style' => 'normal', 'file' => 'google/ghost-404.ttf']],
        ]);

        $this->assertNull((new FontService())->resolveFontFile('Ghost', 400));
    }

    public function test_editor_font_list_merges_builtins_and_installed_without_dupes(): void
    {
        Font::create([
            'name' => 'Zilla Slab', 'slug' => 'zilla-slab', 'source' => 'google', 'is_active' => true,
            'variants' => [['weight' => 400, 'style' => 'normal', 'file' => 'google/zilla-slab-400.ttf']],
        ]);

        $list = (new FontService())->editorFontList();

        $this->assertContains('Montserrat', $list);
        $this->assertContains('Zilla Slab', $list);
        $this->assertSame(count($list), count(array_unique(array_map('strtolower', $list))));
    }

    public function test_renderer_getfontfile_prefers_installed_then_builtin(): void
    {
        Font::create([
            'name' => 'BrandFont', 'slug' => 'brandfont', 'source' => 'custom', 'is_active' => true,
            'variants' => [['weight' => 700, 'style' => 'normal', 'file' => 'Montserrat-Bold.ttf']],
        ]);

        $svc = app(TemplateRenderService::class);
        $ref = new \ReflectionMethod($svc, 'getFontFile');
        $ref->setAccessible(true);

        // Installed font wins.
        $this->assertSame('Montserrat-Bold.ttf', $ref->invoke($svc, '700', 'normal', 'BrandFont'));
        // Unknown family falls back to the built-in map (unchanged behaviour).
        $this->assertSame('Oswald-Bold.ttf', $ref->invoke($svc, '700', 'normal', 'Oswald'));
    }
}
