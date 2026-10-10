<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\TemplatePreset;
use App\Models\TournamentTemplate;
use App\Services\Poster\Presets\PresetThemes;
use App\Services\Poster\TemplatePresetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Design thumbnails (anyone who makes posters) and Super Admin's switches for
 * each ready-made design: on/off, premium and price, order.
 */
class TemplatePresetController extends Controller
{
    public function __construct(private readonly TemplatePresetService $presets)
    {
        $this->middleware('permission:template.generate|template.manage')->only('thumb');
        $this->middleware('permission:template.manage')->except('thumb');
    }

    public function thumb(string $key, string $theme)
    {
        abort_unless($this->presets->find($key) !== null, 404);
        $path = $this->presets->thumbnail($key, $theme === 'own' ? null : $theme);
        abort_unless($path, 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function index(): View
    {
        $types = TournamentTemplate::TYPES;

        return view('backend.pages.template-presets.index', [
            'presets' => collect($this->presets->all())->sortBy([['type', 'asc'], ['sort', 'asc']])->groupBy('type'),
            'types' => $types,
            'themes' => PresetThemes::all(),
            'credits' => app(\App\Services\Credits\CreditService::class),
            'breadcrumbs' => ['title' => __('Ready-made designs')],
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        abort_unless($this->presets->find($key) !== null, 404);
        $data = $request->validate([
            'credit_cost' => 'nullable|integer|min:0|max:1000000',
            'sort' => 'nullable|integer|min:0|max:999',
        ]);

        TemplatePreset::updateOrCreate(['key' => $key], [
            'is_active' => $request->boolean('is_active'),
            'is_premium' => $request->boolean('is_premium'),
            'credit_cost' => (int) ($data['credit_cost'] ?? 0),
            'sort' => (int) ($data['sort'] ?? 0),
        ]);

        return back()->with('success', 'Design saved.');
    }
}
