<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Font;
use App\Services\Fonts\FontService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class FontController extends Controller
{
    public function __construct(private readonly FontService $fontService) {}

    public function index(): View
    {
        $fonts = Font::orderBy('name')->get();

        return view('backend.pages.fonts.index', compact('fonts'));
    }

    public function storeGoogle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'family'          => 'required|string|max:100',
            'weights'         => 'required|array|min:1',
            'weights.*'       => 'integer|in:100,200,300,400,500,600,700,800,900',
            'include_italic'  => 'nullable|boolean',
        ]);

        try {
            $font = $this->fontService->installGoogleFont(
                $validated['family'],
                $validated['weights'],
                (bool) ($validated['include_italic'] ?? false),
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', __("Google Font ':name' installed (:count variant(s)).", [
            'name'  => $font->name,
            'count' => count($font->variants ?? []),
        ]));
    }

    public function storeCustom(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'family' => 'required|string|max:100',
            'weight' => 'required|integer|in:100,200,300,400,500,600,700,800,900',
            'style'  => 'required|in:normal,italic',
            'font_file' => 'required|file|mimetypes:font/ttf,font/otf,application/font-sfnt,application/octet-stream,application/x-font-ttf,application/x-font-otf|max:6144',
        ]);

        // Extra guard: only allow .ttf/.otf by extension (mimetypes vary by OS).
        $ext = strtolower($request->file('font_file')->getClientOriginalExtension());
        if (!in_array($ext, ['ttf', 'otf'])) {
            return back()->with('error', __('Only .ttf or .otf font files are allowed.'))->withInput();
        }

        try {
            $font = $this->fontService->installCustomFont(
                $validated['family'],
                (int) $validated['weight'],
                $validated['style'],
                $request->file('font_file'),
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', __("Custom font ':name' saved.", ['name' => $font->name]));
    }

    public function destroy(Font $font): RedirectResponse
    {
        // Remove the stored font files (only the ones we manage under fonts/).
        foreach ($font->variants ?? [] as $v) {
            if (!empty($v['file'])) {
                $path = $this->fontService->fontsPath($v['file']);
                if (File::exists($path)) {
                    File::delete($path);
                }
            }
        }

        $name = $font->name;
        $font->delete();

        return back()->with('success', __("Font ':name' removed.", ['name' => $name]));
    }
}
