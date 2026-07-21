<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TournamentBannerController extends Controller
{
    public function index(Tournament $tournament): View
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $banners = $tournament->banners()->orderBy('page')->orderBy('position')->orderBy('sort_order')->get();

        // Organize banners by page → position
        $organized = [];
        foreach (TournamentBanner::PAGES as $pageKey => $pageLabel) {
            $organized[$pageKey] = [
                'label' => $pageLabel,
                'positions' => [],
            ];
            foreach (TournamentBanner::POSITIONS as $posKey => $posLabel) {
                $slotBanners = $banners->where('page', $pageKey)->where('position', $posKey)->values();
                $organized[$pageKey]['positions'][$posKey] = [
                    'label' => $posLabel,
                    'banners' => $slotBanners,
                    'display_type' => $slotBanners->first()?->display_type ?? TournamentBanner::DISPLAY_STATIC,
                ];
            }
        }

        return view('backend.pages.tournaments.banners.index', [
            'tournament' => $tournament,
            'organized' => $organized,
            'aspectRatios' => TournamentBanner::ASPECT_RATIOS,
            'displayTypes' => TournamentBanner::DISPLAY_TYPES,
            'breadcrumbs' => [
                ['name' => 'Dashboard', 'route' => route('admin.dashboard')],
                ['name' => $tournament->name, 'route' => route('tournaments.show', $tournament)],
                ['name' => 'Banners / Ads'],
            ],
        ]);
    }

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $request->validate([
            'image' => 'required|image|mimes:png,jpg,jpeg,gif,webp|max:5120',
            'page' => 'required|string|in:' . implode(',', array_keys(TournamentBanner::PAGES)),
            'position' => 'required|string|in:' . implode(',', array_keys(TournamentBanner::POSITIONS)),
            'aspect_ratio' => 'required|string|in:' . implode(',', array_keys(TournamentBanner::ASPECT_RATIOS)),
            'display_type' => 'required|string|in:' . implode(',', array_keys(TournamentBanner::DISPLAY_TYPES)),
            'link_url' => 'nullable|url|max:2048',
            'alt_text' => 'nullable|string|max:255',
        ]);

        $path = $request->file('image')->store(
            'tournament_banners/' . $tournament->id,
            'public'
        );

        $maxSort = $tournament->banners()
            ->where('page', $request->page)
            ->where('position', $request->position)
            ->max('sort_order') ?? -1;

        $banner = $tournament->banners()->create([
            'page' => $request->page,
            'position' => $request->position,
            'display_type' => $request->display_type,
            'image_path' => $path,
            'aspect_ratio' => $request->aspect_ratio,
            'link_url' => $request->link_url,
            'alt_text' => $request->alt_text,
            'sort_order' => $maxSort + 1,
        ]);

        // Sync display_type for all banners in same slot
        $tournament->banners()
            ->where('page', $request->page)
            ->where('position', $request->position)
            ->where('id', '!=', $banner->id)
            ->update(['display_type' => $request->display_type]);

        return redirect()->route('admin.tournaments.banners.index', $tournament)
            ->with('success', 'Banner uploaded successfully.');
    }

    public function update(Request $request, Tournament $tournament, TournamentBanner $banner): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $request->validate([
            'image' => 'nullable|image|mimes:png,jpg,jpeg,gif,webp|max:5120',
            'aspect_ratio' => 'required|string|in:' . implode(',', array_keys(TournamentBanner::ASPECT_RATIOS)),
            'display_type' => 'required|string|in:' . implode(',', array_keys(TournamentBanner::DISPLAY_TYPES)),
            'link_url' => 'nullable|url|max:2048',
            'alt_text' => 'nullable|string|max:255',
        ]);

        $data = [
            'aspect_ratio' => $request->aspect_ratio,
            'display_type' => $request->display_type,
            'link_url' => $request->link_url,
            'alt_text' => $request->alt_text,
        ];

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($banner->image_path);
            $data['image_path'] = $request->file('image')->store(
                'tournament_banners/' . $tournament->id,
                'public'
            );
        }

        $banner->update($data);

        // Sync display_type for all banners in same slot
        $tournament->banners()
            ->where('page', $banner->page)
            ->where('position', $banner->position)
            ->where('id', '!=', $banner->id)
            ->update(['display_type' => $request->display_type]);

        return redirect()->route('admin.tournaments.banners.index', $tournament)
            ->with('success', 'Banner updated successfully.');
    }

    public function destroy(Tournament $tournament, TournamentBanner $banner): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        Storage::disk('public')->delete($banner->image_path);
        $banner->delete();

        return redirect()->route('admin.tournaments.banners.index', $tournament)
            ->with('success', 'Banner deleted successfully.');
    }

    public function reorder(Request $request, Tournament $tournament): JsonResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:tournament_banners,id',
        ]);

        foreach ($request->order as $index => $bannerId) {
            $tournament->banners()->where('id', $bannerId)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    public function toggleActive(Tournament $tournament, TournamentBanner $banner): JsonResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $banner->update(['is_active' => !$banner->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $banner->is_active,
        ]);
    }
}
