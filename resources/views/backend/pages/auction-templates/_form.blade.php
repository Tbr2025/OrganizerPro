@php
    $isEdit = isset($template);
    $positions = $isEdit ? ($template->element_positions ?? []) : $defaultPositions;
    $cw = old('canvas_width', $template->canvas_width ?? 1601);
    $ch = old('canvas_height', $template->canvas_height ?? 910);
    $defaultStyling = \App\Models\AuctionTemplate::getDefaultStyling();
@endphp

<div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
    {{-- Left: Form Fields --}}
    <div class="xl:col-span-1 space-y-6">

        {{-- Basic Info --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Basic Information</h3>

            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Template Name *</label>
                    <input type="text" name="name" value="{{ old('name', $template->name ?? '') }}"
                           class="form-control" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Template Type *</label>
                    <select name="type" class="form-control">
                        <option value="live_display" {{ old('type', $template->type ?? '') == 'live_display' ? 'selected' : '' }}>Live Display (LED Wall)</option>
                        <option value="sold_display" {{ old('type', $template->type ?? '') == 'sold_display' ? 'selected' : '' }}>Sold Display</option>
                        <option value="player_card" {{ old('type', $template->type ?? '') == 'player_card' ? 'selected' : '' }}>Player Card</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Specific Auction</label>
                    <select name="auction_id" class="form-control">
                        <option value="">-- Global Template --</option>
                        @foreach($auctions as $id => $name)
                            <option value="{{ $id }}" {{ old('auction_id', $template->auction_id ?? '') == $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Width (px)</label>
                        <input type="number" name="canvas_width" id="canvas_width" value="{{ $cw }}"
                               class="form-control" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Height (px)</label>
                        <input type="number" name="canvas_height" id="canvas_height" value="{{ $ch }}"
                               class="form-control" required>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_default" value="1"
                               {{ old('is_default', $template->is_default ?? false) ? 'checked' : '' }}
                               class="form-checkbox rounded text-blue-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Default</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1"
                               {{ old('is_active', $template->is_active ?? true) ? 'checked' : '' }}
                               class="form-checkbox rounded text-green-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Images --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Images</h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Background Image</label>
                    @if($isEdit && $template->background_image)
                        <div class="mb-2 p-2 bg-gray-100 dark:bg-gray-700 rounded flex items-center justify-between">
                            <img src="{{ asset('storage/' . $template->background_image) }}" class="h-16 object-contain">
                            <label class="flex items-center gap-1 text-xs text-red-500 cursor-pointer">
                                <input type="checkbox" name="remove_background_image" value="1" class="form-checkbox rounded text-red-500">
                                Remove
                            </label>
                        </div>
                    @endif
                    <input type="file" name="background_image" accept="image/*" class="form-control text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sold Badge Image</label>
                    @if($isEdit && $template->sold_badge_image)
                        <div class="mb-2 p-2 bg-gray-100 dark:bg-gray-700 rounded flex items-center justify-between">
                            <img src="{{ asset('storage/' . $template->sold_badge_image) }}" class="h-12 object-contain">
                            <label class="flex items-center gap-1 text-xs text-red-500 cursor-pointer">
                                <input type="checkbox" name="remove_sold_badge_image" value="1" class="form-checkbox rounded text-red-500">
                                Remove
                            </label>
                        </div>
                    @endif
                    <input type="file" name="sold_badge_image" accept="image/*" class="form-control text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Unsold Badge Image</label>
                    @if($isEdit && $template->unsold_badge_image)
                        <div class="mb-2 p-2 bg-gray-100 dark:bg-gray-700 rounded flex items-center justify-between">
                            <img src="{{ asset('storage/' . $template->unsold_badge_image) }}" class="h-12 object-contain">
                            <label class="flex items-center gap-1 text-xs text-red-500 cursor-pointer">
                                <input type="checkbox" name="remove_unsold_badge_image" value="1" class="form-checkbox rounded text-red-500">
                                Remove
                            </label>
                        </div>
                    @endif
                    <input type="file" name="unsold_badge_image" accept="image/*" class="form-control text-sm">
                </div>
            </div>
        </div>

        {{-- Selected Element Properties — Floating Panel --}}
        <div id="element-props" class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl border-2 border-blue-400 dark:border-blue-500 hidden"
             style="position:fixed;top:80px;right:20px;width:280px;max-height:80vh;z-index:9999;display:none;">
            {{-- Draggable header --}}
            <div id="props-header" class="flex items-center justify-between px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 rounded-t-xl cursor-move select-none">
                <div class="flex items-center gap-2">
                    <span id="prop-color" class="w-3 h-3 rounded-full bg-white border border-blue-300"></span>
                    <span id="prop-title" class="text-sm font-bold text-white">Element</span>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" id="props-collapse-btn" onclick="togglePropsCollapse()" class="text-white/80 hover:text-white text-xs px-1" title="Collapse/Expand">&#9660;</button>
                    <button type="button" onclick="document.getElementById('element-props').style.display='none';document.getElementById('element-props').classList.add('hidden')" class="text-white/80 hover:text-white text-lg leading-none px-1" title="Close">&times;</button>
                </div>
            </div>
            {{-- Scrollable body --}}
            <div id="props-body" class="p-4 overflow-y-auto" style="max-height:calc(80vh - 40px);">
            <div class="space-y-3">
                {{-- Visibility Toggle --}}
                <div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="prop-visible" checked class="form-checkbox rounded text-green-600">
                        <span class="text-xs text-gray-500">Visible</span>
                    </label>
                </div>

                {{-- Position --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-500">Top (px)</label>
                        <input type="number" id="prop-top" class="form-control form-control-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Left (px)</label>
                        <input type="number" id="prop-left" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div id="prop-width-wrap">
                        <label class="text-xs text-gray-500">Width (px)</label>
                        <input type="number" id="prop-width" class="form-control form-control-sm">
                    </div>
                    <div id="prop-height-wrap">
                        <label class="text-xs text-gray-500">Height (px)</label>
                        <input type="number" id="prop-height" class="form-control form-control-sm">
                    </div>
                </div>
                <div id="prop-fontsize-wrap">
                    <label class="text-xs text-gray-500">Font Size (px)</label>
                    <input type="number" id="prop-fontsize" class="form-control form-control-sm">
                </div>
                <div id="prop-bottom-wrap">
                    <label class="text-xs text-gray-500">Bottom (px) — overrides Top</label>
                    <input type="number" id="prop-bottom" class="form-control form-control-sm">
                </div>

                {{-- Styling Section --}}
                <hr class="border-gray-300 dark:border-gray-600">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Styling</p>

                {{-- Text Color --}}
                <div id="prop-text-color-wrap">
                    <label class="text-xs text-gray-500">Text Color</label>
                    <div class="flex gap-2">
                        <input type="color" id="prop-text-color" value="#ffffff" class="w-10 h-8 rounded cursor-pointer border border-gray-300">
                        <input type="text" id="prop-text-color-hex" value="#ffffff" class="form-control form-control-sm flex-1" placeholder="#ffffff">
                    </div>
                </div>

                {{-- Background Color --}}
                <div>
                    <label class="text-xs text-gray-500">Background Color</label>
                    <div class="flex gap-2 items-center">
                        <input type="color" id="prop-bg-color" value="#000000" class="w-10 h-8 rounded cursor-pointer border border-gray-300">
                        <input type="text" id="prop-bg-color-text" value="" class="form-control form-control-sm flex-1" placeholder="rgba() or gradient">
                        <button type="button" id="prop-bg-color-clear" title="Remove background" class="text-red-400 hover:text-red-600 text-lg leading-none px-1">&times;</button>
                    </div>
                    {{-- Gradient Presets --}}
                    <div id="gradient-presets" class="flex flex-wrap gap-1 mt-2"></div>
                </div>

                {{-- Element Opacity --}}
                <div>
                    <label class="text-xs text-gray-500">Element Opacity: <span id="prop-opacity-val">1</span></label>
                    <input type="range" id="prop-opacity" min="0" max="1" step="0.05" value="1" class="w-full">
                </div>

                {{-- Background Opacity --}}
                <div>
                    <label class="text-xs text-gray-500">Background Opacity: <span id="prop-bg-opacity-val">1</span></label>
                    <input type="range" id="prop-bg-opacity" min="0" max="1" step="0.05" value="1" class="w-full">
                </div>

                {{-- Border Radius --}}
                <div>
                    <label class="text-xs text-gray-500">Border Radius (px) — All</label>
                    <input type="number" id="prop-border-radius" min="0" value="0" class="form-control form-control-sm" placeholder="All corners">
                    <div class="grid grid-cols-2 gap-1 mt-1">
                        <div>
                            <label class="text-[10px] text-gray-400">TL</label>
                            <input type="number" id="prop-br-tl" min="0" value="0" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-400">TR</label>
                            <input type="number" id="prop-br-tr" min="0" value="0" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-400">BL</label>
                            <input type="number" id="prop-br-bl" min="0" value="0" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-400">BR</label>
                            <input type="number" id="prop-br-br" min="0" value="0" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                {{-- Padding --}}
                <div>
                    <label class="text-xs text-gray-500">Padding (px) — All</label>
                    <input type="number" id="prop-padding" min="0" value="0" class="form-control form-control-sm" placeholder="All sides">
                    <div class="grid grid-cols-2 gap-1 mt-1">
                        <div>
                            <label class="text-[10px] text-gray-400">Top</label>
                            <input type="number" id="prop-pad-top" min="0" value="" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-400">Right</label>
                            <input type="number" id="prop-pad-right" min="0" value="" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-400">Bottom</label>
                            <input type="number" id="prop-pad-bottom" min="0" value="" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-400">Left</label>
                            <input type="number" id="prop-pad-left" min="0" value="" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                {{-- Z-Index --}}
                <div>
                    <label class="text-xs text-gray-500">Z-Index</label>
                    <input type="number" id="prop-zindex" value="10" class="form-control form-control-sm">
                </div>

                {{-- Box Shadow --}}
                <div>
                    <label class="text-xs text-gray-500">Box Shadow</label>
                    <select id="prop-box-shadow" class="form-control form-control-sm">
                        <option value="none">None</option>
                        <option value="small">Small</option>
                        <option value="medium">Medium</option>
                        <option value="large">Large</option>
                        <option value="glow">Glow</option>
                    </select>
                </div>

                {{-- Text Shadow --}}
                <div id="prop-text-shadow-wrap">
                    <label class="text-xs text-gray-500">Text Shadow</label>
                    <select id="prop-text-shadow" class="form-control form-control-sm">
                        <option value="none">None</option>
                        <option value="subtle">Subtle</option>
                        <option value="strong">Strong</option>
                        <option value="glow">Glow</option>
                    </select>
                </div>

                {{-- Font Weight --}}
                <div id="prop-font-weight-wrap">
                    <label class="text-xs text-gray-500">Font Weight</label>
                    <select id="prop-font-weight" class="form-control form-control-sm">
                        <option value="normal">Normal</option>
                        <option value="bold">Bold</option>
                        <option value="900">Black (900)</option>
                    </select>
                </div>

                {{-- Typography Section --}}
                <hr class="border-gray-300 dark:border-gray-600">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Typography</p>

                <div id="prop-text-align-wrap">
                    <label class="text-xs text-gray-500">Text Align</label>
                    <select id="prop-text-align" class="form-control form-control-sm">
                        <option value="left">Left</option>
                        <option value="center">Center</option>
                        <option value="right">Right</option>
                    </select>
                </div>

                <div id="prop-text-transform-wrap">
                    <label class="text-xs text-gray-500">Text Transform</label>
                    <select id="prop-text-transform" class="form-control form-control-sm">
                        <option value="none">None</option>
                        <option value="uppercase">Uppercase</option>
                        <option value="lowercase">Lowercase</option>
                        <option value="capitalize">Capitalize</option>
                    </select>
                </div>

                <div id="prop-letter-spacing-wrap">
                    <label class="text-xs text-gray-500">Letter Spacing (px)</label>
                    <input type="number" id="prop-letter-spacing" value="0" class="form-control form-control-sm">
                </div>

                <div id="prop-line-height-wrap">
                    <label class="text-xs text-gray-500">Line Height</label>
                    <input type="text" id="prop-line-height" value="" class="form-control form-control-sm" placeholder="e.g. 1.2 or 40px">
                </div>

                {{-- Layout Section --}}
                <hr class="border-gray-300 dark:border-gray-600">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Layout</p>

                <div>
                    <label class="text-xs text-gray-500">Margin (px, all sides)</label>
                    <input type="number" id="prop-margin" min="0" value="0" class="form-control form-control-sm">
                </div>

                <div id="prop-el-width-wrap">
                    <label class="text-xs text-gray-500">Element Width (px)</label>
                    <input type="number" id="prop-el-width" class="form-control form-control-sm" placeholder="Auto">
                </div>

                <div id="prop-el-height-wrap">
                    <label class="text-xs text-gray-500">Element Height (px)</label>
                    <input type="number" id="prop-el-height" class="form-control form-control-sm" placeholder="Auto">
                </div>

                {{-- Border Section --}}
                <hr class="border-gray-300 dark:border-gray-600">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Border</p>

                <div>
                    <label class="text-xs text-gray-500">Border Style</label>
                    <select id="prop-border-style" class="form-control form-control-sm">
                        <option value="none">None</option>
                        <option value="solid">Solid</option>
                        <option value="dashed">Dashed</option>
                        <option value="dotted">Dotted</option>
                    </select>
                </div>

                <div>
                    <label class="text-xs text-gray-500">Border Color</label>
                    <input type="color" id="prop-el-border-color" value="#ffffff" class="w-10 h-8 rounded cursor-pointer border border-gray-300">
                </div>

                <div>
                    <label class="text-xs text-gray-500">Border Width (px)</label>
                    <input type="number" id="prop-el-border-width" min="0" value="0" class="form-control form-control-sm">
                </div>

                {{-- Transform Section --}}
                <hr class="border-gray-300 dark:border-gray-600">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Transform</p>

                <div>
                    <label class="text-xs text-gray-500">Rotation: <span id="prop-rotation-val">0</span>&deg;</label>
                    <input type="range" id="prop-rotation" min="-180" max="180" step="1" value="0" class="w-full">
                </div>

                {{-- Custom text content --}}
                <div id="prop-content-wrap" class="hidden">
                    <hr class="border-gray-300 dark:border-gray-600">
                    <label class="text-xs text-gray-500">Text Content</label>
                    <input type="text" id="prop-content" class="form-control form-control-sm" placeholder="Enter text...">
                </div>

                {{-- Custom shape type --}}
                <div id="prop-shape-wrap" class="hidden">
                    <hr class="border-gray-300 dark:border-gray-600">
                    <label class="text-xs text-gray-500">Shape Type</label>
                    <select id="prop-shape-type" class="form-control form-control-sm">
                        <option value="rectangle">Rectangle</option>
                        <option value="circle">Circle</option>
                        <option value="rounded-rect">Rounded Rectangle</option>
                        <option value="pill">Pill</option>
                        <option value="diamond">Diamond</option>
                        <option value="triangle">Triangle</option>
                        <option value="line">Line</option>
                    </select>
                </div>

                {{-- Table styling --}}
                <div id="prop-table-wrap" class="hidden">
                    <hr class="border-gray-300 dark:border-gray-600">
                    <p class="text-xs font-semibold text-purple-400 mb-1">Table Styling</p>
                    <div class="space-y-2">
                        <div>
                            <label class="text-[10px] text-gray-400">Header Background</label>
                            <input type="text" id="prop-header-bg" class="form-control form-control-sm" placeholder="rgba(0,0,0,0.7)">
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-400">Header Text Color</label>
                            <input type="text" id="prop-header-color" class="form-control form-control-sm" placeholder="#ffffff">
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-400">Row Background</label>
                            <input type="text" id="prop-row-bg" class="form-control form-control-sm" placeholder="rgba(255,255,255,0.1)">
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-400">Cell Text Color</label>
                            <input type="text" id="prop-cell-color" class="form-control form-control-sm" placeholder="#ffffff">
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-400">Cell Padding (px)</label>
                            <input type="number" id="prop-cell-padding" min="0" class="form-control form-control-sm" placeholder="10">
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-400">Table Border Color</label>
                            <input type="text" id="prop-table-border-color" class="form-control form-control-sm" placeholder="rgba(255,255,255,0.2)">
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-400">Table Border Width (px)</label>
                            <input type="number" id="prop-table-border-width" min="0" class="form-control form-control-sm" placeholder="1">
                        </div>
                    </div>

                    {{-- Dynamic Columns Editor --}}
                    <hr class="border-gray-300 dark:border-gray-600 mt-3">
                    <div class="flex items-center justify-between mb-1">
                        <p class="text-xs font-semibold text-purple-400">Columns</p>
                        <button type="button" onclick="addTableColumn()" class="text-[10px] bg-purple-600 hover:bg-purple-700 text-white px-2 py-0.5 rounded">+ Column</button>
                    </div>
                    <div id="table-columns-editor" class="space-y-2 max-h-48 overflow-y-auto">
                        {{-- JS populates column rows here --}}
                    </div>
                </div>
            </div>
            </div>{{-- /props-body --}}
        </div>

        {{-- Custom Elements --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-3">Custom Elements</h3>
            <div class="flex gap-2">
                <button type="button" onclick="addCustomText()" class="btn btn-sm bg-purple-600 text-white hover:bg-purple-700 flex-1 text-xs py-2 px-3 rounded">+ Text</button>
                <button type="button" onclick="addCustomShape()" class="btn btn-sm bg-indigo-600 text-white hover:bg-indigo-700 flex-1 text-xs py-2 px-3 rounded">+ Shape</button>
                <button type="button" onclick="triggerAddImage()" class="btn btn-sm bg-teal-600 text-white hover:bg-teal-700 flex-1 text-xs py-2 px-3 rounded">+ Image</button>
            </div>
            <input type="file" id="custom-image-picker" accept="image/*" class="hidden" onchange="addCustomImage(this.files[0]);this.value=''">
            <div id="custom-elements-list" class="mt-3 space-y-1 text-xs"></div>
        </div>

        {{-- Submit --}}
        <div class="flex gap-3">
            <button type="submit" class="btn btn-primary flex-1">
                {{ $isEdit ? 'Update Template' : 'Create Template' }}
            </button>
            <a href="{{ route('admin.auction-templates.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </div>

    {{-- Right: Visual Canvas Editor --}}
    <div class="xl:col-span-3">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">Visual Layout Editor</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Drag elements to reposition. Click to edit properties.</p>
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <span id="canvas-scale-info">Scale: 100%</span>
                    <button type="button" onclick="resetAllPositions()" class="px-2 py-1 bg-gray-200 dark:bg-gray-600 rounded hover:bg-gray-300 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200">Reset All</button>
                </div>
            </div>

            {{-- Canvas wrapper with auto-scale --}}
            <div id="canvas-wrapper" class="overflow-auto rounded-lg border-2 border-gray-300 dark:border-gray-600" style="max-height: 75vh;">
                <div id="canvas-container" style="position:relative;width:{{ $cw }}px;height:{{ $ch }}px;transform-origin:top left;
                    @if($isEdit && $template->background_image)
                    background: url('{{ asset('storage/' . $template->background_image) }}') no-repeat center center;background-size:cover;
                    @else
                    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
                    @endif
                ">
                    {{-- Draggable elements with data-* styling attributes --}}
                    @php
                        $elements = [
                            'player_image' => ['label' => 'Player Image', 'type' => 'image', 'borderColor' => '59,130,246', 'content' => '<img src="https://ui-avatars.com/api/?name=Player&size=200&background=random" class="w-full h-full object-cover opacity-60">'],
                            'player_name' => ['label' => 'Player Name', 'type' => 'text', 'borderColor' => '34,197,94', 'content' => 'SAMPLE PLAYER'],
                            'player_role' => ['label' => 'Player Role', 'type' => 'text', 'borderColor' => '168,85,247', 'content' => 'ALL ROUNDER'],
                            'batting_style' => ['label' => 'Batting Style', 'type' => 'text', 'borderColor' => '234,179,8', 'content' => 'Right Hand Bat'],
                            'bowling_style' => ['label' => 'Bowling Style', 'type' => 'text', 'borderColor' => '249,115,22', 'content' => 'Right Arm Medium'],
                            'current_bid' => ['label' => 'Current Bid', 'type' => 'text', 'borderColor' => '239,68,68', 'content' => '10.5 L'],
                            'bid_label' => ['label' => 'Bid Label', 'type' => 'text', 'borderColor' => '236,72,153', 'content' => 'SOLD PRICE'],
                            'sold_badge' => ['label' => 'Sold Badge', 'type' => 'box', 'borderColor' => '16,185,129', 'content' => ''],
                            'team_logo' => ['label' => 'Team Logo', 'type' => 'box', 'borderColor' => '6,182,212', 'content' => '<span class="text-cyan-400 text-xs">TEAM LOGO</span>'],
                            'highest_bidder' => ['label' => 'Highest Bidder', 'type' => 'text', 'borderColor' => '0,255,0', 'content' => 'Thunder Kings'],
                            'stats_table' => ['label' => 'Stats Table', 'type' => 'table', 'borderColor' => '147,51,234', 'content' => ''],
                        ];
                    @endphp

                    @foreach($elements as $elKey => $elInfo)
                        @php
                            $p = $positions[$elKey] ?? [];
                            $isBoxOrImage = in_array($elInfo['type'], ['box', 'image', 'table']);
                            $hasBottom = in_array($elKey, ['player_image', 'current_bid', 'bid_label', 'sold_badge', 'team_logo']);
                            // Position style
                            if ($hasBottom && isset($p['bottom']) && !isset($p['top'])) {
                                $posStyle = 'bottom:'.($p['bottom'] ?? 0).'px';
                            } elseif (isset($p['top'])) {
                                $posStyle = 'top:'.$p['top'].'px';
                            } elseif ($hasBottom) {
                                $defaults = \App\Models\AuctionTemplate::getDefaultPositions();
                                $posStyle = 'bottom:'.($defaults[$elKey]['bottom'] ?? 0).'px';
                            } else {
                                $defaults = \App\Models\AuctionTemplate::getDefaultPositions();
                                $posStyle = 'top:'.($defaults[$elKey]['top'] ?? 0).'px';
                            }
                            $defaults = \App\Models\AuctionTemplate::getDefaultPositions();
                        @endphp
                        <div class="drag-el" data-key="{{ $elKey }}" data-type="{{ $elInfo['type'] }}"
                             data-color="{{ $p['color'] ?? $defaultStyling['color'] }}"
                             data-bg-color="{{ $p['bgColor'] ?? $defaultStyling['bgColor'] }}"
                             data-opacity="{{ $p['opacity'] ?? $defaultStyling['opacity'] }}"
                             data-bg-opacity="{{ $p['bgOpacity'] ?? $defaultStyling['bgOpacity'] }}"
                             data-border-radius="{{ $p['borderRadius'] ?? $defaultStyling['borderRadius'] }}"
                             data-br-tl="{{ $p['borderRadiusTL'] ?? '' }}"
                             data-br-tr="{{ $p['borderRadiusTR'] ?? '' }}"
                             data-br-bl="{{ $p['borderRadiusBL'] ?? '' }}"
                             data-br-br="{{ $p['borderRadiusBR'] ?? '' }}"
                             data-box-shadow="{{ $p['boxShadow'] ?? $defaultStyling['boxShadow'] }}"
                             data-text-shadow="{{ $p['textShadow'] ?? $defaultStyling['textShadow'] }}"
                             data-z-index="{{ $p['zIndex'] ?? $defaultStyling['zIndex'] }}"
                             data-visible="{{ ($p['visible'] ?? $defaultStyling['visible']) ? '1' : '0' }}"
                             data-font-weight="{{ $p['fontWeight'] ?? $defaultStyling['fontWeight'] }}"
                             data-padding="{{ $p['padding'] ?? $defaultStyling['padding'] }}"
                             data-pad-top="{{ $p['paddingTop'] ?? '' }}"
                             data-pad-right="{{ $p['paddingRight'] ?? '' }}"
                             data-pad-bottom="{{ $p['paddingBottom'] ?? '' }}"
                             data-pad-left="{{ $p['paddingLeft'] ?? '' }}"
                             data-margin="{{ $p['margin'] ?? $defaultStyling['margin'] }}"
                             data-letter-spacing="{{ $p['letterSpacing'] ?? $defaultStyling['letterSpacing'] }}"
                             data-line-height="{{ $p['lineHeight'] ?? $defaultStyling['lineHeight'] }}"
                             data-text-align="{{ $p['textAlign'] ?? $defaultStyling['textAlign'] }}"
                             data-text-transform="{{ $p['textTransform'] ?? $defaultStyling['textTransform'] }}"
                             data-rotation="{{ $p['rotation'] ?? $defaultStyling['rotation'] }}"
                             data-border-style="{{ $p['borderStyle'] ?? $defaultStyling['borderStyle'] }}"
                             data-border-color-val="{{ $p['borderColor'] ?? $defaultStyling['borderColor'] }}"
                             data-border-width="{{ $p['borderWidth'] ?? $defaultStyling['borderWidth'] }}"
                             data-el-width="{{ $p['width'] ?? '' }}"
                             data-el-height="{{ $p['height'] ?? '' }}"
                             data-header-bg="{{ $p['headerBg'] ?? '' }}"
                             data-header-color="{{ $p['headerColor'] ?? '' }}"
                             data-row-bg="{{ $p['rowBg'] ?? '' }}"
                             data-cell-color="{{ $p['cellColor'] ?? '' }}"
                             data-cell-padding="{{ $p['cellPadding'] ?? '' }}"
                             data-table-border-color="{{ $p['tableBorderColor'] ?? '' }}"
                             data-table-border-width="{{ $p['tableBorderWidth'] ?? '' }}"
                             data-table-columns="{{ $p['tableColumns'] ?? '' }}"
                             style="position:absolute;cursor:move;border:2px dashed rgba({{ $elInfo['borderColor'] }},0.7);background:rgba({{ $elInfo['borderColor'] }},0.1);
                             {{ $posStyle }};
                             left:{{ $p['left'] ?? ($defaults[$elKey]['left'] ?? 0) }}px;
                             @if($isBoxOrImage) width:{{ $p['width'] ?? ($defaults[$elKey]['width'] ?? 150) }}px;height:{{ $p['height'] ?? ($defaults[$elKey]['height'] ?? 150) }}px;display:flex;align-items:center;justify-content:center; @endif
                             @if(!$isBoxOrImage) font-size:{{ $p['fontSize'] ?? ($defaults[$elKey]['fontSize'] ?? 30) }}px;color:#fff;font-weight:bold;white-space:nowrap;padding:2px 8px; @endif
                             {{ ($p['visible'] ?? true) ? '' : 'opacity:0.3;' }}">
                            <div class="drag-label">{{ $elInfo['label'] }}</div>
                            @if($elKey === 'sold_badge')
                                @if($isEdit && $template->sold_badge_image)
                                    <img src="{{ asset('storage/' . $template->sold_badge_image) }}" class="w-full h-full object-contain opacity-70">
                                @else
                                    <span class="text-yellow-400 text-xs">SOLD</span>
                                @endif
                            @elseif($elKey === 'stats_table')
                                @php
                                    $cols = json_decode($p['tableColumns'] ?? '[]', true) ?: [
                                        ['label'=>'Matches','field'=>'total_matches'],
                                        ['label'=>'Runs','field'=>'total_runs'],
                                        ['label'=>'Wickets','field'=>'total_wickets'],
                                    ];
                                    $tCP = $p['cellPadding'] ?? 10;
                                    $tBW = $p['tableBorderWidth'] ?? 1;
                                    $tBC = $p['tableBorderColor'] ?? 'rgba(255,255,255,0.2)';
                                    $bdr = $tBW.'px solid '.$tBC;
                                @endphp
                                <table style="width:100%;height:100%;border-collapse:collapse;font-size:{{ $p['fontSize'] ?? 20 }}px;pointer-events:none;">
                                    <thead><tr style="background:{{ $p['headerBg'] ?? 'rgba(0,0,0,0.7)' }};color:{{ $p['headerColor'] ?? '#fff' }};">
                                        @foreach($cols as $col)
                                        <th style="padding:{{ $tCP }}px;border:{{ $bdr }};{{ !empty($col['headerBg']) ? 'background:'.$col['headerBg'].';' : '' }}{{ !empty($col['headerColor']) ? 'color:'.$col['headerColor'].';' : '' }}{{ !empty($col['width']) ? 'width:'.$col['width'].';' : '' }}">{{ $col['label'] ?? '' }}</th>
                                        @endforeach
                                    </tr></thead>
                                    <tbody><tr style="background:{{ $p['rowBg'] ?? 'rgba(255,255,255,0.1)' }};color:{{ $p['cellColor'] ?? '#fff' }};">
                                        @foreach($cols as $col)
                                        <td style="padding:{{ $tCP }}px;border:{{ $bdr }};text-align:center;{{ !empty($col['cellBg']) ? 'background:'.$col['cellBg'].';' : '' }}{{ !empty($col['cellColor']) ? 'color:'.$col['cellColor'].';' : '' }}">0</td>
                                        @endforeach
                                    </tr></tbody>
                                </table>
                            @else
                                {!! $elInfo['content'] !!}
                            @endif
                        </div>
                    @endforeach

                    {{-- Render existing custom elements --}}
                    @foreach($positions as $cKey => $cVal)
                        @if(str_starts_with($cKey, 'custom_text_'))
                            <div class="drag-el custom-el" data-key="{{ $cKey }}" data-type="custom_text"
                                 data-color="{{ $cVal['color'] ?? '#ffffff' }}"
                                 data-bg-color="{{ $cVal['bgColor'] ?? '' }}"
                                 data-opacity="{{ $cVal['opacity'] ?? 1 }}"
                                 data-bg-opacity="{{ $cVal['bgOpacity'] ?? 1 }}"
                                 data-border-radius="{{ $cVal['borderRadius'] ?? 0 }}"
                                 data-br-tl="{{ $cVal['borderRadiusTL'] ?? '' }}"
                                 data-br-tr="{{ $cVal['borderRadiusTR'] ?? '' }}"
                                 data-br-bl="{{ $cVal['borderRadiusBL'] ?? '' }}"
                                 data-br-br="{{ $cVal['borderRadiusBR'] ?? '' }}"
                                 data-box-shadow="{{ $cVal['boxShadow'] ?? 'none' }}"
                                 data-text-shadow="{{ $cVal['textShadow'] ?? 'none' }}"
                                 data-z-index="{{ $cVal['zIndex'] ?? 10 }}"
                                 data-visible="{{ ($cVal['visible'] ?? true) ? '1' : '0' }}"
                                 data-font-weight="{{ $cVal['fontWeight'] ?? 'bold' }}"
                                 data-padding="{{ $cVal['padding'] ?? 0 }}"
                                 data-pad-top="{{ $cVal['paddingTop'] ?? '' }}"
                                 data-pad-right="{{ $cVal['paddingRight'] ?? '' }}"
                                 data-pad-bottom="{{ $cVal['paddingBottom'] ?? '' }}"
                                 data-pad-left="{{ $cVal['paddingLeft'] ?? '' }}"
                                 data-content="{{ $cVal['content'] ?? 'Text' }}"
                                 data-margin="{{ $cVal['margin'] ?? 0 }}"
                                 data-letter-spacing="{{ $cVal['letterSpacing'] ?? 0 }}"
                                 data-line-height="{{ $cVal['lineHeight'] ?? '' }}"
                                 data-text-align="{{ $cVal['textAlign'] ?? 'left' }}"
                                 data-text-transform="{{ $cVal['textTransform'] ?? 'none' }}"
                                 data-rotation="{{ $cVal['rotation'] ?? 0 }}"
                                 data-border-style="{{ $cVal['borderStyle'] ?? 'none' }}"
                                 data-border-color-val="{{ $cVal['borderColor'] ?? '' }}"
                                 data-border-width="{{ $cVal['borderWidth'] ?? 0 }}"
                                 data-el-width="{{ $cVal['width'] ?? '' }}"
                                 data-el-height="{{ $cVal['height'] ?? '' }}"
                                 style="position:absolute;cursor:move;border:2px dashed rgba(168,85,247,0.7);background:rgba(168,85,247,0.1);
                                 top:{{ $cVal['top'] ?? 100 }}px;left:{{ $cVal['left'] ?? 100 }}px;
                                 font-size:{{ $cVal['fontSize'] ?? 24 }}px;color:{{ $cVal['color'] ?? '#ffffff' }};font-weight:{{ $cVal['fontWeight'] ?? 'bold' }};white-space:nowrap;padding:2px 8px;">
                                <div class="drag-label">{{ $cKey }} <span class="custom-delete" onclick="deleteCustomElement(this.closest('.drag-el'))" style="cursor:pointer;color:#ef4444;margin-left:4px;">x</span></div>
                                {{ $cVal['content'] ?? 'Text' }}
                            </div>
                        @elseif(str_starts_with($cKey, 'custom_shape_'))
                            <div class="drag-el custom-el" data-key="{{ $cKey }}" data-type="custom_shape"
                                 data-color="{{ $cVal['color'] ?? '#ffffff' }}"
                                 data-bg-color="{{ $cVal['bgColor'] ?? 'rgba(255,255,255,0.1)' }}"
                                 data-opacity="{{ $cVal['opacity'] ?? 1 }}"
                                 data-bg-opacity="{{ $cVal['bgOpacity'] ?? 1 }}"
                                 data-border-radius="{{ $cVal['borderRadius'] ?? 0 }}"
                                 data-br-tl="{{ $cVal['borderRadiusTL'] ?? '' }}"
                                 data-br-tr="{{ $cVal['borderRadiusTR'] ?? '' }}"
                                 data-br-bl="{{ $cVal['borderRadiusBL'] ?? '' }}"
                                 data-br-br="{{ $cVal['borderRadiusBR'] ?? '' }}"
                                 data-box-shadow="{{ $cVal['boxShadow'] ?? 'none' }}"
                                 data-text-shadow="{{ $cVal['textShadow'] ?? 'none' }}"
                                 data-z-index="{{ $cVal['zIndex'] ?? 10 }}"
                                 data-visible="{{ ($cVal['visible'] ?? true) ? '1' : '0' }}"
                                 data-font-weight="{{ $cVal['fontWeight'] ?? 'bold' }}"
                                 data-padding="{{ $cVal['padding'] ?? 0 }}"
                                 data-pad-top="{{ $cVal['paddingTop'] ?? '' }}"
                                 data-pad-right="{{ $cVal['paddingRight'] ?? '' }}"
                                 data-pad-bottom="{{ $cVal['paddingBottom'] ?? '' }}"
                                 data-pad-left="{{ $cVal['paddingLeft'] ?? '' }}"
                                 data-shape-type="{{ $cVal['shapeType'] ?? 'rectangle' }}"
                                 data-border-color-val="{{ $cVal['borderColor'] ?? '' }}"
                                 data-border-width="{{ $cVal['borderWidth'] ?? 0 }}"
                                 data-margin="{{ $cVal['margin'] ?? 0 }}"
                                 data-letter-spacing="{{ $cVal['letterSpacing'] ?? 0 }}"
                                 data-line-height="{{ $cVal['lineHeight'] ?? '' }}"
                                 data-text-align="{{ $cVal['textAlign'] ?? 'left' }}"
                                 data-text-transform="{{ $cVal['textTransform'] ?? 'none' }}"
                                 data-rotation="{{ $cVal['rotation'] ?? 0 }}"
                                 data-border-style="{{ $cVal['borderStyle'] ?? 'none' }}"
                                 style="position:absolute;cursor:move;border:2px dashed rgba(99,102,241,0.7);
                                 top:{{ $cVal['top'] ?? 100 }}px;left:{{ $cVal['left'] ?? 100 }}px;
                                 width:{{ $cVal['width'] ?? 100 }}px;height:{{ $cVal['height'] ?? 60 }}px;
                                 background:{{ $cVal['bgColor'] ?? 'rgba(255,255,255,0.1)' }};
                                 border-radius:{{ $cVal['borderRadius'] ?? 0 }}px;
                                 {{ ($cVal['shapeType'] ?? 'rectangle') === 'circle' ? 'border-radius:50%;' : '' }}">
                                <div class="drag-label">{{ $cKey }} <span class="custom-delete" onclick="deleteCustomElement(this.closest('.drag-el'))" style="cursor:pointer;color:#ef4444;margin-left:4px;">x</span></div>
                            </div>
                        @elseif(str_starts_with($cKey, 'custom_image_') && !empty($cVal['imagePath']))
                            <div class="drag-el custom-el" data-key="{{ $cKey }}" data-type="custom_image"
                                 data-color="{{ $cVal['color'] ?? '#ffffff' }}"
                                 data-bg-color="{{ $cVal['bgColor'] ?? '' }}"
                                 data-opacity="{{ $cVal['opacity'] ?? 1 }}"
                                 data-bg-opacity="{{ $cVal['bgOpacity'] ?? 1 }}"
                                 data-border-radius="{{ $cVal['borderRadius'] ?? 0 }}"
                                 data-br-tl="{{ $cVal['borderRadiusTL'] ?? '' }}"
                                 data-br-tr="{{ $cVal['borderRadiusTR'] ?? '' }}"
                                 data-br-bl="{{ $cVal['borderRadiusBL'] ?? '' }}"
                                 data-br-br="{{ $cVal['borderRadiusBR'] ?? '' }}"
                                 data-box-shadow="{{ $cVal['boxShadow'] ?? 'none' }}"
                                 data-text-shadow="{{ $cVal['textShadow'] ?? 'none' }}"
                                 data-z-index="{{ $cVal['zIndex'] ?? 10 }}"
                                 data-visible="{{ ($cVal['visible'] ?? true) ? '1' : '0' }}"
                                 data-font-weight="{{ $cVal['fontWeight'] ?? 'bold' }}"
                                 data-padding="{{ $cVal['padding'] ?? 0 }}"
                                 data-pad-top="{{ $cVal['paddingTop'] ?? '' }}"
                                 data-pad-right="{{ $cVal['paddingRight'] ?? '' }}"
                                 data-pad-bottom="{{ $cVal['paddingBottom'] ?? '' }}"
                                 data-pad-left="{{ $cVal['paddingLeft'] ?? '' }}"
                                 data-image-path="{{ $cVal['imagePath'] }}"
                                 data-margin="{{ $cVal['margin'] ?? 0 }}"
                                 data-letter-spacing="{{ $cVal['letterSpacing'] ?? 0 }}"
                                 data-line-height="{{ $cVal['lineHeight'] ?? '' }}"
                                 data-text-align="{{ $cVal['textAlign'] ?? 'left' }}"
                                 data-text-transform="{{ $cVal['textTransform'] ?? 'none' }}"
                                 data-rotation="{{ $cVal['rotation'] ?? 0 }}"
                                 data-border-style="{{ $cVal['borderStyle'] ?? 'none' }}"
                                 data-border-color-val="{{ $cVal['borderColor'] ?? '' }}"
                                 data-border-width="{{ $cVal['borderWidth'] ?? 0 }}"
                                 data-el-width="{{ $cVal['width'] ?? '' }}"
                                 data-el-height="{{ $cVal['height'] ?? '' }}"
                                 style="position:absolute;cursor:move;border:2px dashed rgba(20,184,166,0.7);background:rgba(20,184,166,0.1);
                                 top:{{ $cVal['top'] ?? 100 }}px;left:{{ $cVal['left'] ?? 100 }}px;
                                 width:{{ $cVal['width'] ?? 150 }}px;height:{{ $cVal['height'] ?? 100 }}px;
                                 display:flex;align-items:center;justify-content:center;overflow:hidden;">
                                <div class="drag-label">{{ $cKey }} <span class="custom-delete" onclick="deleteCustomElement(this.closest('.drag-el'))" style="cursor:pointer;color:#ef4444;margin-left:4px;">x</span></div>
                                <img src="{{ asset('storage/' . $cVal['imagePath']) }}" style="width:100%;height:100%;object-fit:contain;pointer-events:none;">
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Hidden inputs for positions + styling (synced via JS) --}}
<div id="hidden-pos-inputs">
    @foreach(\App\Models\AuctionTemplate::getElementKeys() as $el)
        <input type="hidden" name="pos_{{ $el }}_top" id="hid_{{ $el }}_top" value="{{ $positions[$el]['top'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_left" id="hid_{{ $el }}_left" value="{{ $positions[$el]['left'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_bottom" id="hid_{{ $el }}_bottom" value="{{ $positions[$el]['bottom'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_width" id="hid_{{ $el }}_width" value="{{ $positions[$el]['width'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_height" id="hid_{{ $el }}_height" value="{{ $positions[$el]['height'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_fontSize" id="hid_{{ $el }}_fontSize" value="{{ $positions[$el]['fontSize'] ?? '' }}">
        {{-- Styling inputs --}}
        <input type="hidden" name="pos_{{ $el }}_color" id="hid_{{ $el }}_color" value="{{ $positions[$el]['color'] ?? '#ffffff' }}">
        <input type="hidden" name="pos_{{ $el }}_bgColor" id="hid_{{ $el }}_bgColor" value="{{ $positions[$el]['bgColor'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_opacity" id="hid_{{ $el }}_opacity" value="{{ $positions[$el]['opacity'] ?? 1 }}">
        <input type="hidden" name="pos_{{ $el }}_bgOpacity" id="hid_{{ $el }}_bgOpacity" value="{{ $positions[$el]['bgOpacity'] ?? 1 }}">
        <input type="hidden" name="pos_{{ $el }}_borderRadius" id="hid_{{ $el }}_borderRadius" value="{{ $positions[$el]['borderRadius'] ?? 0 }}">
        <input type="hidden" name="pos_{{ $el }}_borderRadiusTL" id="hid_{{ $el }}_borderRadiusTL" value="{{ $positions[$el]['borderRadiusTL'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_borderRadiusTR" id="hid_{{ $el }}_borderRadiusTR" value="{{ $positions[$el]['borderRadiusTR'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_borderRadiusBL" id="hid_{{ $el }}_borderRadiusBL" value="{{ $positions[$el]['borderRadiusBL'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_borderRadiusBR" id="hid_{{ $el }}_borderRadiusBR" value="{{ $positions[$el]['borderRadiusBR'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_boxShadow" id="hid_{{ $el }}_boxShadow" value="{{ $positions[$el]['boxShadow'] ?? 'none' }}">
        <input type="hidden" name="pos_{{ $el }}_textShadow" id="hid_{{ $el }}_textShadow" value="{{ $positions[$el]['textShadow'] ?? 'none' }}">
        <input type="hidden" name="pos_{{ $el }}_zIndex" id="hid_{{ $el }}_zIndex" value="{{ $positions[$el]['zIndex'] ?? 10 }}">
        <input type="hidden" name="pos_{{ $el }}_visible" id="hid_{{ $el }}_visible" value="{{ ($positions[$el]['visible'] ?? true) ? '1' : '0' }}">
        <input type="hidden" name="pos_{{ $el }}_fontWeight" id="hid_{{ $el }}_fontWeight" value="{{ $positions[$el]['fontWeight'] ?? 'bold' }}">
        <input type="hidden" name="pos_{{ $el }}_padding" id="hid_{{ $el }}_padding" value="{{ $positions[$el]['padding'] ?? 0 }}">
        <input type="hidden" name="pos_{{ $el }}_paddingTop" id="hid_{{ $el }}_paddingTop" value="{{ $positions[$el]['paddingTop'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_paddingRight" id="hid_{{ $el }}_paddingRight" value="{{ $positions[$el]['paddingRight'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_paddingBottom" id="hid_{{ $el }}_paddingBottom" value="{{ $positions[$el]['paddingBottom'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_paddingLeft" id="hid_{{ $el }}_paddingLeft" value="{{ $positions[$el]['paddingLeft'] ?? '' }}">
        {{-- New styling inputs --}}
        <input type="hidden" name="pos_{{ $el }}_margin" id="hid_{{ $el }}_margin" value="{{ $positions[$el]['margin'] ?? 0 }}">
        <input type="hidden" name="pos_{{ $el }}_letterSpacing" id="hid_{{ $el }}_letterSpacing" value="{{ $positions[$el]['letterSpacing'] ?? 0 }}">
        <input type="hidden" name="pos_{{ $el }}_lineHeight" id="hid_{{ $el }}_lineHeight" value="{{ $positions[$el]['lineHeight'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_textAlign" id="hid_{{ $el }}_textAlign" value="{{ $positions[$el]['textAlign'] ?? 'left' }}">
        <input type="hidden" name="pos_{{ $el }}_textTransform" id="hid_{{ $el }}_textTransform" value="{{ $positions[$el]['textTransform'] ?? 'none' }}">
        <input type="hidden" name="pos_{{ $el }}_rotation" id="hid_{{ $el }}_rotation" value="{{ $positions[$el]['rotation'] ?? 0 }}">
        <input type="hidden" name="pos_{{ $el }}_borderStyle" id="hid_{{ $el }}_borderStyle" value="{{ $positions[$el]['borderStyle'] ?? 'none' }}">
        <input type="hidden" name="pos_{{ $el }}_borderColor" id="hid_{{ $el }}_borderColor" value="{{ $positions[$el]['borderColor'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_borderWidth" id="hid_{{ $el }}_borderWidth" value="{{ $positions[$el]['borderWidth'] ?? 0 }}">
        {{-- Table-specific --}}
        <input type="hidden" name="pos_{{ $el }}_headerBg" id="hid_{{ $el }}_headerBg" value="{{ $positions[$el]['headerBg'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_headerColor" id="hid_{{ $el }}_headerColor" value="{{ $positions[$el]['headerColor'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_rowBg" id="hid_{{ $el }}_rowBg" value="{{ $positions[$el]['rowBg'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_cellColor" id="hid_{{ $el }}_cellColor" value="{{ $positions[$el]['cellColor'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_cellPadding" id="hid_{{ $el }}_cellPadding" value="{{ $positions[$el]['cellPadding'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_tableBorderColor" id="hid_{{ $el }}_tableBorderColor" value="{{ $positions[$el]['tableBorderColor'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_tableBorderWidth" id="hid_{{ $el }}_tableBorderWidth" value="{{ $positions[$el]['tableBorderWidth'] ?? '' }}">
        <input type="hidden" name="pos_{{ $el }}_tableColumns" id="hid_{{ $el }}_tableColumns" value="{{ $positions[$el]['tableColumns'] ?? '' }}">
    @endforeach
</div>
<div id="custom-hidden-inputs">
    {{-- JS-generated for custom elements --}}
</div>

<style>
    .drag-el {
        z-index: 10;
        user-select: none;
        transition: box-shadow 0.15s;
    }
    .drag-el:hover, .drag-el.active {
        box-shadow: 0 0 0 2px #3b82f6, 0 0 20px rgba(59,130,246,0.3);
        z-index: 20;
    }
    .drag-el.active {
        box-shadow: 0 0 0 3px #f59e0b, 0 0 20px rgba(245,158,11,0.4);
    }
    .drag-el.dragging {
        opacity: 0.85;
        z-index: 30;
    }
    .drag-el.el-hidden {
        opacity: 0.25 !important;
        border-style: dotted !important;
    }
    .drag-label {
        position: absolute;
        top: -20px;
        left: 0;
        font-size: 10px;
        font-weight: 600;
        color: #fff;
        background: rgba(0,0,0,0.7);
        padding: 1px 6px;
        border-radius: 3px;
        white-space: nowrap;
        pointer-events: none;
        line-height: 1.4;
    }
    .drag-label .custom-delete {
        pointer-events: all;
    }
    .resize-handle {
        position: absolute;
        bottom: -4px;
        right: -4px;
        width: 10px;
        height: 10px;
        background: #3b82f6;
        border: 1px solid #fff;
        border-radius: 2px;
        cursor: nwse-resize;
        z-index: 5;
    }
    #element-props {
        box-shadow: 0 8px 40px rgba(0,0,0,0.25), 0 0 0 1px rgba(59,130,246,0.3);
        backdrop-filter: blur(8px);
        transition: box-shadow 0.2s;
    }
    #element-props:hover {
        box-shadow: 0 12px 50px rgba(0,0,0,0.3), 0 0 0 1px rgba(59,130,246,0.5);
    }
    #props-body::-webkit-scrollbar { width: 5px; }
    #props-body::-webkit-scrollbar-track { background: transparent; }
    #props-body::-webkit-scrollbar-thumb { background: rgba(59,130,246,0.3); border-radius: 3px; }
    #props-body::-webkit-scrollbar-thumb:hover { background: rgba(59,130,246,0.5); }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('canvas-container');
    const canvasW = {{ $cw }};
    const canvasH = {{ $ch }};
    let scale = 1;
    let activeEl = null;
    let customTextCount = 0;
    let customShapeCount = 0;
    let customImageCount = 0;

    // Default positions for reset
    const defaults = @json($defaultPositions);

    // Box shadow preset map
    const boxShadowPresets = {
        none: 'none',
        small: '0 2px 8px rgba(0,0,0,0.3)',
        medium: '0 4px 20px rgba(0,0,0,0.4)',
        large: '0 8px 40px rgba(0,0,0,0.5)',
        glow: '0 0 20px rgba(59,130,246,0.6)'
    };

    const textShadowPresets = {
        none: 'none',
        subtle: '0 1px 3px rgba(0,0,0,0.5)',
        strong: '0 2px 8px rgba(0,0,0,0.8)',
        glow: '0 0 10px rgba(59,130,246,0.6), 0 0 20px rgba(59,130,246,0.3)'
    };

    // Element metadata
    const elMeta = {
        player_image: { label: 'Player Image', color: '#3b82f6', hasBottom: true, hasWidth: true, hasHeight: true, isImage: true },
        player_name: { label: 'Player Name', color: '#22c55e', hasFontSize: true },
        player_role: { label: 'Player Role', color: '#a855f7', hasFontSize: true },
        batting_style: { label: 'Batting Style', color: '#eab308', hasFontSize: true },
        bowling_style: { label: 'Bowling Style', color: '#f97316', hasFontSize: true },
        current_bid: { label: 'Current Bid', color: '#ef4444', hasFontSize: true, hasBottom: true },
        bid_label: { label: 'Bid Label', color: '#ec4899', hasFontSize: true, hasBottom: true },
        sold_badge: { label: 'Sold Badge', color: '#10b981', hasBottom: true, hasWidth: true, hasHeight: true, isImage: true },
        team_logo: { label: 'Team Logo', color: '#06b6d4', hasBottom: true, hasWidth: true, hasHeight: true, isImage: true },
        highest_bidder: { label: 'Highest Bidder', color: '#00ff00', hasFontSize: true },
        stats_table: { label: 'Stats Table', color: '#9333ea', hasWidth: true, hasHeight: true, hasFontSize: true, isTable: true },
    };

    // Auto-scale canvas to fit wrapper
    function scaleCanvas() {
        const wrapper = document.getElementById('canvas-wrapper');
        const maxW = wrapper.clientWidth - 4;
        scale = Math.min(maxW / canvasW, 1);
        canvas.style.transform = `scale(${scale})`;
        canvas.style.marginBottom = ((canvasH * scale) - canvasH) + 'px';
        document.getElementById('canvas-scale-info').textContent = `Scale: ${Math.round(scale * 100)}%`;
    }
    scaleCanvas();
    window.addEventListener('resize', scaleCanvas);

    // Add resize handles to box/image/shape elements
    function addResizeHandle(el) {
        if (el.querySelector('.resize-handle')) return;
        const type = el.dataset.type;
        if (type === 'box' || type === 'image' || type === 'custom_shape' || type === 'custom_image' || type === 'table') {
            const handle = document.createElement('div');
            handle.className = 'resize-handle';
            el.appendChild(handle);
        }
    }
    document.querySelectorAll('.drag-el').forEach(addResizeHandle);

    // ── Apply visual styling to element in editor ──
    function applyVisualStyling(el) {
        const visible = el.dataset.visible !== '0';
        el.classList.toggle('el-hidden', !visible);

        // Background color with bgOpacity
        const bgColor = el.dataset.bgColor || '';
        const bgOpacity = parseFloat(el.dataset.bgOpacity ?? 1);
        if (bgColor && bgOpacity < 1) {
            // Apply bgOpacity: convert hex to rgba, or wrap with opacity
            const hex = bgColor.match(/^#([0-9a-f]{6})$/i);
            if (hex) {
                const r = parseInt(hex[1].substring(0,2), 16);
                const g = parseInt(hex[1].substring(2,4), 16);
                const b = parseInt(hex[1].substring(4,6), 16);
                el.style.background = `rgba(${r},${g},${b},${bgOpacity})`;
            } else {
                el.style.background = bgColor;
            }
        } else {
            el.style.background = bgColor || '';
        }

        // Text color (for text/custom_text elements)
        const type = el.dataset.type;
        if (type === 'text' || type === 'custom_text') {
            el.style.color = el.dataset.color || '#ffffff';
        }

        // Opacity
        if (visible) {
            el.style.opacity = el.dataset.opacity || 1;
        }

        // Border radius — per-corner or uniform
        const brTL = el.dataset.brTl || '';
        const brTR = el.dataset.brTr || '';
        const brBL = el.dataset.brBl || '';
        const brBR = el.dataset.brBr || '';
        if (brTL || brTR || brBL || brBR) {
            el.style.borderRadius = `${brTL || 0}px ${brTR || 0}px ${brBR || 0}px ${brBL || 0}px`;
        } else {
            const br = parseInt(el.dataset.borderRadius) || 0;
            el.style.borderRadius = br > 0 ? br + 'px' : '';
        }

        // Padding — per-side or uniform
        const padT = el.dataset.padTop || '';
        const padR = el.dataset.padRight || '';
        const padB = el.dataset.padBottom || '';
        const padL = el.dataset.padLeft || '';
        if (padT || padR || padB || padL) {
            el.style.padding = `${padT || 0}px ${padR || 0}px ${padB || 0}px ${padL || 0}px`;
        } else {
            const pad = parseInt(el.dataset.padding) || 0;
            el.style.padding = pad > 0 ? pad + 'px' : '';
        }

        // Font weight
        if (type === 'text' || type === 'custom_text') {
            el.style.fontWeight = el.dataset.fontWeight || 'bold';
        }

        // Box shadow
        const bs = el.dataset.boxShadow || 'none';
        el.style.boxShadow = (bs !== 'none' && boxShadowPresets[bs]) ? boxShadowPresets[bs] : '';

        // Text shadow
        if (type === 'text' || type === 'custom_text') {
            const ts = el.dataset.textShadow || 'none';
            el.style.textShadow = (ts !== 'none' && textShadowPresets[ts]) ? textShadowPresets[ts] : '';
        }

        // Text align
        if (type === 'text' || type === 'custom_text') {
            el.style.textAlign = el.dataset.textAlign || 'left';
        }

        // Text transform
        if (type === 'text' || type === 'custom_text') {
            const tt = el.dataset.textTransform || 'none';
            el.style.textTransform = tt !== 'none' ? tt : '';
        }

        // Letter spacing
        const ls = parseInt(el.dataset.letterSpacing) || 0;
        if (ls) el.style.letterSpacing = ls + 'px';
        else el.style.letterSpacing = '';

        // Line height
        const lh = el.dataset.lineHeight || '';
        if (lh) el.style.lineHeight = lh;
        else el.style.lineHeight = '';

        // Margin
        const margin = parseInt(el.dataset.margin) || 0;
        if (margin > 0) el.style.margin = margin + 'px';
        else el.style.margin = '';

        // Rotation
        const rot = parseInt(el.dataset.rotation) || 0;
        if (rot !== 0) el.style.transform = `rotate(${rot}deg)`;
        else el.style.transform = '';

        // Border (style/color/width)
        const bStyle = el.dataset.borderStyle || 'none';
        if (bStyle !== 'none') {
            const bColor = el.dataset.borderColorVal || '#ffffff';
            const bWidth = parseInt(el.dataset.borderWidth) || 1;
            el.style.borderWidth = bWidth + 'px';
            el.style.borderStyle = bStyle;
            el.style.borderColor = bColor;
        }

        // Element width/height for text elements
        const elW = el.dataset.elWidth || '';
        const elH = el.dataset.elHeight || '';
        if ((type === 'text' || type === 'custom_text') && elW) {
            el.style.width = elW + 'px';
            el.style.whiteSpace = 'normal';
            el.style.wordWrap = 'break-word';
        } else if (type === 'text' || type === 'custom_text') {
            el.style.whiteSpace = 'nowrap';
        }
        if ((type === 'text' || type === 'custom_text') && elH) {
            el.style.height = elH + 'px';
        }
    }

    // Apply initial styling
    document.querySelectorAll('.drag-el').forEach(applyVisualStyling);

    // ── Drag logic ──
    let dragTarget = null, resizeTarget = null;
    let startX, startY, origLeft, origTop, origW, origH;

    canvas.addEventListener('pointerdown', (e) => {
        // Allow delete button clicks
        if (e.target.classList.contains('custom-delete')) return;

        const handle = e.target.closest('.resize-handle');
        const el = e.target.closest('.drag-el');
        if (!el) { deselectAll(); return; }

        e.preventDefault();
        selectElement(el);

        startX = e.clientX;
        startY = e.clientY;

        if (handle) {
            resizeTarget = el;
            origW = el.offsetWidth;
            origH = el.offsetHeight;
        } else {
            dragTarget = el;
            origLeft = el.offsetLeft;
            origTop = el.offsetTop;
            el.classList.add('dragging');
        }

        document.addEventListener('pointermove', onPointerMove);
        document.addEventListener('pointerup', onPointerUp);
    });

    function onPointerMove(e) {
        const dx = (e.clientX - startX) / scale;
        const dy = (e.clientY - startY) / scale;

        if (dragTarget) {
            let newLeft = Math.round(origLeft + dx);
            let newTop = Math.round(origTop + dy);
            newLeft = Math.max(0, Math.min(canvasW - dragTarget.offsetWidth, newLeft));
            newTop = Math.max(0, Math.min(canvasH - dragTarget.offsetHeight, newTop));
            dragTarget.style.left = newLeft + 'px';
            dragTarget.style.top = newTop + 'px';
            dragTarget.style.bottom = 'auto';
            updatePropsPanel(dragTarget);
        }

        if (resizeTarget) {
            let newW = Math.max(30, Math.round(origW + dx));
            let newH = Math.max(30, Math.round(origH + dy));
            resizeTarget.style.width = newW + 'px';
            resizeTarget.style.height = newH + 'px';
            updatePropsPanel(resizeTarget);
        }
    }

    function onPointerUp(e) {
        if (dragTarget) {
            dragTarget.classList.remove('dragging');
            syncToHidden(dragTarget);
            dragTarget = null;
        }
        if (resizeTarget) {
            syncToHidden(resizeTarget);
            resizeTarget = null;
        }
        document.removeEventListener('pointermove', onPointerMove);
        document.removeEventListener('pointerup', onPointerUp);
    }

    // ── Select / Props panel (floating) ──
    function deselectAll() {
        document.querySelectorAll('.drag-el').forEach(el => el.classList.remove('active'));
        const panel = document.getElementById('element-props');
        panel.classList.add('hidden');
        panel.style.display = 'none';
        activeEl = null;
    }

    function selectElement(el) {
        document.querySelectorAll('.drag-el').forEach(e => e.classList.remove('active'));
        el.classList.add('active');
        activeEl = el;
        updatePropsPanel(el);
        const panel = document.getElementById('element-props');
        panel.classList.remove('hidden');
        panel.style.display = '';
    }

    // ── Floating panel drag ──
    (function() {
        const panel = document.getElementById('element-props');
        const header = document.getElementById('props-header');
        let isDragging = false, dragStartX, dragStartY, panelStartX, panelStartY;

        header.addEventListener('pointerdown', (e) => {
            if (e.target.closest('button')) return;
            isDragging = true;
            dragStartX = e.clientX;
            dragStartY = e.clientY;
            const rect = panel.getBoundingClientRect();
            panelStartX = rect.left;
            panelStartY = rect.top;
            header.setPointerCapture(e.pointerId);
            e.preventDefault();
        });

        header.addEventListener('pointermove', (e) => {
            if (!isDragging) return;
            const dx = e.clientX - dragStartX;
            const dy = e.clientY - dragStartY;
            let newLeft = Math.max(0, Math.min(window.innerWidth - panel.offsetWidth, panelStartX + dx));
            let newTop = Math.max(0, Math.min(window.innerHeight - 40, panelStartY + dy));
            panel.style.left = newLeft + 'px';
            panel.style.top = newTop + 'px';
            panel.style.right = 'auto';
        });

        header.addEventListener('pointerup', () => { isDragging = false; });
    })();

    // ── Collapse/expand panel body ──
    window.togglePropsCollapse = function() {
        const body = document.getElementById('props-body');
        const btn = document.getElementById('props-collapse-btn');
        if (body.style.display === 'none') {
            body.style.display = '';
            btn.innerHTML = '&#9660;';
        } else {
            body.style.display = 'none';
            btn.innerHTML = '&#9654;';
        }
    };

    function getElMeta(el) {
        const key = el.dataset.key;
        if (elMeta[key]) return elMeta[key];
        // Custom elements
        const type = el.dataset.type;
        if (type === 'custom_text') {
            return { label: key, color: '#a855f7', hasFontSize: true, isCustomText: true };
        }
        if (type === 'custom_shape') {
            return { label: key, color: '#6366f1', hasWidth: true, hasHeight: true, isCustomShape: true };
        }
        if (type === 'custom_image') {
            return { label: key, color: '#14b8a6', hasWidth: true, hasHeight: true, isImage: true, isCustomImage: true };
        }
        return { label: key, color: '#888' };
    }

    function updatePropsPanel(el) {
        const key = el.dataset.key;
        const meta = getElMeta(el);
        document.getElementById('prop-title').textContent = meta.label;
        document.getElementById('prop-color').style.background = meta.color;

        const top = el.offsetTop;
        const left = el.offsetLeft;
        document.getElementById('prop-top').value = top;
        document.getElementById('prop-left').value = left;

        // Show/hide relevant fields
        const wWrap = document.getElementById('prop-width-wrap');
        const hWrap = document.getElementById('prop-height-wrap');
        const fsWrap = document.getElementById('prop-fontsize-wrap');
        const bWrap = document.getElementById('prop-bottom-wrap');

        wWrap.style.display = (meta.hasWidth) ? '' : 'none';
        hWrap.style.display = (meta.hasHeight) ? '' : 'none';
        fsWrap.style.display = (meta.hasFontSize) ? '' : 'none';
        bWrap.style.display = (meta.hasBottom) ? '' : 'none';

        if (meta.hasWidth) document.getElementById('prop-width').value = el.offsetWidth;
        if (meta.hasHeight) document.getElementById('prop-height').value = el.offsetHeight;
        if (meta.hasFontSize) {
            document.getElementById('prop-fontsize').value = parseInt(getComputedStyle(el).fontSize) || 30;
        }
        if (meta.hasBottom) {
            const bottom = canvasH - top - el.offsetHeight;
            document.getElementById('prop-bottom').value = bottom;
        }

        // Styling fields
        document.getElementById('prop-visible').checked = el.dataset.visible !== '0';
        document.getElementById('prop-text-color').value = el.dataset.color || '#ffffff';
        document.getElementById('prop-text-color-hex').value = el.dataset.color || '#ffffff';
        document.getElementById('prop-bg-color-text').value = el.dataset.bgColor || '';
        if (el.dataset.bgColor && el.dataset.bgColor.startsWith('#')) {
            document.getElementById('prop-bg-color').value = el.dataset.bgColor;
        }
        document.getElementById('prop-opacity').value = el.dataset.opacity || 1;
        document.getElementById('prop-opacity-val').textContent = el.dataset.opacity || 1;
        document.getElementById('prop-bg-opacity').value = el.dataset.bgOpacity ?? 1;
        document.getElementById('prop-bg-opacity-val').textContent = el.dataset.bgOpacity ?? 1;
        document.getElementById('prop-border-radius').value = el.dataset.borderRadius || 0;
        document.getElementById('prop-br-tl').value = el.dataset.brTl || '';
        document.getElementById('prop-br-tr').value = el.dataset.brTr || '';
        document.getElementById('prop-br-bl').value = el.dataset.brBl || '';
        document.getElementById('prop-br-br').value = el.dataset.brBr || '';
        document.getElementById('prop-padding').value = el.dataset.padding || 0;
        document.getElementById('prop-pad-top').value = el.dataset.padTop || '';
        document.getElementById('prop-pad-right').value = el.dataset.padRight || '';
        document.getElementById('prop-pad-bottom').value = el.dataset.padBottom || '';
        document.getElementById('prop-pad-left').value = el.dataset.padLeft || '';
        document.getElementById('prop-zindex').value = el.dataset.zIndex || 10;
        document.getElementById('prop-box-shadow').value = el.dataset.boxShadow || 'none';
        document.getElementById('prop-text-shadow').value = el.dataset.textShadow || 'none';
        document.getElementById('prop-font-weight').value = el.dataset.fontWeight || 'bold';

        // Show/hide contextual styling fields
        const isImage = meta.isImage;
        const isText = !isImage && !meta.isCustomShape;
        document.getElementById('prop-text-color-wrap').style.display = isImage ? 'none' : '';
        document.getElementById('prop-text-shadow-wrap').style.display = (isImage || meta.isCustomShape) ? 'none' : '';
        document.getElementById('prop-font-weight-wrap').style.display = (isImage || meta.isCustomShape) ? 'none' : '';

        // Typography fields - only for text elements
        document.getElementById('prop-text-align-wrap').style.display = isText ? '' : 'none';
        document.getElementById('prop-text-transform-wrap').style.display = isText ? '' : 'none';
        document.getElementById('prop-letter-spacing-wrap').style.display = isText ? '' : 'none';
        document.getElementById('prop-line-height-wrap').style.display = isText ? '' : 'none';

        // New typography values
        document.getElementById('prop-text-align').value = el.dataset.textAlign || 'left';
        document.getElementById('prop-text-transform').value = el.dataset.textTransform || 'none';
        document.getElementById('prop-letter-spacing').value = el.dataset.letterSpacing || 0;
        document.getElementById('prop-line-height').value = el.dataset.lineHeight || '';

        // Layout
        document.getElementById('prop-margin').value = el.dataset.margin || 0;

        // Element width/height - show for ALL types
        document.getElementById('prop-el-width-wrap').style.display = '';
        document.getElementById('prop-el-height-wrap').style.display = '';
        document.getElementById('prop-el-width').value = el.dataset.elWidth || '';
        document.getElementById('prop-el-height').value = el.dataset.elHeight || '';

        // Border
        document.getElementById('prop-border-style').value = el.dataset.borderStyle || 'none';
        document.getElementById('prop-el-border-color').value = el.dataset.borderColorVal || '#ffffff';
        document.getElementById('prop-el-border-width').value = el.dataset.borderWidth || 0;

        // Rotation
        document.getElementById('prop-rotation').value = el.dataset.rotation || 0;
        document.getElementById('prop-rotation-val').textContent = el.dataset.rotation || 0;

        // Custom element fields
        document.getElementById('prop-content-wrap').classList.toggle('hidden', !meta.isCustomText);
        document.getElementById('prop-shape-wrap').classList.toggle('hidden', !meta.isCustomShape);
        document.getElementById('prop-table-wrap').classList.toggle('hidden', !meta.isTable);
        if (meta.isTable) {
            document.getElementById('prop-header-bg').value = el.dataset.headerBg || '';
            document.getElementById('prop-header-color').value = el.dataset.headerColor || '';
            document.getElementById('prop-row-bg').value = el.dataset.rowBg || '';
            document.getElementById('prop-cell-color').value = el.dataset.cellColor || '';
            document.getElementById('prop-cell-padding').value = el.dataset.cellPadding || '';
            document.getElementById('prop-table-border-color').value = el.dataset.tableBorderColor || '';
            document.getElementById('prop-table-border-width').value = el.dataset.tableBorderWidth || '';
            loadColumnsEditor(el);
        }
        if (meta.isCustomText) {
            document.getElementById('prop-content').value = el.dataset.content || '';
        }
        if (meta.isCustomShape) {
            document.getElementById('prop-shape-type').value = el.dataset.shapeType || 'rectangle';
        }
    }

    // ── Styling props → element sync ──
    document.getElementById('prop-visible').addEventListener('change', (e) => {
        if (!activeEl) return;
        activeEl.dataset.visible = e.target.checked ? '1' : '0';
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-text-color').addEventListener('input', (e) => {
        if (!activeEl) return;
        activeEl.dataset.color = e.target.value;
        document.getElementById('prop-text-color-hex').value = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-text-color-hex').addEventListener('input', (e) => {
        if (!activeEl) return;
        activeEl.dataset.color = e.target.value;
        if (/^#[0-9a-fA-F]{6}$/.test(e.target.value)) {
            document.getElementById('prop-text-color').value = e.target.value;
        }
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-bg-color').addEventListener('input', (e) => {
        if (!activeEl) return;
        activeEl.dataset.bgColor = e.target.value;
        document.getElementById('prop-bg-color-text').value = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-bg-color-text').addEventListener('input', (e) => {
        if (!activeEl) return;
        activeEl.dataset.bgColor = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-bg-color-clear').addEventListener('click', () => {
        if (!activeEl) return;
        activeEl.dataset.bgColor = '';
        activeEl.style.background = '';
        document.getElementById('prop-bg-color-text').value = '';
        document.getElementById('prop-bg-color').value = '#000000';
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-opacity').addEventListener('input', (e) => {
        if (!activeEl) return;
        activeEl.dataset.opacity = e.target.value;
        document.getElementById('prop-opacity-val').textContent = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-bg-opacity').addEventListener('input', (e) => {
        if (!activeEl) return;
        activeEl.dataset.bgOpacity = e.target.value;
        document.getElementById('prop-bg-opacity-val').textContent = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-border-radius').addEventListener('input', (e) => {
        if (!activeEl) return;
        const v = e.target.value;
        activeEl.dataset.borderRadius = v;
        // "All" sets uniform — clear per-corner overrides
        activeEl.dataset.brTl = '';
        activeEl.dataset.brTr = '';
        activeEl.dataset.brBl = '';
        activeEl.dataset.brBr = '';
        document.getElementById('prop-br-tl').value = '';
        document.getElementById('prop-br-tr').value = '';
        document.getElementById('prop-br-bl').value = '';
        document.getElementById('prop-br-br').value = '';
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    // Per-corner border radius
    ['prop-br-tl', 'prop-br-tr', 'prop-br-bl', 'prop-br-br'].forEach(id => {
        document.getElementById(id).addEventListener('input', (e) => {
            if (!activeEl) return;
            const map = { 'prop-br-tl': 'brTl', 'prop-br-tr': 'brTr', 'prop-br-bl': 'brBl', 'prop-br-br': 'brBr' };
            activeEl.dataset[map[id]] = e.target.value;
            applyVisualStyling(activeEl);
            syncToHidden(activeEl);
        });
    });

    document.getElementById('prop-padding').addEventListener('input', (e) => {
        if (!activeEl) return;
        activeEl.dataset.padding = e.target.value;
        // Clear per-side overrides
        activeEl.dataset.padTop = '';
        activeEl.dataset.padRight = '';
        activeEl.dataset.padBottom = '';
        activeEl.dataset.padLeft = '';
        document.getElementById('prop-pad-top').value = '';
        document.getElementById('prop-pad-right').value = '';
        document.getElementById('prop-pad-bottom').value = '';
        document.getElementById('prop-pad-left').value = '';
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    // Per-side padding
    ['prop-pad-top', 'prop-pad-right', 'prop-pad-bottom', 'prop-pad-left'].forEach(id => {
        document.getElementById(id).addEventListener('input', (e) => {
            if (!activeEl) return;
            const map = { 'prop-pad-top': 'padTop', 'prop-pad-right': 'padRight', 'prop-pad-bottom': 'padBottom', 'prop-pad-left': 'padLeft' };
            activeEl.dataset[map[id]] = e.target.value;
            applyVisualStyling(activeEl);
            syncToHidden(activeEl);
        });
    });

    document.getElementById('prop-zindex').addEventListener('input', (e) => {
        if (!activeEl) return;
        activeEl.dataset.zIndex = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-box-shadow').addEventListener('change', (e) => {
        if (!activeEl) return;
        activeEl.dataset.boxShadow = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-text-shadow').addEventListener('change', (e) => {
        if (!activeEl) return;
        activeEl.dataset.textShadow = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-font-weight').addEventListener('change', (e) => {
        if (!activeEl) return;
        activeEl.dataset.fontWeight = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    // Custom text content
    document.getElementById('prop-content').addEventListener('input', (e) => {
        if (!activeEl) return;
        activeEl.dataset.content = e.target.value;
        // Update visible text (skip the drag-label)
        const label = activeEl.querySelector('.drag-label');
        activeEl.textContent = e.target.value;
        activeEl.prepend(label);
        syncToHidden(activeEl);
    });

    // Custom shape
    document.getElementById('prop-shape-type').addEventListener('change', (e) => {
        if (!activeEl) return;
        activeEl.dataset.shapeType = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    // Table styling controls
    ['prop-header-bg', 'prop-header-color', 'prop-row-bg', 'prop-cell-color', 'prop-cell-padding', 'prop-table-border-color', 'prop-table-border-width'].forEach(id => {
        document.getElementById(id).addEventListener('input', (e) => {
            if (!activeEl) return;
            const map = {
                'prop-header-bg': 'headerBg', 'prop-header-color': 'headerColor',
                'prop-row-bg': 'rowBg', 'prop-cell-color': 'cellColor',
                'prop-cell-padding': 'cellPadding', 'prop-table-border-color': 'tableBorderColor',
                'prop-table-border-width': 'tableBorderWidth'
            };
            activeEl.dataset[map[id]] = e.target.value;
            refreshTablePreview(activeEl);
            syncToHidden(activeEl);
        });
    });

    // New styling controls
    document.getElementById('prop-text-align').addEventListener('change', (e) => {
        if (!activeEl) return;
        activeEl.dataset.textAlign = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-text-transform').addEventListener('change', (e) => {
        if (!activeEl) return;
        activeEl.dataset.textTransform = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-letter-spacing').addEventListener('input', (e) => {
        if (!activeEl) return;
        activeEl.dataset.letterSpacing = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-line-height').addEventListener('input', (e) => {
        if (!activeEl) return;
        activeEl.dataset.lineHeight = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-margin').addEventListener('input', (e) => {
        if (!activeEl) return;
        activeEl.dataset.margin = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-el-width').addEventListener('input', (e) => {
        if (!activeEl) return;
        activeEl.dataset.elWidth = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-el-height').addEventListener('input', (e) => {
        if (!activeEl) return;
        activeEl.dataset.elHeight = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-border-style').addEventListener('change', (e) => {
        if (!activeEl) return;
        activeEl.dataset.borderStyle = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-el-border-color').addEventListener('input', (e) => {
        if (!activeEl) return;
        activeEl.dataset.borderColorVal = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-el-border-width').addEventListener('input', (e) => {
        if (!activeEl) return;
        activeEl.dataset.borderWidth = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    document.getElementById('prop-rotation').addEventListener('input', (e) => {
        if (!activeEl) return;
        activeEl.dataset.rotation = e.target.value;
        document.getElementById('prop-rotation-val').textContent = e.target.value;
        applyVisualStyling(activeEl);
        syncToHidden(activeEl);
    });

    // ── Position props panel → element sync ──
    ['prop-top', 'prop-left', 'prop-width', 'prop-height', 'prop-fontsize', 'prop-bottom'].forEach(id => {
        document.getElementById(id).addEventListener('input', (e) => {
            if (!activeEl) return;
            const key = activeEl.dataset.key;
            const meta = getElMeta(activeEl);
            const val = parseInt(e.target.value) || 0;

            if (id === 'prop-top') {
                activeEl.style.top = val + 'px';
                activeEl.style.bottom = 'auto';
                if (meta.hasBottom) {
                    document.getElementById('prop-bottom').value = canvasH - val - activeEl.offsetHeight;
                }
            }
            if (id === 'prop-left') activeEl.style.left = val + 'px';
            if (id === 'prop-width') activeEl.style.width = val + 'px';
            if (id === 'prop-height') activeEl.style.height = val + 'px';
            if (id === 'prop-fontsize') activeEl.style.fontSize = val + 'px';
            if (id === 'prop-bottom') {
                const newTop = canvasH - val - activeEl.offsetHeight;
                activeEl.style.top = newTop + 'px';
                activeEl.style.bottom = 'auto';
                document.getElementById('prop-top').value = newTop;
            }

            syncToHidden(activeEl);
        });
    });

    // ── Sync element to hidden form inputs ──
    function syncToHidden(el) {
        const key = el.dataset.key;
        const meta = getElMeta(el);
        const top = el.offsetTop;
        const left = el.offsetLeft;
        const isCustom = key.startsWith('custom_');

        const prefix = 'hid_' + key + '_';

        function setHid(prop, val) {
            const input = document.getElementById(prefix + prop);
            if (input) input.value = val;
        }

        setHid('top', top);
        setHid('left', left);

        if (meta.hasBottom) {
            const bottom = canvasH - top - el.offsetHeight;
            setHid('bottom', bottom);
        } else if (!isCustom) {
            setHid('bottom', '');
        }

        if (meta.hasWidth) {
            setHid('width', el.offsetWidth);
        } else {
            // For text elements, sync elWidth/elHeight data attrs
            setHid('width', el.dataset.elWidth || '');
        }
        if (meta.hasHeight) {
            setHid('height', el.offsetHeight);
        } else {
            setHid('height', el.dataset.elHeight || '');
        }
        if (meta.hasFontSize) {
            setHid('fontSize', parseInt(getComputedStyle(el).fontSize) || '');
        }

        // Styling sync
        setHid('color', el.dataset.color || '#ffffff');
        setHid('bgColor', el.dataset.bgColor || '');
        setHid('opacity', el.dataset.opacity || 1);
        setHid('bgOpacity', el.dataset.bgOpacity ?? 1);
        setHid('borderRadius', el.dataset.borderRadius || 0);
        setHid('borderRadiusTL', el.dataset.brTl || '');
        setHid('borderRadiusTR', el.dataset.brTr || '');
        setHid('borderRadiusBL', el.dataset.brBl || '');
        setHid('borderRadiusBR', el.dataset.brBr || '');
        setHid('boxShadow', el.dataset.boxShadow || 'none');
        setHid('textShadow', el.dataset.textShadow || 'none');
        setHid('zIndex', el.dataset.zIndex || 10);
        setHid('visible', el.dataset.visible !== '0' ? '1' : '0');
        setHid('fontWeight', el.dataset.fontWeight || 'bold');
        setHid('padding', el.dataset.padding || 0);
        setHid('paddingTop', el.dataset.padTop || '');
        setHid('paddingRight', el.dataset.padRight || '');
        setHid('paddingBottom', el.dataset.padBottom || '');
        setHid('paddingLeft', el.dataset.padLeft || '');

        // New styling sync
        setHid('margin', el.dataset.margin || 0);
        setHid('letterSpacing', el.dataset.letterSpacing || 0);
        setHid('lineHeight', el.dataset.lineHeight || '');
        setHid('textAlign', el.dataset.textAlign || 'left');
        setHid('textTransform', el.dataset.textTransform || 'none');
        setHid('rotation', el.dataset.rotation || 0);
        setHid('borderStyle', el.dataset.borderStyle || 'none');
        setHid('borderColor', el.dataset.borderColorVal || '');
        setHid('borderWidth', el.dataset.borderWidth || 0);

        // Custom element fields
        if (meta.isCustomText) {
            setHid('content', el.dataset.content || '');
        }
        if (meta.isCustomShape) {
            setHid('shapeType', el.dataset.shapeType || 'rectangle');
            setHid('width', el.offsetWidth);
            setHid('height', el.offsetHeight);
        }
        if (meta.isCustomImage) {
            setHid('imagePath', el.dataset.imagePath || '');
            setHid('width', el.offsetWidth);
            setHid('height', el.offsetHeight);
        }
        if (meta.isTable) {
            setHid('headerBg', el.dataset.headerBg || '');
            setHid('headerColor', el.dataset.headerColor || '');
            setHid('rowBg', el.dataset.rowBg || '');
            setHid('cellColor', el.dataset.cellColor || '');
            setHid('cellPadding', el.dataset.cellPadding || '');
            setHid('tableBorderColor', el.dataset.tableBorderColor || '');
            setHid('tableBorderWidth', el.dataset.tableBorderWidth || '');
            setHid('tableColumns', el.dataset.tableColumns || '');
        }
    }

    // Rebuild the table preview inside the drag element from columns JSON
    function refreshTablePreview(el) {
        let tbl = el.querySelector('table');
        const hBg = el.dataset.headerBg || 'rgba(0,0,0,0.7)';
        const hC = el.dataset.headerColor || '#fff';
        const rBg = el.dataset.rowBg || 'rgba(255,255,255,0.1)';
        const cC = el.dataset.cellColor || '#fff';
        const cP = el.dataset.cellPadding || '10';
        const tBC = el.dataset.tableBorderColor || 'rgba(255,255,255,0.2)';
        const tBW = el.dataset.tableBorderWidth || '1';
        const bdr = `${tBW}px solid ${tBC}`;
        const fs = el.style.fontSize || '20px';

        let cols;
        try { cols = JSON.parse(el.dataset.tableColumns || '[]'); } catch(e) { cols = []; }
        if (!cols.length) cols = [{label:'Matches',field:'total_matches'},{label:'Runs',field:'total_runs'},{label:'Wickets',field:'total_wickets'}];

        // Rebuild entire table HTML
        let html = `<table style="width:100%;height:100%;border-collapse:collapse;font-size:${fs};pointer-events:none;">`;
        html += `<thead><tr style="background:${hBg};color:${hC};">`;
        cols.forEach(c => {
            let thStyle = `padding:${cP}px;border:${bdr};`;
            if (c.headerBg) thStyle += `background:${c.headerBg};`;
            if (c.headerColor) thStyle += `color:${c.headerColor};`;
            if (c.width) thStyle += `width:${c.width};`;
            html += `<th style="${thStyle}">${c.label || ''}</th>`;
        });
        html += `</tr></thead><tbody><tr style="background:${rBg};color:${cC};">`;
        cols.forEach(c => {
            let tdStyle = `padding:${cP}px;border:${bdr};text-align:center;`;
            if (c.cellBg) tdStyle += `background:${c.cellBg};`;
            if (c.cellColor) tdStyle += `color:${c.cellColor};`;
            html += `<td style="${tdStyle}">0</td>`;
        });
        html += `</tr></tbody></table>`;

        // Keep the drag-label
        const label = el.querySelector('.drag-label');
        if (tbl) tbl.remove();
        el.insertAdjacentHTML('beforeend', html);
        if (label) el.prepend(label);
    }

    // Available player fields for column mapping
    const playerFields = [
        {value: 'total_matches', label: 'Total Matches'},
        {value: 'total_runs', label: 'Total Runs'},
        {value: 'total_wickets', label: 'Total Wickets'},
        {value: 'base_price', label: 'Base Price'},
        {value: 'batting_avg', label: 'Batting Avg'},
        {value: 'bowling_avg', label: 'Bowling Avg'},
        {value: 'strike_rate', label: 'Strike Rate'},
        {value: 'economy', label: 'Economy'},
        {value: 'fifties', label: 'Fifties'},
        {value: 'hundreds', label: 'Hundreds'},
        {value: 'catches', label: 'Catches'},
        {value: 'stumpings', label: 'Stumpings'},
    ];

    // Get current columns from activeEl
    function getTableColumns() {
        if (!activeEl) return [];
        try { return JSON.parse(activeEl.dataset.tableColumns || '[]'); } catch(e) { return []; }
    }

    // Save columns to activeEl and sync
    function saveTableColumns(cols) {
        if (!activeEl) return;
        activeEl.dataset.tableColumns = JSON.stringify(cols);
        refreshTablePreview(activeEl);
        syncToHidden(activeEl);
    }

    // Load columns editor UI
    function loadColumnsEditor(el) {
        const editor = document.getElementById('table-columns-editor');
        let cols;
        try { cols = JSON.parse(el.dataset.tableColumns || '[]'); } catch(e) { cols = []; }
        if (!cols.length) cols = [{label:'Matches',field:'total_matches',cellBg:'',cellColor:'',headerBg:'',headerColor:'',width:''}];

        editor.innerHTML = '';
        cols.forEach((col, i) => {
            editor.appendChild(buildColumnRow(col, i, cols.length));
        });
    }

    // Build a single column editor row
    function buildColumnRow(col, index, total) {
        const row = document.createElement('div');
        row.className = 'p-2 bg-gray-700/50 rounded border border-gray-600 space-y-1';
        row.dataset.colIndex = index;

        const fieldOpts = playerFields.map(f =>
            `<option value="${f.value}" ${col.field === f.value ? 'selected' : ''}>${f.label}</option>`
        ).join('');

        row.innerHTML = `
            <div class="flex items-center justify-between">
                <span class="text-[10px] text-purple-300 font-bold">Col ${index + 1}</span>
                <button type="button" onclick="removeTableColumn(${index})" class="text-red-400 hover:text-red-300 text-xs" title="Remove">&times;</button>
            </div>
            <div>
                <label class="text-[10px] text-gray-400">Label</label>
                <input type="text" value="${col.label || ''}" class="form-control form-control-sm col-label" data-ci="${index}" placeholder="Column header">
            </div>
            <div>
                <label class="text-[10px] text-gray-400">Field</label>
                <select class="form-control form-control-sm col-field" data-ci="${index}">
                    <option value="">-- None --</option>
                    ${fieldOpts}
                </select>
            </div>
            <div class="grid grid-cols-2 gap-1">
                <div>
                    <label class="text-[10px] text-gray-400">Header Bg</label>
                    <input type="text" value="${col.headerBg || ''}" class="form-control form-control-sm col-header-bg" data-ci="${index}" placeholder="inherit">
                </div>
                <div>
                    <label class="text-[10px] text-gray-400">Header Color</label>
                    <input type="text" value="${col.headerColor || ''}" class="form-control form-control-sm col-header-color" data-ci="${index}" placeholder="inherit">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-1">
                <div>
                    <label class="text-[10px] text-gray-400">Cell Bg</label>
                    <input type="text" value="${col.cellBg || ''}" class="form-control form-control-sm col-cell-bg" data-ci="${index}" placeholder="inherit">
                </div>
                <div>
                    <label class="text-[10px] text-gray-400">Cell Color</label>
                    <input type="text" value="${col.cellColor || ''}" class="form-control form-control-sm col-cell-color" data-ci="${index}" placeholder="inherit">
                </div>
            </div>
            <div>
                <label class="text-[10px] text-gray-400">Width</label>
                <input type="text" value="${col.width || ''}" class="form-control form-control-sm col-width" data-ci="${index}" placeholder="auto (e.g. 100px, 30%)">
            </div>
        `;

        // Bind change events
        setTimeout(() => {
            row.querySelectorAll('input, select').forEach(inp => {
                inp.addEventListener('input', () => syncColumnsFromEditor());
            });
        }, 0);

        return row;
    }

    // Read all column data from editor and save
    function syncColumnsFromEditor() {
        const editor = document.getElementById('table-columns-editor');
        const rows = editor.querySelectorAll('[data-col-index]');
        const cols = [];
        rows.forEach(row => {
            cols.push({
                label: row.querySelector('.col-label')?.value || '',
                field: row.querySelector('.col-field')?.value || '',
                cellBg: row.querySelector('.col-cell-bg')?.value || '',
                cellColor: row.querySelector('.col-cell-color')?.value || '',
                headerBg: row.querySelector('.col-header-bg')?.value || '',
                headerColor: row.querySelector('.col-header-color')?.value || '',
                width: row.querySelector('.col-width')?.value || '',
            });
        });
        saveTableColumns(cols);
    }

    // Add a new column
    window.addTableColumn = function() {
        const cols = getTableColumns();
        cols.push({label: 'New', field: '', cellBg: '', cellColor: '', headerBg: '', headerColor: '', width: ''});
        saveTableColumns(cols);
        loadColumnsEditor(activeEl);
    };

    // Remove a column by index
    window.removeTableColumn = function(index) {
        const cols = getTableColumns();
        cols.splice(index, 1);
        saveTableColumns(cols);
        loadColumnsEditor(activeEl);
    };

    // ── Create hidden inputs for custom element ──
    function createCustomHiddenInputs(key, type) {
        const container = document.getElementById('custom-hidden-inputs');
        const fields = ['top', 'left', 'width', 'height', 'fontSize', 'color', 'bgColor', 'opacity', 'bgOpacity', 'borderRadius', 'borderRadiusTL', 'borderRadiusTR', 'borderRadiusBL', 'borderRadiusBR', 'boxShadow', 'textShadow', 'zIndex', 'visible', 'fontWeight', 'padding', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'margin', 'letterSpacing', 'lineHeight', 'textAlign', 'textTransform', 'rotation', 'borderStyle', 'borderColor', 'borderWidth'];

        if (type === 'custom_text') fields.push('content');
        if (type === 'custom_shape') fields.push('shapeType');
        if (type === 'custom_image') fields.push('imagePath');

        fields.forEach(f => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `pos_${key}_${f}`;
            input.id = `hid_${key}_${f}`;
            container.appendChild(input);
        });
    }

    // ── Custom Text Element ──
    window.addCustomText = function() {
        const idx = customTextCount++;
        const key = 'custom_text_' + idx;

        const el = document.createElement('div');
        el.className = 'drag-el custom-el';
        el.dataset.key = key;
        el.dataset.type = 'custom_text';
        el.dataset.color = '#ffffff';
        el.dataset.bgColor = '';
        el.dataset.opacity = '1';
        el.dataset.bgOpacity = '1';
        el.dataset.borderRadius = '0';
        el.dataset.brTl = '';
        el.dataset.brTr = '';
        el.dataset.brBl = '';
        el.dataset.brBr = '';
        el.dataset.boxShadow = 'none';
        el.dataset.textShadow = 'none';
        el.dataset.zIndex = '10';
        el.dataset.visible = '1';
        el.dataset.fontWeight = 'bold';
        el.dataset.padding = '0';
        el.dataset.padTop = '';
        el.dataset.padRight = '';
        el.dataset.padBottom = '';
        el.dataset.padLeft = '';
        el.dataset.content = 'Text Label';
        el.dataset.margin = '0';
        el.dataset.letterSpacing = '0';
        el.dataset.lineHeight = '';
        el.dataset.textAlign = 'left';
        el.dataset.textTransform = 'none';
        el.dataset.rotation = '0';
        el.dataset.borderStyle = 'none';
        el.dataset.borderColorVal = '';
        el.dataset.borderWidth = '0';
        el.dataset.elWidth = '';
        el.dataset.elHeight = '';
        el.style.cssText = 'position:absolute;cursor:move;border:2px dashed rgba(168,85,247,0.7);background:rgba(168,85,247,0.1);top:100px;left:100px;font-size:24px;color:#fff;font-weight:bold;white-space:nowrap;padding:2px 8px;';
        el.innerHTML = `<div class="drag-label">${key} <span class="custom-delete" onclick="deleteCustomElement(this.closest('.drag-el'))" style="cursor:pointer;color:#ef4444;margin-left:4px;">x</span></div>Text Label`;

        canvas.appendChild(el);
        createCustomHiddenInputs(key, 'custom_text');
        syncToHidden(el);
        updateCustomList();
        selectElement(el);
    };

    // ── Custom Shape Element ──
    window.addCustomShape = function() {
        const idx = customShapeCount++;
        const key = 'custom_shape_' + idx;

        const el = document.createElement('div');
        el.className = 'drag-el custom-el';
        el.dataset.key = key;
        el.dataset.type = 'custom_shape';
        el.dataset.color = '#ffffff';
        el.dataset.bgColor = 'rgba(255,255,255,0.1)';
        el.dataset.opacity = '1';
        el.dataset.bgOpacity = '1';
        el.dataset.borderRadius = '0';
        el.dataset.brTl = '';
        el.dataset.brTr = '';
        el.dataset.brBl = '';
        el.dataset.brBr = '';
        el.dataset.boxShadow = 'none';
        el.dataset.textShadow = 'none';
        el.dataset.zIndex = '10';
        el.dataset.visible = '1';
        el.dataset.fontWeight = 'bold';
        el.dataset.padding = '0';
        el.dataset.padTop = '';
        el.dataset.padRight = '';
        el.dataset.padBottom = '';
        el.dataset.padLeft = '';
        el.dataset.shapeType = 'rectangle';
        el.dataset.borderColorVal = '';
        el.dataset.borderWidth = '0';
        el.dataset.margin = '0';
        el.dataset.letterSpacing = '0';
        el.dataset.lineHeight = '';
        el.dataset.textAlign = 'left';
        el.dataset.textTransform = 'none';
        el.dataset.rotation = '0';
        el.dataset.borderStyle = 'none';
        el.style.cssText = 'position:absolute;cursor:move;border:2px dashed rgba(99,102,241,0.7);background:rgba(255,255,255,0.1);top:100px;left:100px;width:120px;height:60px;';
        el.innerHTML = `<div class="drag-label">${key} <span class="custom-delete" onclick="deleteCustomElement(this.closest('.drag-el'))" style="cursor:pointer;color:#ef4444;margin-left:4px;">x</span></div>`;

        canvas.appendChild(el);
        addResizeHandle(el);
        createCustomHiddenInputs(key, 'custom_shape');
        syncToHidden(el);
        updateCustomList();
        selectElement(el);
    };

    // ── Trigger custom image file picker ──
    window.triggerAddImage = function() {
        document.getElementById('custom-image-picker').click();
    };

    // ── Custom Image Element ──
    window.addCustomImage = function(file) {
        if (!file) return;
        const idx = customImageCount++;
        const key = 'custom_image_' + idx;

        const el = document.createElement('div');
        el.className = 'drag-el custom-el';
        el.dataset.key = key;
        el.dataset.type = 'custom_image';
        el.dataset.color = '#ffffff';
        el.dataset.bgColor = '';
        el.dataset.opacity = '1';
        el.dataset.bgOpacity = '1';
        el.dataset.borderRadius = '0';
        el.dataset.brTl = '';
        el.dataset.brTr = '';
        el.dataset.brBl = '';
        el.dataset.brBr = '';
        el.dataset.boxShadow = 'none';
        el.dataset.textShadow = 'none';
        el.dataset.zIndex = '10';
        el.dataset.visible = '1';
        el.dataset.fontWeight = 'bold';
        el.dataset.padding = '0';
        el.dataset.padTop = '';
        el.dataset.padRight = '';
        el.dataset.padBottom = '';
        el.dataset.padLeft = '';
        el.dataset.imagePath = '';
        el.dataset.margin = '0';
        el.dataset.letterSpacing = '0';
        el.dataset.lineHeight = '';
        el.dataset.textAlign = 'left';
        el.dataset.textTransform = 'none';
        el.dataset.rotation = '0';
        el.dataset.borderStyle = 'none';
        el.dataset.borderColorVal = '';
        el.dataset.borderWidth = '0';
        el.dataset.elWidth = '';
        el.dataset.elHeight = '';
        el.style.cssText = 'position:absolute;cursor:move;border:2px dashed rgba(20,184,166,0.7);background:rgba(20,184,166,0.1);top:100px;left:100px;width:150px;height:100px;display:flex;align-items:center;justify-content:center;overflow:hidden;';

        const previewUrl = URL.createObjectURL(file);
        el.innerHTML = `<div class="drag-label">${key} <span class="custom-delete" onclick="deleteCustomElement(this.closest('.drag-el'))" style="cursor:pointer;color:#ef4444;margin-left:4px;">x</span></div><img src="${previewUrl}" style="width:100%;height:100%;object-fit:contain;pointer-events:none;">`;

        canvas.appendChild(el);
        addResizeHandle(el);
        createCustomHiddenInputs(key, 'custom_image');

        // Attach the file to a hidden file input so it submits with the form
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.name = `pos_${key}_file`;
        fileInput.style.display = 'none';
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;
        document.getElementById('custom-hidden-inputs').appendChild(fileInput);

        syncToHidden(el);
        updateCustomList();
        selectElement(el);
    };

    // ── Delete custom element ──
    window.deleteCustomElement = function(el) {
        const key = el.dataset.key;
        el.remove();
        // Remove hidden inputs
        document.querySelectorAll(`#custom-hidden-inputs input[name^="pos_${key}_"]`).forEach(i => i.remove());
        if (activeEl === el) deselectAll();
        updateCustomList();
    };

    // ── Update custom elements list in sidebar ──
    function updateCustomList() {
        const list = document.getElementById('custom-elements-list');
        const customs = document.querySelectorAll('.drag-el.custom-el');
        if (customs.length === 0) {
            list.innerHTML = '<p class="text-gray-400">No custom elements yet.</p>';
            return;
        }
        list.innerHTML = '';
        customs.forEach(el => {
            const div = document.createElement('div');
            div.className = 'flex items-center justify-between p-1 bg-gray-100 dark:bg-gray-700 rounded';
            div.innerHTML = `<span class="text-gray-700 dark:text-gray-300 cursor-pointer" onclick="document.querySelector('.drag-el[data-key=&quot;${el.dataset.key}&quot;]')?.click()">${el.dataset.key}</span>
                <button type="button" onclick="deleteCustomElement(document.querySelector('.drag-el[data-key=&quot;${el.dataset.key}&quot;]'))" class="text-red-500 hover:text-red-700 text-xs px-1">x</button>`;
            list.appendChild(div);
        });
    }

    // Initial sync from rendered positions to hidden inputs
    document.querySelectorAll('.drag-el').forEach(el => {
        const cs = getComputedStyle(el);
        if (cs.bottom !== 'auto' && cs.top === 'auto') {
            const top = el.offsetTop;
            el.style.top = top + 'px';
            el.style.bottom = 'auto';
        }

        // Create hidden inputs for existing custom elements
        if (el.classList.contains('custom-el')) {
            createCustomHiddenInputs(el.dataset.key, el.dataset.type);
            if (el.dataset.type === 'custom_text') {
                const num = parseInt(el.dataset.key.replace('custom_text_', ''));
                if (num >= customTextCount) customTextCount = num + 1;
            }
            if (el.dataset.type === 'custom_shape') {
                const num = parseInt(el.dataset.key.replace('custom_shape_', ''));
                if (num >= customShapeCount) customShapeCount = num + 1;
            }
            if (el.dataset.type === 'custom_image') {
                const num = parseInt(el.dataset.key.replace('custom_image_', ''));
                if (num >= customImageCount) customImageCount = num + 1;
            }
        }

        syncToHidden(el);
    });

    updateCustomList();

    // ── Gradient Presets ──
    const gradientPresets = [
        { label: 'None', value: '' },
        { label: 'Black', value: '#000000' },
        { label: 'White 10%', value: 'rgba(255,255,255,0.1)' },
        { label: 'White 30%', value: 'rgba(255,255,255,0.3)' },
        { label: 'Black 50%', value: 'rgba(0,0,0,0.5)' },
        { label: 'Blue', value: 'linear-gradient(135deg, #1e3a5f, #2196f3)' },
        { label: 'Gold', value: 'linear-gradient(135deg, #b8860b, #ffd700)' },
        { label: 'Red', value: 'linear-gradient(135deg, #8b0000, #ff4444)' },
        { label: 'Green', value: 'linear-gradient(135deg, #1b5e20, #4caf50)' },
        { label: 'Purple', value: 'radial-gradient(circle, #9c27b0, #4a148c)' },
    ];
    const presetsContainer = document.getElementById('gradient-presets');
    gradientPresets.forEach(p => {
        const swatch = document.createElement('div');
        swatch.title = p.label;
        swatch.style.cssText = `width:24px;height:24px;border-radius:4px;cursor:pointer;border:1px solid #666;flex-shrink:0;`;
        swatch.style.background = p.value || 'repeating-conic-gradient(#808080 0% 25%, transparent 0% 50%) 50%/8px 8px';
        swatch.addEventListener('click', () => {
            if (!activeEl) return;
            activeEl.dataset.bgColor = p.value;
            document.getElementById('prop-bg-color-text').value = p.value;
            applyVisualStyling(activeEl);
            syncToHidden(activeEl);
        });
        presetsContainer.appendChild(swatch);
    });

    // ── Reset all positions ──
    window.resetAllPositions = function() {
        if (!confirm('Reset all element positions to defaults?')) return;
        document.querySelectorAll('.drag-el:not(.custom-el)').forEach(el => {
            const key = el.dataset.key;
            const def = defaults[key];
            if (!def) return;
            if (def.top !== undefined) {
                el.style.top = def.top + 'px';
                el.style.bottom = 'auto';
            } else if (def.bottom !== undefined) {
                const top = canvasH - def.bottom - el.offsetHeight;
                el.style.top = top + 'px';
                el.style.bottom = 'auto';
            }
            if (def.left !== undefined) el.style.left = def.left + 'px';
            if (def.width !== undefined) el.style.width = def.width + 'px';
            if (def.height !== undefined) el.style.height = def.height + 'px';
            if (def.fontSize !== undefined) el.style.fontSize = def.fontSize + 'px';

            // Reset styling
            el.dataset.color = def.color || '#ffffff';
            el.dataset.bgColor = def.bgColor || '';
            el.dataset.opacity = def.opacity ?? 1;
            el.dataset.bgOpacity = def.bgOpacity ?? 1;
            el.dataset.borderRadius = def.borderRadius ?? 0;
            el.dataset.brTl = '';
            el.dataset.brTr = '';
            el.dataset.brBl = '';
            el.dataset.brBr = '';
            el.dataset.boxShadow = def.boxShadow || 'none';
            el.dataset.textShadow = def.textShadow || 'none';
            el.dataset.zIndex = def.zIndex ?? 10;
            el.dataset.visible = '1';
            el.dataset.fontWeight = def.fontWeight || 'bold';
            el.dataset.padding = def.padding ?? 0;
            el.dataset.padTop = '';
            el.dataset.padRight = '';
            el.dataset.padBottom = '';
            el.dataset.padLeft = '';
            el.dataset.margin = def.margin ?? 0;
            el.dataset.letterSpacing = def.letterSpacing ?? 0;
            el.dataset.lineHeight = def.lineHeight || '';
            el.dataset.textAlign = def.textAlign || 'left';
            el.dataset.textTransform = def.textTransform || 'none';
            el.dataset.rotation = def.rotation ?? 0;
            el.dataset.borderStyle = def.borderStyle || 'none';
            el.dataset.borderColorVal = def.borderColor || '';
            el.dataset.borderWidth = def.borderWidth ?? 0;
            el.dataset.elWidth = '';
            el.dataset.elHeight = '';
            applyVisualStyling(el);

            syncToHidden(el);
        });
        deselectAll();
    };
});
</script>
