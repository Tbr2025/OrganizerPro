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
            'unsold_badge_image' => 'nullable|image|max:5120',
            'canvas_width' => 'required|integer|min:100|max:4000',
            'canvas_height' => 'required|integer|min:100|max:4000',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Validate custom image files (mimes instead of image to support SVG)
        foreach ($request->allFiles() as $fileKey => $file) {
            if (preg_match('/^pos_custom_image_\d+_file$/', $fileKey)) {
                $request->validate([$fileKey => 'file|mimes:jpg,jpeg,png,gif,webp,svg,bmp|max:5120']);
            }
        }

        // Handle file uploads
        if ($request->hasFile('background_image')) {
            $validated['background_image'] = $request->file('background_image')
                ->store('auction-templates', 'public');
        }

        if ($request->hasFile('sold_badge_image')) {
            $validated['sold_badge_image'] = $request->file('sold_badge_image')
                ->store('auction-templates', 'public');
        }

        if ($request->hasFile('unsold_badge_image')) {
            $validated['unsold_badge_image'] = $request->file('unsold_badge_image')
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
            'unsold_badge_image' => 'nullable|image|max:5120',
            'canvas_width' => 'required|integer|min:100|max:4000',
            'canvas_height' => 'required|integer|min:100|max:4000',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Validate custom image files (mimes instead of image to support SVG)
        foreach ($request->allFiles() as $fileKey => $file) {
            if (preg_match('/^pos_custom_image_\d+_file$/', $fileKey)) {
                $request->validate([$fileKey => 'file|mimes:jpg,jpeg,png,gif,webp,svg,bmp|max:5120']);
            }
        }

        // Handle file uploads
        if ($request->hasFile('background_image')) {
            // Delete old image
            if ($auctionTemplate->background_image) {
                Storage::disk('public')->delete($auctionTemplate->background_image);
            }
            $validated['background_image'] = $request->file('background_image')
                ->store('auction-templates', 'public');
        } elseif ($request->boolean('remove_background_image')) {
            if ($auctionTemplate->background_image) {
                Storage::disk('public')->delete($auctionTemplate->background_image);
            }
            $validated['background_image'] = null;
        }

        if ($request->hasFile('sold_badge_image')) {
            if ($auctionTemplate->sold_badge_image) {
                Storage::disk('public')->delete($auctionTemplate->sold_badge_image);
            }
            $validated['sold_badge_image'] = $request->file('sold_badge_image')
                ->store('auction-templates', 'public');
        } elseif ($request->boolean('remove_sold_badge_image')) {
            if ($auctionTemplate->sold_badge_image) {
                Storage::disk('public')->delete($auctionTemplate->sold_badge_image);
            }
            $validated['sold_badge_image'] = null;
        }

        if ($request->hasFile('unsold_badge_image')) {
            if ($auctionTemplate->unsold_badge_image) {
                Storage::disk('public')->delete($auctionTemplate->unsold_badge_image);
            }
            $validated['unsold_badge_image'] = $request->file('unsold_badge_image')
                ->store('auction-templates', 'public');
        } elseif ($request->boolean('remove_unsold_badge_image')) {
            if ($auctionTemplate->unsold_badge_image) {
                Storage::disk('public')->delete($auctionTemplate->unsold_badge_image);
            }
            $validated['unsold_badge_image'] = null;
        }

        // Parse element positions from form
        $validated['element_positions'] = $this->parseElementPositions($request);

        // Clean up orphaned custom_image files
        $oldPositions = $auctionTemplate->element_positions ?? [];
        $newPositions = $validated['element_positions'];
        foreach ($oldPositions as $key => $val) {
            if (str_starts_with($key, 'custom_image_') && !empty($val['imagePath'])) {
                if (!isset($newPositions[$key]) || ($newPositions[$key]['imagePath'] ?? '') !== $val['imagePath']) {
                    Storage::disk('public')->delete($val['imagePath']);
                }
            }
        }

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
        if ($auctionTemplate->unsold_badge_image) {
            Storage::disk('public')->delete($auctionTemplate->unsold_badge_image);
        }

        // Delete custom image files
        $positions = $auctionTemplate->element_positions ?? [];
        foreach ($positions as $key => $val) {
            if (str_starts_with($key, 'custom_image_') && !empty($val['imagePath'])) {
                Storage::disk('public')->delete($val['imagePath']);
            }
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
        $auctionTemplate->load('auction');

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
        $elements = AuctionTemplate::getElementKeys();

        foreach ($elements as $element) {
            if ($request->has("pos_{$element}_top") || $request->has("pos_{$element}_left")) {
                $positions[$element] = [
                    // Position fields
                    'top' => $request->input("pos_{$element}_top"),
                    'left' => $request->input("pos_{$element}_left"),
                    'bottom' => $request->input("pos_{$element}_bottom"),
                    'right' => $request->input("pos_{$element}_right"),
                    'width' => $request->input("pos_{$element}_width"),
                    'height' => $request->input("pos_{$element}_height"),
                    'fontSize' => $request->input("pos_{$element}_fontSize"),
                    // Styling fields
                    'color' => $request->input("pos_{$element}_color", '#ffffff'),
                    'bgColor' => $request->input("pos_{$element}_bgColor", ''),
                    'opacity' => (float) ($request->input("pos_{$element}_opacity", 1)),
                    'bgOpacity' => (float) ($request->input("pos_{$element}_bgOpacity", 1)),
                    'borderRadius' => (int) ($request->input("pos_{$element}_borderRadius", 0)),
                    'borderRadiusTL' => $request->input("pos_{$element}_borderRadiusTL", ''),
                    'borderRadiusTR' => $request->input("pos_{$element}_borderRadiusTR", ''),
                    'borderRadiusBL' => $request->input("pos_{$element}_borderRadiusBL", ''),
                    'borderRadiusBR' => $request->input("pos_{$element}_borderRadiusBR", ''),
                    'boxShadow' => $request->input("pos_{$element}_boxShadow", 'none'),
                    'textShadow' => $request->input("pos_{$element}_textShadow", 'none'),
                    'zIndex' => (int) ($request->input("pos_{$element}_zIndex", 10)),
                    'visible' => (bool) $request->input("pos_{$element}_visible", true),
                    'fontWeight' => $request->input("pos_{$element}_fontWeight", 'bold'),
                    'padding' => (int) ($request->input("pos_{$element}_padding", 0)),
                    'paddingTop' => $request->input("pos_{$element}_paddingTop", ''),
                    'paddingRight' => $request->input("pos_{$element}_paddingRight", ''),
                    'paddingBottom' => $request->input("pos_{$element}_paddingBottom", ''),
                    'paddingLeft' => $request->input("pos_{$element}_paddingLeft", ''),
                    // New styling fields
                    'margin' => (int) ($request->input("pos_{$element}_margin", 0)),
                    'letterSpacing' => (int) ($request->input("pos_{$element}_letterSpacing", 0)),
                    'lineHeight' => $request->input("pos_{$element}_lineHeight", ''),
                    'textAlign' => $request->input("pos_{$element}_textAlign", 'left'),
                    'textTransform' => $request->input("pos_{$element}_textTransform", 'none'),
                    'rotation' => (int) ($request->input("pos_{$element}_rotation", 0)),
                    'borderStyle' => $request->input("pos_{$element}_borderStyle", 'none'),
                    'borderColor' => $request->input("pos_{$element}_borderColor", ''),
                    'borderWidth' => (int) ($request->input("pos_{$element}_borderWidth", 0)),
                    // Table-specific
                    'headerBg' => $request->input("pos_{$element}_headerBg", ''),
                    'headerColor' => $request->input("pos_{$element}_headerColor", ''),
                    'rowBg' => $request->input("pos_{$element}_rowBg", ''),
                    'cellColor' => $request->input("pos_{$element}_cellColor", ''),
                    'cellPadding' => $request->input("pos_{$element}_cellPadding", ''),
                    'tableBorderColor' => $request->input("pos_{$element}_tableBorderColor", ''),
                    'tableBorderWidth' => $request->input("pos_{$element}_tableBorderWidth", ''),
                    'tableColumns' => $request->input("pos_{$element}_tableColumns", ''),
                ];
                // Remove null/empty position values but keep styling values
                $positions[$element] = array_filter($positions[$element], function ($v, $k) {
                    // Always keep styling keys even if default
                    $stylingKeys = ['color', 'bgColor', 'opacity', 'bgOpacity', 'borderRadius', 'borderRadiusTL', 'borderRadiusTR', 'borderRadiusBL', 'borderRadiusBR', 'boxShadow', 'textShadow', 'zIndex', 'visible', 'fontWeight', 'padding', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'margin', 'letterSpacing', 'lineHeight', 'textAlign', 'textTransform', 'rotation', 'borderStyle', 'borderColor', 'borderWidth', 'headerBg', 'headerColor', 'rowBg', 'cellColor', 'cellPadding', 'tableBorderColor', 'tableBorderWidth', 'tableColumns'];
                    if (in_array($k, $stylingKeys)) return true;
                    return $v !== null && $v !== '';
                }, ARRAY_FILTER_USE_BOTH);
            }
        }

        // Parse custom elements (text labels and shapes)
        $positions = array_merge($positions, $this->parseCustomElements($request));

        return $positions;
    }

    /**
     * Parse custom text and shape elements from request.
     */
    protected function parseCustomElements(Request $request): array
    {
        $custom = [];
        $allInputs = $request->all();

        // Scan for custom_text_N, custom_shape_N, and custom_image_N patterns
        foreach (['custom_text', 'custom_shape', 'custom_image'] as $prefix) {
            $indices = [];
            foreach ($allInputs as $key => $val) {
                if (preg_match("/^pos_{$prefix}_(\d+)_top$/", $key, $m)) {
                    $indices[] = (int) $m[1];
                }
            }

            foreach ($indices as $i) {
                $elKey = "{$prefix}_{$i}";
                $data = [
                    'top' => $request->input("pos_{$elKey}_top"),
                    'left' => $request->input("pos_{$elKey}_left"),
                    'width' => $request->input("pos_{$elKey}_width"),
                    'height' => $request->input("pos_{$elKey}_height"),
                    'color' => $request->input("pos_{$elKey}_color", '#ffffff'),
                    'bgColor' => $request->input("pos_{$elKey}_bgColor", ''),
                    'opacity' => (float) ($request->input("pos_{$elKey}_opacity", 1)),
                    'bgOpacity' => (float) ($request->input("pos_{$elKey}_bgOpacity", 1)),
                    'borderRadius' => (int) ($request->input("pos_{$elKey}_borderRadius", 0)),
                    'borderRadiusTL' => $request->input("pos_{$elKey}_borderRadiusTL", ''),
                    'borderRadiusTR' => $request->input("pos_{$elKey}_borderRadiusTR", ''),
                    'borderRadiusBL' => $request->input("pos_{$elKey}_borderRadiusBL", ''),
                    'borderRadiusBR' => $request->input("pos_{$elKey}_borderRadiusBR", ''),
                    'boxShadow' => $request->input("pos_{$elKey}_boxShadow", 'none'),
                    'textShadow' => $request->input("pos_{$elKey}_textShadow", 'none'),
                    'zIndex' => (int) ($request->input("pos_{$elKey}_zIndex", 10)),
                    'visible' => (bool) $request->input("pos_{$elKey}_visible", true),
                    'fontWeight' => $request->input("pos_{$elKey}_fontWeight", 'bold'),
                    'padding' => (int) ($request->input("pos_{$elKey}_padding", 0)),
                    'paddingTop' => $request->input("pos_{$elKey}_paddingTop", ''),
                    'paddingRight' => $request->input("pos_{$elKey}_paddingRight", ''),
                    'paddingBottom' => $request->input("pos_{$elKey}_paddingBottom", ''),
                    'paddingLeft' => $request->input("pos_{$elKey}_paddingLeft", ''),
                    'fontSize' => $request->input("pos_{$elKey}_fontSize"),
                    // New styling fields
                    'margin' => (int) ($request->input("pos_{$elKey}_margin", 0)),
                    'letterSpacing' => (int) ($request->input("pos_{$elKey}_letterSpacing", 0)),
                    'lineHeight' => $request->input("pos_{$elKey}_lineHeight", ''),
                    'textAlign' => $request->input("pos_{$elKey}_textAlign", 'left'),
                    'textTransform' => $request->input("pos_{$elKey}_textTransform", 'none'),
                    'rotation' => (int) ($request->input("pos_{$elKey}_rotation", 0)),
                    'borderStyle' => $request->input("pos_{$elKey}_borderStyle", 'none'),
                    'borderColor' => $request->input("pos_{$elKey}_borderColor", ''),
                    'borderWidth' => (int) ($request->input("pos_{$elKey}_borderWidth", 0)),
                ];

                if ($prefix === 'custom_text') {
                    $data['content'] = $request->input("pos_{$elKey}_content", 'Text');
                } elseif ($prefix === 'custom_image') {
                    // Handle image file upload
                    if ($request->hasFile("pos_{$elKey}_file")) {
                        $data['imagePath'] = $request->file("pos_{$elKey}_file")
                            ->store('auction-templates/custom', 'public');
                    } else {
                        $data['imagePath'] = $request->input("pos_{$elKey}_imagePath", '');
                    }
                } else {
                    $data['shapeType'] = $request->input("pos_{$elKey}_shapeType", 'rectangle');
                }

                $data = array_filter($data, function ($v, $k) {
                    $keepKeys = ['color', 'bgColor', 'opacity', 'bgOpacity', 'borderRadius', 'borderRadiusTL', 'borderRadiusTR', 'borderRadiusBL', 'borderRadiusBR', 'boxShadow', 'textShadow', 'zIndex', 'visible', 'fontWeight', 'padding', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'content', 'shapeType', 'imagePath', 'borderColor', 'borderWidth', 'margin', 'letterSpacing', 'lineHeight', 'textAlign', 'textTransform', 'rotation', 'borderStyle'];
                    if (in_array($k, $keepKeys)) return true;
                    return $v !== null && $v !== '';
                }, ARRAY_FILTER_USE_BOTH);

                $custom[$elKey] = $data;
            }
        }

        return $custom;
    }
}
