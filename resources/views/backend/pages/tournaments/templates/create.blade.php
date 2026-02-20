@extends('backend.layouts.app')

@section('title', 'Create Template | ' . $tournament->name)

@push('styles')
<style>
    .canvas-element {
        position: absolute;
        cursor: move;
        user-select: none;
    }
    .canvas-element.selected {
        outline: 2px solid #6366f1;
        outline-offset: 2px;
    }
    .canvas-element .resize-handle {
        position: absolute;
        width: 10px;
        height: 10px;
        background: #6366f1;
        border: 2px solid white;
        border-radius: 2px;
        display: none;
    }
    .canvas-element.selected .resize-handle {
        display: block;
    }
    .resize-handle.nw { top: -5px; left: -5px; cursor: nw-resize; }
    .resize-handle.ne { top: -5px; right: -5px; cursor: ne-resize; }
    .resize-handle.sw { bottom: -5px; left: -5px; cursor: sw-resize; }
    .resize-handle.se { bottom: -5px; right: -5px; cursor: se-resize; }
    .resize-handle.n { top: -5px; left: 50%; transform: translateX(-50%); cursor: n-resize; }
    .resize-handle.s { bottom: -5px; left: 50%; transform: translateX(-50%); cursor: s-resize; }
    .resize-handle.e { right: -5px; top: 50%; transform: translateY(-50%); cursor: e-resize; }
    .resize-handle.w { left: -5px; top: 50%; transform: translateY(-50%); cursor: w-resize; }
    .placeholder-item:active { cursor: grabbing; }
    #canvasContainer { touch-action: none; }
    .element-content {
        white-space: nowrap;
        display: inline-block;
    }
    .color-btn { width: 24px; height: 24px; border-radius: 4px; cursor: pointer; border: 2px solid transparent; }
    .color-btn:hover, .color-btn.active { border-color: #6366f1; }
</style>
@endpush

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Tournaments', 'route' => route('admin.tournaments.index')],
    ['name' => $tournament->name, 'route' => route('admin.tournaments.show', $tournament)],
    ['name' => 'Templates', 'route' => route('admin.tournaments.templates.index', $tournament)],
    ['name' => 'Create']
]" />

<form action="{{ route('admin.tournaments.templates.store', $tournament) }}" method="POST" enctype="multipart/form-data" id="templateForm">
    @csrf
    <input type="hidden" name="layout_json" id="layoutJsonInput" value="[]">
    <input type="hidden" name="canvas_width" id="canvasWidthInput" value="1080">
    <input type="hidden" name="canvas_height" id="canvasHeightInput" value="1080">

    <div class="grid grid-cols-1 xl:grid-cols-4 gap-4">
        {{-- Left Panel: Settings & Placeholders --}}
        <div class="xl:col-span-1 space-y-4">
            {{-- Basic Info Card --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-3 text-sm">Template Settings</h3>

                <div class="space-y-3">
                    <div>
                        <label for="name" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Template Name *</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                               placeholder="e.g., Welcome Card Blue">
                    </div>

                    <div>
                        <label for="type" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Template Type *</label>
                        <select name="type" id="type" required onchange="updatePlaceholders()"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            @foreach(\App\Models\TournamentTemplate::TYPES as $templateType)
                                <option value="{{ $templateType }}" {{ $type === $templateType ? 'selected' : '' }}>
                                    {{ \App\Models\TournamentTemplate::getTypeDisplay($templateType) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Background Image</label>
                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-3 text-center hover:border-indigo-400 transition cursor-pointer"
                             onclick="document.getElementById('background_image').click()">
                            <input type="file" name="background_image" id="background_image" accept="image/*" class="hidden" onchange="loadBackgroundImage(this)">
                            <svg class="w-6 h-6 mx-auto text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p class="text-xs text-gray-500">Click to upload</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="is_default" id="is_default" value="1"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="is_default" class="text-xs text-gray-700 dark:text-gray-300">Set as default</label>
                    </div>
                </div>
            </div>

            {{-- Placeholders Panel --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Placeholders</h3>
                    <span class="text-xs text-gray-500">Drag to canvas</span>
                </div>

                <div id="placeholdersList" class="space-y-1.5 max-h-[350px] overflow-y-auto pr-1">
                    @php
                        $placeholderIcons = [
                            'player_image' => 'image',
                            'team_logo' => 'image',
                            'tournament_logo' => 'image',
                            'team_a_logo' => 'image',
                            'team_b_logo' => 'image',
                            'team_a_captain_image' => 'image',
                            'team_b_captain_image' => 'image',
                            'team_a_sponsor_logo' => 'image',
                            'team_b_sponsor_logo' => 'image',
                            'man_of_the_match_image' => 'image',
                            'qr_code' => 'image',
                        ];
                    @endphp
                    @foreach($placeholders as $placeholder)
                        @php $isImage = in_array($placeholder, array_keys($placeholderIcons)); @endphp
                        <div class="placeholder-item flex items-center gap-2 p-2 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-grab hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition text-xs"
                             draggable="true"
                             data-placeholder="{{ $placeholder }}"
                             data-type="{{ $isImage ? 'image' : 'text' }}">
                            <div class="w-6 h-6 rounded bg-{{ $isImage ? 'purple' : 'indigo' }}-100 dark:bg-{{ $isImage ? 'purple' : 'indigo' }}-900 flex items-center justify-center flex-shrink-0">
                                @if($isImage)
                                    <svg class="w-3 h-3 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                @else
                                    <svg class="w-3 h-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                                    </svg>
                                @endif
                            </div>
                            <span class="flex-1 truncate text-gray-700 dark:text-gray-300">{{ str_replace('_', ' ', ucfirst($placeholder)) }}</span>
                            <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                            </svg>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Center Panel: Canvas --}}
        <div class="xl:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Canvas</h3>
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="clearCanvas()" class="text-xs text-red-600 hover:text-red-800">Clear All</button>
                    </div>
                </div>

                {{-- Canvas Container --}}
                <div id="canvasWrapper" class="bg-gray-900 rounded-lg p-4 overflow-auto" style="max-height: 70vh;">
                    <div id="canvasContainer" class="relative bg-gray-800 mx-auto"
                         style="width: 540px; height: 540px;"
                         ondragover="handleDragOver(event)"
                         ondrop="handleDrop(event)">
                        {{-- Background Image --}}
                        <img id="canvasBackground" src="" alt="Background" class="absolute inset-0 w-full h-full object-cover hidden">

                        {{-- Grid overlay for positioning --}}
                        <div id="canvasGrid" class="absolute inset-0 pointer-events-none hidden"
                             style="background-image: linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 10% 10%;">
                        </div>

                        {{-- Placeholder for empty canvas --}}
                        <div id="canvasPlaceholder" class="absolute inset-0 flex flex-col items-center justify-center text-gray-500">
                            <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p class="text-sm font-medium">Upload background image</p>
                            <p class="text-xs mt-1">Then drag placeholders to position</p>
                        </div>

                        {{-- Dropped elements container --}}
                        <div id="droppedElements" class="absolute inset-0 overflow-hidden"></div>
                    </div>
                </div>

                {{-- Canvas Controls --}}
                <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400">
                            <input type="checkbox" id="showGrid" onchange="toggleGrid()" class="rounded border-gray-300 text-indigo-600 w-3 h-3">
                            Grid
                        </label>
                        <span class="text-gray-300">|</span>
                        <select id="canvasSize" onchange="changeCanvasSize()" class="text-xs border-gray-300 rounded py-1">
                            <option value="540x540">Square (1:1)</option>
                            <option value="540x675">Portrait (4:5)</option>
                            <option value="540x960">Story (9:16)</option>
                            <option value="675x540">Landscape (5:4)</option>
                        </select>
                    </div>
                    <div class="text-xs text-gray-500">
                        <span id="elementCount">0</span> elements
                    </div>
                </div>
            </div>

            {{-- Form Actions --}}
            <div class="flex justify-between gap-3 mt-4">
                <button type="button" onclick="showClientPreview()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    Preview Layout
                </button>
                <div class="flex gap-3">
                    <a href="{{ route('admin.tournaments.templates.index', $tournament) }}"
                       class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">
                        Create Template
                    </button>
                </div>
            </div>
        </div>

        {{-- Client-side Preview Modal --}}
        <div id="previewModal" class="fixed inset-0 z-50 hidden">
            <div class="absolute inset-0 bg-black/70" onclick="closePreviewModal()"></div>
            <div class="absolute inset-4 md:inset-10 bg-white dark:bg-gray-800 rounded-xl overflow-hidden flex flex-col">
                <div class="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Layout Preview</h3>
                    <div class="flex items-center gap-3">
                        <p class="text-sm text-gray-500">Save template to generate downloadable images</p>
                        <button onclick="closePreviewModal()" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-auto p-4 bg-gray-900 flex items-center justify-center">
                    <canvas id="previewCanvas" class="max-w-full max-h-full shadow-2xl rounded-lg"></canvas>
                </div>
            </div>
        </div>

        {{-- Right Panel: Element Editor --}}
        <div class="xl:col-span-1">
            <div id="elementEditorPanel" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sticky top-4">
                <div id="noSelectionMsg" class="text-center py-8 text-gray-500">
                    <svg class="w-10 h-10 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                    </svg>
                    <p class="text-sm">Click an element to edit</p>
                    <p class="text-xs mt-1">or drag placeholders to canvas</p>
                </div>

                <div id="elementEditor" class="hidden">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Edit Element</h3>
                        <button type="button" onclick="deleteSelectedElement()" class="text-red-500 hover:text-red-700" title="Delete">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        {{-- Element Name --}}
                        <div class="bg-indigo-50 dark:bg-indigo-900/30 rounded-lg p-2">
                            <span id="editingElementName" class="text-sm font-medium text-indigo-700 dark:text-indigo-300"></span>
                        </div>

                        {{-- Text Properties (hidden for images) --}}
                        <div id="textProperties">
                            {{-- Font Family --}}
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Font Family</label>
                                <select id="elementFontFamily" onchange="updateSelectedElement()"
                                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                                    <option value="Arial, sans-serif">Arial</option>
                                    <option value="'Times New Roman', serif">Times New Roman</option>
                                    <option value="Georgia, serif">Georgia</option>
                                    <option value="'Courier New', monospace">Courier New</option>
                                    <option value="Verdana, sans-serif">Verdana</option>
                                    <option value="Impact, sans-serif">Impact</option>
                                    <option value="'Comic Sans MS', cursive">Comic Sans</option>
                                    <option value="'Trebuchet MS', sans-serif">Trebuchet MS</option>
                                </select>
                            </div>

                            {{-- Font Size & Weight --}}
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Size</label>
                                    <input type="number" id="elementFontSize" min="8" max="120" value="24"
                                           class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                           onchange="updateSelectedElement()">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Weight</label>
                                    <select id="elementFontWeight" onchange="updateSelectedElement()"
                                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                                        <option value="300">Light</option>
                                        <option value="400">Normal</option>
                                        <option value="600">Semi Bold</option>
                                        <option value="700" selected>Bold</option>
                                        <option value="900">Black</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Text Color --}}
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" id="elementColor" value="#ffffff"
                                           class="w-10 h-8 rounded cursor-pointer border-0"
                                           onchange="updateSelectedElement()">
                                    <div class="flex gap-1">
                                        <button type="button" onclick="setColor('#ffffff')" class="color-btn bg-white border border-gray-300"></button>
                                        <button type="button" onclick="setColor('#000000')" class="color-btn bg-black"></button>
                                        <button type="button" onclick="setColor('#FFD700')" class="color-btn bg-yellow-400"></button>
                                        <button type="button" onclick="setColor('#EF4444')" class="color-btn bg-red-500"></button>
                                        <button type="button" onclick="setColor('#3B82F6')" class="color-btn bg-blue-500"></button>
                                        <button type="button" onclick="setColor('#10B981')" class="color-btn bg-green-500"></button>
                                    </div>
                                </div>
                            </div>

                            {{-- Text Alignment --}}
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Alignment</label>
                                <div class="flex gap-1">
                                    <button type="button" onclick="setAlignment('left')" id="alignLeft"
                                            class="flex-1 py-1.5 text-xs border rounded-l-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h14" />
                                        </svg>
                                    </button>
                                    <button type="button" onclick="setAlignment('center')" id="alignCenter"
                                            class="flex-1 py-1.5 text-xs border-y hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10M5 18h14" />
                                        </svg>
                                    </button>
                                    <button type="button" onclick="setAlignment('right')" id="alignRight"
                                            class="flex-1 py-1.5 text-xs border rounded-r-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M10 12h10M6 18h14" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            {{-- Text Transform --}}
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Transform</label>
                                <select id="elementTextTransform" onchange="updateSelectedElement()"
                                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                                    <option value="none">Normal</option>
                                    <option value="uppercase">UPPERCASE</option>
                                    <option value="lowercase">lowercase</option>
                                    <option value="capitalize">Capitalize</option>
                                </select>
                            </div>

                            {{-- Text Shadow --}}
                            <div class="mb-3">
                                <label class="flex items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    <input type="checkbox" id="elementShadow" onchange="updateSelectedElement()"
                                           class="rounded border-gray-300 text-indigo-600 w-3 h-3" checked>
                                    Text Shadow
                                </label>
                                <div id="shadowOptions" class="grid grid-cols-3 gap-2 mt-2">
                                    <div>
                                        <label class="block text-xs text-gray-500">Blur</label>
                                        <input type="number" id="shadowBlur" value="4" min="0" max="20"
                                               class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                               onchange="updateSelectedElement()">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500">X</label>
                                        <input type="number" id="shadowX" value="2" min="-10" max="10"
                                               class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                               onchange="updateSelectedElement()">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500">Y</label>
                                        <input type="number" id="shadowY" value="2" min="-10" max="10"
                                               class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                               onchange="updateSelectedElement()">
                                    </div>
                                </div>
                            </div>

                            {{-- Letter Spacing --}}
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Letter Spacing</label>
                                <input type="range" id="elementLetterSpacing" min="-2" max="10" value="0" step="0.5"
                                       class="w-full" onchange="updateSelectedElement()">
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>-2</span>
                                    <span id="letterSpacingValue">0</span>
                                    <span>10</span>
                                </div>
                            </div>
                        </div>

                        {{-- Image Properties (hidden for text) --}}
                        <div id="imageProperties" class="hidden">
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Width (px)</label>
                                <input type="number" id="elementWidth" min="20" max="500" value="100"
                                       class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                       onchange="updateSelectedElement()">
                            </div>
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Height (px)</label>
                                <input type="number" id="elementHeight" min="20" max="500" value="100"
                                       class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                       onchange="updateSelectedElement()">
                            </div>
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Border Radius</label>
                                <input type="range" id="elementBorderRadius" min="0" max="50" value="0"
                                       class="w-full" onchange="updateSelectedElement()">
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>0%</span>
                                    <span id="borderRadiusValue">0%</span>
                                    <span>50%</span>
                                </div>
                            </div>
                        </div>

                        {{-- Common: Rotation --}}
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Rotation</label>
                            <input type="range" id="elementRotation" min="-180" max="180" value="0"
                                   class="w-full" onchange="updateSelectedElement()">
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>-180°</span>
                                <span id="rotationValue">0°</span>
                                <span>180°</span>
                            </div>
                        </div>

                        {{-- Common: Opacity --}}
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Opacity</label>
                            <input type="range" id="elementOpacity" min="10" max="100" value="100"
                                   class="w-full" onchange="updateSelectedElement()">
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>10%</span>
                                <span id="opacityValue">100%</span>
                                <span>100%</span>
                            </div>
                        </div>

                        {{-- Layer Controls --}}
                        <div class="flex gap-2 pt-3 border-t border-gray-200 dark:border-gray-700">
                            <button type="button" onclick="bringToFront()" class="flex-1 py-1.5 text-xs bg-gray-100 dark:bg-gray-700 rounded hover:bg-gray-200 dark:hover:bg-gray-600">
                                Bring Front
                            </button>
                            <button type="button" onclick="sendToBack()" class="flex-1 py-1.5 text-xs bg-gray-100 dark:bg-gray-700 rounded hover:bg-gray-200 dark:hover:bg-gray-600">
                                Send Back
                            </button>
                            <button type="button" onclick="duplicateElement()" class="flex-1 py-1.5 text-xs bg-gray-100 dark:bg-gray-700 rounded hover:bg-gray-200 dark:hover:bg-gray-600">
                                Duplicate
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
let elements = [];
let selectedElement = null;
let isDragging = false;
let isResizing = false;
let resizeHandle = null;
let zIndexCounter = 1;
const canvas = { width: 540, height: 540 };

// Load background image
function loadBackgroundImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('canvasBackground');
            img.src = e.target.result;
            img.classList.remove('hidden');
            document.getElementById('canvasPlaceholder').classList.add('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Drag and Drop from placeholders panel
document.querySelectorAll('.placeholder-item').forEach(item => {
    item.addEventListener('dragstart', function(e) {
        e.dataTransfer.setData('placeholder', this.dataset.placeholder);
        e.dataTransfer.setData('type', this.dataset.type);
        this.classList.add('opacity-50');
    });
    item.addEventListener('dragend', function() {
        this.classList.remove('opacity-50');
    });
});

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'copy';
}

function handleDrop(e) {
    e.preventDefault();
    const placeholder = e.dataTransfer.getData('placeholder');
    const type = e.dataTransfer.getData('type');

    if (!placeholder) return;

    const container = document.getElementById('canvasContainer');
    const rect = container.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    addElement(placeholder, type, x, y);
}

function addElement(placeholder, type, x, y) {
    const id = 'element_' + Date.now();
    zIndexCounter++;

    const element = {
        id: id,
        placeholder: placeholder,
        type: type,
        x: Math.max(20, Math.min(x, canvas.width - 20)),
        y: Math.max(20, Math.min(y, canvas.height - 20)),
        fontSize: 24,
        fontFamily: 'Arial, sans-serif',
        fontWeight: '700',
        color: '#ffffff',
        textAlign: 'center',
        textTransform: 'none',
        letterSpacing: 0,
        shadow: true,
        shadowBlur: 4,
        shadowX: 2,
        shadowY: 2,
        rotation: 0,
        opacity: 100,
        width: type === 'image' ? 100 : 0,
        height: type === 'image' ? 100 : 0,
        borderRadius: 0,
        zIndex: zIndexCounter
    };

    elements.push(element);
    renderElement(element);
    selectElement(id);
    updateLayoutJson();
    updateElementCount();
}

function renderElement(element) {
    const container = document.getElementById('droppedElements');
    const div = document.createElement('div');
    div.id = element.id;
    div.className = 'canvas-element';
    div.style.left = element.x + 'px';
    div.style.top = element.y + 'px';
    div.style.zIndex = element.zIndex;
    div.style.transform = `translate(-50%, -50%) rotate(${element.rotation}deg)`;
    div.style.opacity = element.opacity / 100;

    if (element.type === 'image') {
        div.innerHTML = `
            <div class="element-content bg-white/20 border-2 border-dashed border-white/60 rounded flex items-center justify-center"
                 style="width: ${element.width}px; height: ${element.height}px; border-radius: ${element.borderRadius}%;">
                <div class="text-center">
                    <svg class="w-8 h-8 mx-auto text-white/70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-xs text-white/70 mt-1">${element.placeholder.replace(/_/g, ' ')}</p>
                </div>
            </div>
            <div class="resize-handle nw"></div>
            <div class="resize-handle ne"></div>
            <div class="resize-handle sw"></div>
            <div class="resize-handle se"></div>
        `;
    } else {
        const shadowStyle = element.shadow ? `${element.shadowX}px ${element.shadowY}px ${element.shadowBlur}px rgba(0,0,0,0.8)` : 'none';
        div.innerHTML = `
            <span class="element-content" style="
                font-family: ${element.fontFamily};
                font-size: ${element.fontSize}px;
                font-weight: ${element.fontWeight};
                color: ${element.color};
                text-align: ${element.textAlign};
                text-transform: ${element.textTransform};
                letter-spacing: ${element.letterSpacing}px;
                text-shadow: ${shadowStyle};
            ">&#123;&#123;${element.placeholder}&#125;&#125;</span>
            <div class="resize-handle nw"></div>
            <div class="resize-handle ne"></div>
            <div class="resize-handle sw"></div>
            <div class="resize-handle se"></div>
        `;
    }

    // Event listeners
    div.addEventListener('mousedown', handleElementMouseDown);
    div.addEventListener('click', (e) => {
        e.stopPropagation();
        selectElement(element.id);
    });

    // Resize handles
    div.querySelectorAll('.resize-handle').forEach(handle => {
        handle.addEventListener('mousedown', (e) => {
            e.stopPropagation();
            startResize(e, element.id, handle.className.split(' ')[1]);
        });
    });

    container.appendChild(div);
}

function handleElementMouseDown(e) {
    if (e.target.classList.contains('resize-handle')) return;

    const elementDiv = e.currentTarget;
    const element = elements.find(el => el.id === elementDiv.id);
    if (!element) return;

    isDragging = true;
    selectElement(element.id);

    const container = document.getElementById('canvasContainer');
    const startX = e.clientX;
    const startY = e.clientY;
    const originalX = element.x;
    const originalY = element.y;

    function onMouseMove(e) {
        if (!isDragging) return;

        const deltaX = e.clientX - startX;
        const deltaY = e.clientY - startY;

        element.x = Math.max(10, Math.min(originalX + deltaX, canvas.width - 10));
        element.y = Math.max(10, Math.min(originalY + deltaY, canvas.height - 10));

        elementDiv.style.left = element.x + 'px';
        elementDiv.style.top = element.y + 'px';
    }

    function onMouseUp() {
        isDragging = false;
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
        updateLayoutJson();
    }

    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
}

function startResize(e, elementId, handleType) {
    isResizing = true;
    resizeHandle = handleType;

    const element = elements.find(el => el.id === elementId);
    const elementDiv = document.getElementById(elementId);
    if (!element || !elementDiv) return;

    const startX = e.clientX;
    const startY = e.clientY;
    const originalWidth = element.width || 100;
    const originalHeight = element.height || 100;
    const originalFontSize = element.fontSize;

    function onMouseMove(e) {
        if (!isResizing) return;

        const deltaX = e.clientX - startX;
        const deltaY = e.clientY - startY;

        if (element.type === 'image') {
            if (handleType.includes('e')) element.width = Math.max(40, originalWidth + deltaX);
            if (handleType.includes('w')) element.width = Math.max(40, originalWidth - deltaX);
            if (handleType.includes('s')) element.height = Math.max(40, originalHeight + deltaY);
            if (handleType.includes('n')) element.height = Math.max(40, originalHeight - deltaY);

            const content = elementDiv.querySelector('.element-content');
            if (content) {
                content.style.width = element.width + 'px';
                content.style.height = element.height + 'px';
            }
            document.getElementById('elementWidth').value = Math.round(element.width);
            document.getElementById('elementHeight').value = Math.round(element.height);
        } else {
            // For text, resize by changing font size
            const delta = (deltaX + deltaY) / 4;
            element.fontSize = Math.max(8, Math.min(120, originalFontSize + delta));
            const content = elementDiv.querySelector('.element-content');
            if (content) {
                content.style.fontSize = element.fontSize + 'px';
            }
            document.getElementById('elementFontSize').value = Math.round(element.fontSize);
        }
    }

    function onMouseUp() {
        isResizing = false;
        resizeHandle = null;
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
        updateLayoutJson();
    }

    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
}

function selectElement(elementId) {
    // Remove selection from all
    document.querySelectorAll('.canvas-element').forEach(el => {
        el.classList.remove('selected');
    });

    const element = elements.find(el => el.id === elementId);
    if (!element) return;

    selectedElement = element;

    // Add selection styling
    const div = document.getElementById(elementId);
    if (div) div.classList.add('selected');

    // Show editor
    document.getElementById('noSelectionMsg').classList.add('hidden');
    document.getElementById('elementEditor').classList.remove('hidden');
    document.getElementById('editingElementName').textContent = element.placeholder.replace(/_/g, ' ');

    // Toggle text/image properties
    if (element.type === 'image') {
        document.getElementById('textProperties').classList.add('hidden');
        document.getElementById('imageProperties').classList.remove('hidden');
        document.getElementById('elementWidth').value = element.width;
        document.getElementById('elementHeight').value = element.height;
        document.getElementById('elementBorderRadius').value = element.borderRadius;
        document.getElementById('borderRadiusValue').textContent = element.borderRadius + '%';
    } else {
        document.getElementById('textProperties').classList.remove('hidden');
        document.getElementById('imageProperties').classList.add('hidden');
        document.getElementById('elementFontFamily').value = element.fontFamily;
        document.getElementById('elementFontSize').value = element.fontSize;
        document.getElementById('elementFontWeight').value = element.fontWeight;
        document.getElementById('elementColor').value = element.color;
        document.getElementById('elementTextTransform').value = element.textTransform;
        document.getElementById('elementLetterSpacing').value = element.letterSpacing;
        document.getElementById('letterSpacingValue').textContent = element.letterSpacing;
        document.getElementById('elementShadow').checked = element.shadow;
        document.getElementById('shadowBlur').value = element.shadowBlur;
        document.getElementById('shadowX').value = element.shadowX;
        document.getElementById('shadowY').value = element.shadowY;
        updateAlignmentButtons(element.textAlign);
    }

    document.getElementById('elementRotation').value = element.rotation;
    document.getElementById('rotationValue').textContent = element.rotation + '°';
    document.getElementById('elementOpacity').value = element.opacity;
    document.getElementById('opacityValue').textContent = element.opacity + '%';
}

function updateSelectedElement() {
    if (!selectedElement) return;

    const div = document.getElementById(selectedElement.id);
    if (!div) return;

    if (selectedElement.type === 'image') {
        selectedElement.width = parseInt(document.getElementById('elementWidth').value) || 100;
        selectedElement.height = parseInt(document.getElementById('elementHeight').value) || 100;
        selectedElement.borderRadius = parseInt(document.getElementById('elementBorderRadius').value) || 0;
        document.getElementById('borderRadiusValue').textContent = selectedElement.borderRadius + '%';

        const content = div.querySelector('.element-content');
        if (content) {
            content.style.width = selectedElement.width + 'px';
            content.style.height = selectedElement.height + 'px';
            content.style.borderRadius = selectedElement.borderRadius + '%';
        }
    } else {
        selectedElement.fontFamily = document.getElementById('elementFontFamily').value;
        selectedElement.fontSize = parseInt(document.getElementById('elementFontSize').value) || 24;
        selectedElement.fontWeight = document.getElementById('elementFontWeight').value;
        selectedElement.color = document.getElementById('elementColor').value;
        selectedElement.textTransform = document.getElementById('elementTextTransform').value;
        selectedElement.letterSpacing = parseFloat(document.getElementById('elementLetterSpacing').value) || 0;
        selectedElement.shadow = document.getElementById('elementShadow').checked;
        selectedElement.shadowBlur = parseInt(document.getElementById('shadowBlur').value) || 4;
        selectedElement.shadowX = parseInt(document.getElementById('shadowX').value) || 2;
        selectedElement.shadowY = parseInt(document.getElementById('shadowY').value) || 2;

        document.getElementById('letterSpacingValue').textContent = selectedElement.letterSpacing;

        const shadowStyle = selectedElement.shadow
            ? `${selectedElement.shadowX}px ${selectedElement.shadowY}px ${selectedElement.shadowBlur}px rgba(0,0,0,0.8)`
            : 'none';

        const content = div.querySelector('.element-content');
        if (content) {
            content.style.fontFamily = selectedElement.fontFamily;
            content.style.fontSize = selectedElement.fontSize + 'px';
            content.style.fontWeight = selectedElement.fontWeight;
            content.style.color = selectedElement.color;
            content.style.textTransform = selectedElement.textTransform;
            content.style.letterSpacing = selectedElement.letterSpacing + 'px';
            content.style.textShadow = shadowStyle;
        }
    }

    selectedElement.rotation = parseInt(document.getElementById('elementRotation').value) || 0;
    selectedElement.opacity = parseInt(document.getElementById('elementOpacity').value) || 100;

    document.getElementById('rotationValue').textContent = selectedElement.rotation + '°';
    document.getElementById('opacityValue').textContent = selectedElement.opacity + '%';

    div.style.transform = `translate(-50%, -50%) rotate(${selectedElement.rotation}deg)`;
    div.style.opacity = selectedElement.opacity / 100;

    updateLayoutJson();
}

function setColor(color) {
    document.getElementById('elementColor').value = color;
    updateSelectedElement();
}

function setAlignment(align) {
    if (!selectedElement || selectedElement.type === 'image') return;
    selectedElement.textAlign = align;
    const content = document.getElementById(selectedElement.id)?.querySelector('.element-content');
    if (content) content.style.textAlign = align;
    updateAlignmentButtons(align);
    updateLayoutJson();
}

function updateAlignmentButtons(align) {
    ['left', 'center', 'right'].forEach(a => {
        const btn = document.getElementById('align' + a.charAt(0).toUpperCase() + a.slice(1));
        if (btn) {
            btn.classList.toggle('bg-indigo-100', a === align);
            btn.classList.toggle('dark:bg-indigo-900', a === align);
        }
    });
}

function deleteSelectedElement() {
    if (!selectedElement) return;
    document.getElementById(selectedElement.id)?.remove();
    elements = elements.filter(el => el.id !== selectedElement.id);
    selectedElement = null;
    document.getElementById('noSelectionMsg').classList.remove('hidden');
    document.getElementById('elementEditor').classList.add('hidden');
    updateLayoutJson();
    updateElementCount();
}

function bringToFront() {
    if (!selectedElement) return;
    zIndexCounter++;
    selectedElement.zIndex = zIndexCounter;
    document.getElementById(selectedElement.id).style.zIndex = zIndexCounter;
    updateLayoutJson();
}

function sendToBack() {
    if (!selectedElement) return;
    selectedElement.zIndex = 1;
    document.getElementById(selectedElement.id).style.zIndex = 1;
    // Adjust others
    elements.forEach(el => {
        if (el.id !== selectedElement.id && el.zIndex === 1) {
            el.zIndex = 2;
            document.getElementById(el.id).style.zIndex = 2;
        }
    });
    updateLayoutJson();
}

function duplicateElement() {
    if (!selectedElement) return;
    const newElement = { ...selectedElement };
    newElement.id = 'element_' + Date.now();
    newElement.x = Math.min(selectedElement.x + 20, canvas.width - 20);
    newElement.y = Math.min(selectedElement.y + 20, canvas.height - 20);
    zIndexCounter++;
    newElement.zIndex = zIndexCounter;
    elements.push(newElement);
    renderElement(newElement);
    selectElement(newElement.id);
    updateLayoutJson();
    updateElementCount();
}

function clearCanvas() {
    if (!confirm('Clear all elements?')) return;
    document.getElementById('droppedElements').innerHTML = '';
    elements = [];
    selectedElement = null;
    document.getElementById('noSelectionMsg').classList.remove('hidden');
    document.getElementById('elementEditor').classList.add('hidden');
    updateLayoutJson();
    updateElementCount();
}

function toggleGrid() {
    document.getElementById('canvasGrid').classList.toggle('hidden', !document.getElementById('showGrid').checked);
}

function changeCanvasSize() {
    const size = document.getElementById('canvasSize').value.split('x');
    const oldWidth = canvas.width;
    const oldHeight = canvas.height;
    canvas.width = parseInt(size[0]);
    canvas.height = parseInt(size[1]);

    const container = document.getElementById('canvasContainer');
    container.style.width = canvas.width + 'px';
    container.style.height = canvas.height + 'px';

    // Update hidden inputs (store at 2x for HD render)
    document.getElementById('canvasWidthInput').value = canvas.width * 2;
    document.getElementById('canvasHeightInput').value = canvas.height * 2;

    // Reposition elements proportionally when canvas size changes
    elements.forEach(el => {
        const xPercent = el.x / oldWidth;
        const yPercent = el.y / oldHeight;
        el.x = xPercent * canvas.width;
        el.y = yPercent * canvas.height;

        const div = document.getElementById(el.id);
        if (div) {
            div.style.left = el.x + 'px';
            div.style.top = el.y + 'px';
        }
    });

    updateLayoutJson();
}

function updateLayoutJson() {
    const layoutData = elements.map(el => ({
        placeholder: el.placeholder,
        type: el.type,
        x: Math.round((el.x / canvas.width) * 100 * 10) / 10,
        y: Math.round((el.y / canvas.height) * 100 * 10) / 10,
        fontSize: el.fontSize,
        fontFamily: el.fontFamily,
        fontWeight: el.fontWeight,
        color: el.color,
        textAlign: el.textAlign,
        textTransform: el.textTransform,
        letterSpacing: el.letterSpacing,
        shadow: el.shadow,
        shadowBlur: el.shadowBlur,
        shadowX: el.shadowX,
        shadowY: el.shadowY,
        rotation: el.rotation,
        opacity: el.opacity,
        width: el.width,
        height: el.height,
        borderRadius: el.borderRadius,
        zIndex: el.zIndex
    }));
    document.getElementById('layoutJsonInput').value = JSON.stringify(layoutData);
}

function updateElementCount() {
    document.getElementById('elementCount').textContent = elements.length;
}

// Click outside to deselect
document.getElementById('canvasContainer').addEventListener('click', function(e) {
    if (e.target === this || e.target.id === 'canvasBackground' || e.target.id === 'droppedElements' || e.target.id === 'canvasGrid') {
        selectedElement = null;
        document.querySelectorAll('.canvas-element').forEach(el => el.classList.remove('selected'));
        document.getElementById('noSelectionMsg').classList.remove('hidden');
        document.getElementById('elementEditor').classList.add('hidden');
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Handle Escape key for modal
    if (e.key === 'Escape') {
        closePreviewModal();
        return;
    }

    if (!selectedElement) return;
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') return;

    if (e.key === 'Delete' || e.key === 'Backspace') {
        e.preventDefault();
        deleteSelectedElement();
    }
    if (e.key === 'd' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault();
        duplicateElement();
    }
    // Arrow keys for fine positioning
    const step = e.shiftKey ? 10 : 1;
    if (e.key === 'ArrowLeft') { selectedElement.x = Math.max(10, selectedElement.x - step); }
    if (e.key === 'ArrowRight') { selectedElement.x = Math.min(canvas.width - 10, selectedElement.x + step); }
    if (e.key === 'ArrowUp') { selectedElement.y = Math.max(10, selectedElement.y - step); }
    if (e.key === 'ArrowDown') { selectedElement.y = Math.min(canvas.height - 10, selectedElement.y + step); }

    if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
        e.preventDefault();
        const div = document.getElementById(selectedElement.id);
        div.style.left = selectedElement.x + 'px';
        div.style.top = selectedElement.y + 'px';
        updateLayoutJson();
    }
});

// Form submission
document.getElementById('templateForm').addEventListener('submit', function(e) {
    updateLayoutJson();
});

function updatePlaceholders() {
    if (elements.length > 0) {
        if (confirm('Changing type will clear elements. Continue?')) {
            clearCanvas();
        }
    }
}

// Client-side Preview Functions
function showClientPreview() {
    const modal = document.getElementById('previewModal');
    const previewCanvas = document.getElementById('previewCanvas');
    const ctx = previewCanvas.getContext('2d');

    // Use saved canvas dimensions (2x editor size for HD)
    previewCanvas.width = canvas.width * 2;
    previewCanvas.height = canvas.height * 2;
    const scale = 2; // Always 2x scale from editor to output

    // Draw background
    const bgImg = document.getElementById('canvasBackground');
    if (bgImg.src && !bgImg.classList.contains('hidden')) {
        ctx.drawImage(bgImg, 0, 0, previewCanvas.width, previewCanvas.height);
    } else {
        ctx.fillStyle = '#1a1a2e';
        ctx.fillRect(0, 0, previewCanvas.width, previewCanvas.height);
    }

    // Sort elements by z-index
    const sortedElements = [...elements].sort((a, b) => (a.zIndex || 1) - (b.zIndex || 1));

    // Draw each element
    sortedElements.forEach(element => {
        const x = element.x * scale;
        const y = element.y * scale;

        ctx.save();
        ctx.translate(x, y);
        ctx.rotate((element.rotation || 0) * Math.PI / 180);
        ctx.globalAlpha = (element.opacity || 100) / 100;

        if (element.type === 'image') {
            // Draw placeholder box for images
            const w = (element.width || 100) * scale;
            const h = (element.height || 100) * scale;
            ctx.fillStyle = 'rgba(255, 255, 255, 0.2)';
            ctx.fillRect(-w/2, -h/2, w, h);
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.5)';
            ctx.setLineDash([5, 5]);
            ctx.strokeRect(-w/2, -h/2, w, h);
            ctx.setLineDash([]);
            ctx.fillStyle = '#ffffff';
            ctx.font = '14px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('[' + element.placeholder.replace(/_/g, ' ') + ']', 0, 5);
        } else {
            // Draw text
            const fontSize = (element.fontSize || 24) * scale;
            const fontWeight = element.fontWeight || '700';
            ctx.font = fontWeight + ' ' + fontSize + 'px Arial, sans-serif';
            ctx.textAlign = element.textAlign || 'center';
            ctx.textBaseline = 'middle';

            // Get sample text
            const sampleText = getSampleText(element.placeholder);
            let displayText = sampleText;
            if (element.textTransform === 'uppercase') displayText = sampleText.toUpperCase();
            if (element.textTransform === 'lowercase') displayText = sampleText.toLowerCase();
            if (element.textTransform === 'capitalize') displayText = sampleText.replace(/\b\w/g, l => l.toUpperCase());

            // Draw shadow
            if (element.shadow) {
                ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
                ctx.fillText(displayText, (element.shadowX || 2) * scale, (element.shadowY || 2) * scale);
            }

            // Draw text
            ctx.fillStyle = element.color || '#ffffff';
            ctx.fillText(displayText, 0, 0);
        }

        ctx.restore();
    });

    modal.classList.remove('hidden');
}

function getSampleText(placeholder) {
    const samples = {
        'player_name': 'John Doe',
        'jersey_name': 'J. DOE',
        'jersey_number': '10',
        'team_name': 'Sample Team FC',
        'tournament_name': '{{ $tournament->name }}',
        'team_a_name': 'Team Alpha',
        'team_b_name': 'Team Beta',
        'team_a_score': '150/6',
        'team_b_score': '145/8',
        'match_date': new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
        'match_time': '3:00 PM',
        'venue': 'City Sports Ground',
        'match_stage': 'Group Stage',
        'result_summary': 'Team Alpha won by 5 runs',
        'winner_name': 'Team Alpha',
        'man_of_the_match_name': 'John Doe',
        'player_type': 'All Rounder',
        'batting_style': 'Right Handed',
        'bowling_style': 'Right Arm Medium',
    };
    return samples[placeholder] || placeholder.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function closePreviewModal() {
    document.getElementById('previewModal').classList.add('hidden');
}
</script>
@endpush
@endsection
