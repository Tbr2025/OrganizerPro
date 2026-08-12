<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AuctionTemplateController extends Controller
{
    /**
     * Refuse markup that could execute.
     *
     * The page's CSP is what actually stops a script in a modern browser; this is the
     * belt-and-braces layer for anything older, and it tells the author immediately
     * rather than letting them ship a template whose script silently never runs.
     */
    private function assertMarkupIsSafe(Request $request): void
    {
        foreach (['html_body', 'html_css'] as $field) {
            $offender = \App\Services\Auction\TemplateTokenService::findUnsafeMarkup($request->input($field));

            if ($offender !== null) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $field => "Templates are HTML and CSS only — {$offender} is not permitted. Live values come from placeholder tokens.",
                ]);
            }
        }
    }

    /**
     * Refuse a template that belongs to another organization.
     *
     * Route-model binding hands us any id in the URL, so without this an organizer
     * could edit or delete another organization's template simply by guessing one.
     * Global templates (organization_id null) are Superadmin-only to modify: they are
     * shared, so changing one changes everybody's screen.
     */
    private function assertOwned(AuctionTemplate $template): void
    {
        abort_unless($template->isEditableBy(Auth::user()), 403);
    }

    /** Read access is wider than write: everyone may look at a global template. */
    private function assertVisible(AuctionTemplate $template): void
    {
        abort_unless(
            AuctionTemplate::query()->visibleTo(Auth::user())->whereKey($template->getKey())->exists(),
            403
        );
    }

    /**
     * Which organization should own this template?
     *
     * The bound auction's organization when there is one, else the author's. Only a
     * Superadmin can end up with null (a global template) — for anyone else, falling
     * back to their own organization is what keeps the template reachable to them.
     */
    /**
     * Clear the standing default for one type, within one organization.
     *
     * `AuctionTemplate` deliberately has no global scope (OrganizationScope filters on
     * strict equality and would hide the global `organization_id IS NULL` templates the
     * LED-wall fallback depends on). That means every query here has to scope itself, and
     * the three "unset the old default" sweeps did not — so setting a default in one org
     * un-defaulted every other org's wall, and the global fallback with it.
     *
     * A null $organizationId means the global bucket, matched with whereNull rather than
     * `= null`, which never matches anything in SQL.
     */
    private function clearDefaultsFor(string $type, ?int $organizationId, ?int $exceptId = null): void
    {
        AuctionTemplate::where('type', $type)
            ->where('is_default', true)
            ->when(
                $organizationId === null,
                fn ($q) => $q->whereNull('organization_id'),
                fn ($q) => $q->where('organization_id', $organizationId)
            )
            ->when($exceptId !== null, fn ($q) => $q->where('id', '!=', $exceptId))
            ->update(['is_default' => false]);
    }

    private function organizationForTemplate(?int $auctionId): ?int
    {
        $user = Auth::user();

        if ($auctionId !== null) {
            $orgId = Auction::withoutGlobalScopes()->whereKey($auctionId)->value('organization_id');

            if ($orgId !== null) {
                return (int) $orgId;
            }
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('Superadmin')) {
            return $user->organization_id ? (int) $user->organization_id : null;
        }

        return $user?->organization_id ? (int) $user->organization_id : null;
    }

    /**
     * Display a listing of auction templates.
     */
    public function index()
    {
        $templates = AuctionTemplate::with('auction')
            ->visibleTo(Auth::user())
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
            'type' => 'required|in:live_display,sold_display,player_card,ticker',
            'auction_id' => 'nullable|exists:auctions,id',
            'background_image' => 'nullable|image|max:10240',
            'sold_badge_image' => 'nullable|image|max:5120',
            'unsold_badge_image' => 'nullable|image|max:5120',
            'canvas_width' => 'required|integer|min:100|max:4000',
            'canvas_height' => 'required|integer|min:100|max:4000',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'render_mode' => 'nullable|in:positioned,html',
            // Capped: this markup is re-inserted into the DOM on every refresh, so an
            // unbounded field is a self-inflicted denial of service.
            'html_body' => 'nullable|string|max:200000|required_if:render_mode,html|required_if:type,ticker',
            'html_css' => 'nullable|string|max:200000',
            'html_refresh_ms' => 'nullable|integer|min:500|max:60000',
            'html_transparent_bg' => 'boolean',
        ]);

        $this->assertMarkupIsSafe($request);

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

        // Skipping this in HTML mode matters: otherwise every save writes a positions
        // blob nothing reads, and clobbers the one the drag editor left behind.
        if (($validated['render_mode'] ?? AuctionTemplate::RENDER_POSITIONED) !== AuctionTemplate::RENDER_HTML) {
            $validated['element_positions'] = $this->parseElementPositions($request);
        }

        // If setting as default, unset the previous default of the same type — WITHIN THIS
        // ORGANIZATION only. There is no global scope on this model, so an unscoped sweep
        // silently cleared every other organization's default wall, and the global
        // (organization_id IS NULL) fallback that AuctionTemplate::getDefault() relies on.
        $organizationId = $this->organizationForTemplate($validated['auction_id'] ?? null);

        /*
         * Write the checkboxes explicitly.
         *
         * A bare `boolean` rule cannot distinguish "unchecked" from "not submitted": an
         * unchecked box sends nothing, so the key never reaches $validated and the column is
         * never written. The form now posts a hidden 0 as well, but relying on that alone
         * would mean the controller is only correct for one particular template — so it is
         * settled here too.
         *
         * `is_active` is the one asymmetry: absent on CREATE means a brand-new template, and
         * the column defaults to on, so absence is treated as on rather than off.
         */
        $validated['is_default'] = $request->boolean('is_default');
        $validated['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;

        if ($request->boolean('is_default')) {
            $this->clearDefaultsFor($validated['type'], $organizationId);
        }

        // Bind the template to an organization so it cannot be read or edited across
        // org boundaries. Only a Superadmin may leave it global (no auction selected).
        $validated['organization_id'] = $organizationId;

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
        $this->assertOwned($auctionTemplate);

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
        $this->assertOwned($auctionTemplate);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:live_display,sold_display,player_card,ticker',
            'auction_id' => 'nullable|exists:auctions,id',
            'background_image' => 'nullable|image|max:10240',
            'sold_badge_image' => 'nullable|image|max:5120',
            'unsold_badge_image' => 'nullable|image|max:5120',
            'canvas_width' => 'required|integer|min:100|max:4000',
            'canvas_height' => 'required|integer|min:100|max:4000',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'render_mode' => 'nullable|in:positioned,html',
            // Capped: this markup is re-inserted into the DOM on every refresh, so an
            // unbounded field is a self-inflicted denial of service.
            'html_body' => 'nullable|string|max:200000|required_if:render_mode,html|required_if:type,ticker',
            'html_css' => 'nullable|string|max:200000',
            'html_refresh_ms' => 'nullable|integer|min:500|max:60000',
            'html_transparent_bg' => 'boolean',
        ]);

        $this->assertMarkupIsSafe($request);

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

        // Skipping this in HTML mode matters: otherwise every save writes a positions
        // blob nothing reads, and clobbers the one the drag editor left behind.
        if (($validated['render_mode'] ?? AuctionTemplate::RENDER_POSITIONED) !== AuctionTemplate::RENDER_HTML) {
            $validated['element_positions'] = $this->parseElementPositions($request);
        }

        // Clean up orphaned custom_image files. In HTML mode no positions were parsed,
        // so nothing was orphaned and the existing set is left exactly as it is.
        $oldPositions = $auctionTemplate->element_positions ?? [];
        $newPositions = $validated['element_positions'] ?? $oldPositions;
        foreach ($oldPositions as $key => $val) {
            if (str_starts_with($key, 'custom_image_') && ! empty($val['imagePath'])) {
                if (! isset($newPositions[$key]) || ($newPositions[$key]['imagePath'] ?? '') !== $val['imagePath']) {
                    Storage::disk('public')->delete($val['imagePath']);
                }
            }
        }

        /*
         * Same explicit write as store(). On an EDIT both boxes are always submitted (the
         * hidden 0 companions guarantee it), so an absent key means the operator unticked it
         * — which is exactly the case that silently did nothing before.
         */
        $validated['is_default'] = $request->boolean('is_default');
        $validated['is_active'] = $request->boolean('is_active');

        // Same organization scoping as store(): one org's default must never clear another's.
        if ($request->boolean('is_default') && ! $auctionTemplate->is_default) {
            $this->clearDefaultsFor(
                $validated['type'],
                $auctionTemplate->organization_id !== null ? (int) $auctionTemplate->organization_id : null,
                $auctionTemplate->id
            );
        }

        // One step of undo. Somebody who breaks the wall mid-auction is standing in
        // front of an audience, so keep whatever markup was working a moment ago.
        if ($auctionTemplate->isHtmlMode() && array_key_exists('html_body', $validated)
            && $validated['html_body'] !== $auctionTemplate->html_body) {
            $validated['html_body_previous'] = $auctionTemplate->html_body;
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
        $this->assertOwned($auctionTemplate);

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
            if (str_starts_with($key, 'custom_image_') && ! empty($val['imagePath'])) {
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
        $this->assertVisible($auctionTemplate);

        $auctionTemplate->load('auction');

        // An HTML template owns the whole screen, so its preview is the real renderer
        // pointed at whichever auction it is bound to.
        if ($auctionTemplate->isHtmlMode()) {
            $auction = $auctionTemplate->auction ?? Auction::orderByDesc('id')->first();

            abort_if($auction === null, 404, 'Bind this template to an auction to preview it.');

            $nonce = \App\Http\Middleware\AddTemplateCsp::nonce();

            return response()
                ->view('public.auction.html-template', [
                    'auction' => $auction,
                    'template' => $auctionTemplate,
                    'nonce' => $nonce,
                    'staticTokens' => \App\Services\Auction\TemplateTokenService::staticTokens($auction),
                ])
                ->header('Content-Security-Policy', \App\Http\Middleware\AddTemplateCsp::policy($nonce));
        }

        return view('backend.pages.auction-templates.preview', [
            'template' => $auctionTemplate,
        ]);
    }

    /**
     * Set a template as default.
     */
    public function setDefault(AuctionTemplate $auctionTemplate)
    {
        $this->assertOwned($auctionTemplate);

        $this->clearDefaultsFor(
            $auctionTemplate->type,
            $auctionTemplate->organization_id !== null ? (int) $auctionTemplate->organization_id : null,
            $auctionTemplate->id
        );

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
                    'cellBg' => $request->input("pos_{$element}_cellBg", ''),
                    'cellSpacing' => $request->input("pos_{$element}_cellSpacing", ''),
                    'headerHeight' => $request->input("pos_{$element}_headerHeight", ''),
                    'tableBorderColor' => $request->input("pos_{$element}_tableBorderColor", ''),
                    'tableBorderWidth' => $request->input("pos_{$element}_tableBorderWidth", ''),
                    'tableColumns' => $request->input("pos_{$element}_tableColumns", ''),
                ];
                // Remove null/empty position values but keep styling values
                $positions[$element] = array_filter($positions[$element], function ($v, $k) {
                    // Always keep styling keys even if default
                    $stylingKeys = ['color', 'bgColor', 'opacity', 'bgOpacity', 'borderRadius', 'borderRadiusTL', 'borderRadiusTR', 'borderRadiusBL', 'borderRadiusBR', 'boxShadow', 'textShadow', 'zIndex', 'visible', 'fontWeight', 'padding', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'margin', 'letterSpacing', 'lineHeight', 'textAlign', 'textTransform', 'rotation', 'borderStyle', 'borderColor', 'borderWidth', 'headerBg', 'headerColor', 'rowBg', 'cellColor', 'cellPadding', 'cellBg', 'cellSpacing', 'headerHeight', 'tableBorderColor', 'tableBorderWidth', 'tableColumns'];
                    if (in_array($k, $stylingKeys)) {
                        return true;
                    }
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
                    if (in_array($k, $keepKeys)) {
                        return true;
                    }
                    return $v !== null && $v !== '';
                }, ARRAY_FILTER_USE_BOTH);

                $custom[$elKey] = $data;
            }
        }

        return $custom;
    }
}
