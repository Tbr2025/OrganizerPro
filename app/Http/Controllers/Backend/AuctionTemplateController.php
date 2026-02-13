<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AuctionTemplateController extends Controller
{
    /**
     * Display a listing of auction templates.
     */
    public function index()
    {
        $templates = AuctionTemplate::with('auction')
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('backend.pages.auction-templates.index', compact('templates'));
    }

    /**
     * Show the form for creating a new template.
     */
    public function create()
    {
        $auctions = Auction::orderBy('name')->pluck('name', 'id');
        $defaultPositions = AuctionTemplate::getDefaultPositions();

        return view('backend.pages.auction-templates.create', compact('auctions', 'defaultPositions'));
    }

    /**
     * Store a newly created template.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:live_display,sold_display,player_card',
            'auction_id' => 'nullable|exists:auctions,id',
            'background_image' => 'nullable|image|max:10240',
            'sold_badge_image' => 'nullable|image|max:5120',
            'canvas_width' => 'required|integer|min:100|max:4000',
            'canvas_height' => 'required|integer|min:100|max:4000',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Handle file uploads
        if ($request->hasFile('background_image')) {
            $validated['background_image'] = $request->file('background_image')
                ->store('auction-templates', 'public');
        }

        if ($request->hasFile('sold_badge_image')) {
            $validated['sold_badge_image'] = $request->file('sold_badge_image')
                ->store('auction-templates', 'public');
        }

        // Parse element positions from form
        $validated['element_positions'] = $this->parseElementPositions($request);

        // If setting as default, unset other defaults of same type
        if ($request->boolean('is_default')) {
            AuctionTemplate::where('type', $validated['type'])
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $template = AuctionTemplate::create($validated);

        return redirect()
            ->route('admin.auction-templates.edit', $template)
            ->with('success', 'Template created successfully.');
    }

    /**
     * Show the form for editing a template.
     */
    public function edit(AuctionTemplate $auctionTemplate)
    {
        $auctions = Auction::orderBy('name')->pluck('name', 'id');
        $defaultPositions = AuctionTemplate::getDefaultPositions();

        return view('backend.pages.auction-templates.edit', [
            'template' => $auctionTemplate,
            'auctions' => $auctions,
            'defaultPositions' => $defaultPositions,
        ]);
    }

    /**
     * Update the specified template.
     */
    public function update(Request $request, AuctionTemplate $auctionTemplate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:live_display,sold_display,player_card',
            'auction_id' => 'nullable|exists:auctions,id',
            'background_image' => 'nullable|image|max:10240',
            'sold_badge_image' => 'nullable|image|max:5120',
            'canvas_width' => 'required|integer|min:100|max:4000',
            'canvas_height' => 'required|integer|min:100|max:4000',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Handle file uploads
        if ($request->hasFile('background_image')) {
            // Delete old image
            if ($auctionTemplate->background_image) {
                Storage::disk('public')->delete($auctionTemplate->background_image);
            }
            $validated['background_image'] = $request->file('background_image')
                ->store('auction-templates', 'public');
        }

        if ($request->hasFile('sold_badge_image')) {
            if ($auctionTemplate->sold_badge_image) {
                Storage::disk('public')->delete($auctionTemplate->sold_badge_image);
            }
            $validated['sold_badge_image'] = $request->file('sold_badge_image')
                ->store('auction-templates', 'public');
        }

        // Parse element positions from form
        $validated['element_positions'] = $this->parseElementPositions($request);

        // If setting as default, unset other defaults of same type
        if ($request->boolean('is_default') && !$auctionTemplate->is_default) {
            AuctionTemplate::where('type', $validated['type'])
                ->where('is_default', true)
                ->where('id', '!=', $auctionTemplate->id)
                ->update(['is_default' => false]);
        }

        $auctionTemplate->update($validated);

        return redirect()
            ->route('admin.auction-templates.edit', $auctionTemplate)
            ->with('success', 'Template updated successfully.');
    }

    /**
     * Remove the specified template.
     */
    public function destroy(AuctionTemplate $auctionTemplate)
    {
        // Delete images
        if ($auctionTemplate->background_image) {
            Storage::disk('public')->delete($auctionTemplate->background_image);
        }
        if ($auctionTemplate->sold_badge_image) {
            Storage::disk('public')->delete($auctionTemplate->sold_badge_image);
        }

        $auctionTemplate->delete();

        return redirect()
            ->route('admin.auction-templates.index')
            ->with('success', 'Template deleted successfully.');
    }

    /**
     * Preview the template with sample data.
     */
    public function preview(AuctionTemplate $auctionTemplate)
    {
        return view('backend.pages.auction-templates.preview', [
            'template' => $auctionTemplate,
        ]);
    }

    /**
     * Set a template as default.
     */
    public function setDefault(AuctionTemplate $auctionTemplate)
    {
        // Unset other defaults of same type
        AuctionTemplate::where('type', $auctionTemplate->type)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $auctionTemplate->update(['is_default' => true]);

        return back()->with('success', 'Template set as default.');
    }

    /**
     * Parse element positions from request.
     */
    protected function parseElementPositions(Request $request): array
    {
        $positions = [];
        $elements = [
            'player_image', 'player_name', 'player_role',
            'batting_style', 'bowling_style', 'current_bid', 'bid_label',
            'stats_matches', 'stats_wickets', 'stats_runs',
            'sold_badge', 'team_logo'
        ];

        foreach ($elements as $element) {
            if ($request->has("pos_{$element}_top") || $request->has("pos_{$element}_left")) {
                $positions[$element] = [
                    'top' => $request->input("pos_{$element}_top"),
                    'left' => $request->input("pos_{$element}_left"),
                    'bottom' => $request->input("pos_{$element}_bottom"),
                    'right' => $request->input("pos_{$element}_right"),
                    'width' => $request->input("pos_{$element}_width"),
                    'height' => $request->input("pos_{$element}_height"),
                    'fontSize' => $request->input("pos_{$element}_fontSize"),
                ];
                // Remove null values
                $positions[$element] = array_filter($positions[$element], fn($v) => $v !== null && $v !== '');
            }
        }

        return $positions;
    }
}
