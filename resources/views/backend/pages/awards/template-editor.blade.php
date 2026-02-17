@extends('backend.layouts.app')

@section('title', 'Edit Award Template | ' . $award->name)

@push('styles')
<style>
    .preview-canvas {
        position: relative;
        width: 360px;
        height: 450px;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        user-select: none;
    }
    .preview-element {
        position: absolute;
        cursor: move;
        transition: outline 0.15s, transform 0.1s, box-shadow 0.15s;
        padding: 4px 8px;
        border-radius: 4px;
    }
    .preview-element:hover {
        outline: 2px dashed rgba(255,255,255,0.6);
        background: rgba(255,255,255,0.1);
    }
    .preview-element.selected {
        outline: 2px solid #fbbf24;
        background: rgba(251, 191, 36, 0.15);
        box-shadow: 0 0 10px rgba(251, 191, 36, 0.4);
    }
    .preview-element.dragging {
        transform: scale(1.02);
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    }
    .preview-element-label {
        position: absolute;
        top: -20px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0,0,0,0.7);
        color: white;
        font-size: 9px;
        padding: 2px 6px;
        border-radius: 3px;
        white-space: nowrap;
        opacity: 0;
        transition: opacity 0.2s;
        pointer-events: none;
    }
    .preview-element:hover .preview-element-label,
    .preview-element.selected .preview-element-label {
        opacity: 1;
    }
    .setting-group {
        border-left: 3px solid #3b82f6;
    }
    .color-picker {
        -webkit-appearance: none;
        width: 40px;
        height: 40px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }
    .color-picker::-webkit-color-swatch-wrapper {
        padding: 0;
    }
    .color-picker::-webkit-color-swatch {
        border: none;
        border-radius: 8px;
    }
    .range-slider {
        -webkit-appearance: none;
        width: 100%;
        height: 6px;
        border-radius: 3px;
        background: #e5e7eb;
    }
    .drag-hint {
        position: absolute;
        bottom: 8px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0,0,0,0.6);
        color: white;
        font-size: 10px;
        padding: 4px 10px;
        border-radius: 12px;
        pointer-events: none;
    }
    .range-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #3b82f6;
        cursor: pointer;
    }
</style>
@endpush

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Tournaments', 'route' => route('admin.tournaments.index')],
    ['name' => $tournament->name, 'route' => route('admin.tournaments.show', $tournament)],
    ['name' => 'Awards'],
    ['name' => $award->name . ' Template']
]" />

<form id="templateForm" action="{{ route('admin.awards.template.update', $award) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Preview Panel -->
        <div class="lg:col-span-1">
            <div class="card rounded-2xl overflow-hidden sticky top-4">
                <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
                    <h3 class="text-white font-bold text-lg flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                        </svg>
                        Live Preview
                    </h3>
                </div>

                <div class="p-4 flex flex-col items-center">
                    <p class="text-xs text-gray-500 mb-2">Drag elements to reposition</p>
                    <div class="preview-canvas" id="previewCanvas" style="background: linear-gradient({{ $settings['background']['gradient']['direction'] ?? '135deg' }}, {{ $settings['background']['gradient']['from'] ?? '#1a1a2e' }}, {{ $settings['background']['gradient']['to'] ?? '#16213e' }});">
                        <!-- Award Icon -->
                        @php
                            $iconType = $settings['award_icon']['type'] ?? 'emoji';
                            $iconX = ($settings['award_icon']['x'] ?? 540) / 3;
                            $iconY = ($settings['award_icon']['y'] ?? 100) / 3;
                            $iconWidth = ($settings['award_icon']['width'] ?? 80) / 3;
                            $iconHeight = ($settings['award_icon']['height'] ?? 80) / 3;
                        @endphp
                        <div class="preview-element" id="previewAwardIcon" data-element="award_icon" data-label="Icon"
                             style="{{ $iconType === 'image' ? 'left: ' . ($iconX - $iconWidth/2) . 'px; top: ' . $iconY . 'px;' : 'left: 0; right: 0; top: ' . $iconY . 'px; text-align: center;' }}">
                            <span class="preview-element-label">Icon</span>
                            @if($iconType === 'image' && isset($settings['award_icon']['image']))
                                <img src="{{ asset('storage/' . $settings['award_icon']['image']) }}"
                                     alt="Icon"
                                     id="previewIconImage"
                                     style="width: {{ $iconWidth }}px; height: {{ $iconHeight }}px; object-fit: contain; display: block;">
                            @else
                                <span id="previewIconEmoji" style="font-size: {{ ($settings['award_icon']['font_size'] ?? 64) / 3 }}px;">{{ $award->icon ?? '🏆' }}</span>
                            @endif
                        </div>

                        <!-- Award Name -->
                        <div class="preview-element text-center" id="previewAwardName" data-element="award_name" data-label="Award Name">
                            <span class="preview-element-label">Award Name</span>
                            <span style="font-size: {{ ($settings['award_name']['font_size'] ?? 42) / 3 }}px; color: {{ $settings['award_name']['font_color'] ?? '#fbbf24' }}; font-weight: {{ $settings['award_name']['font_weight'] ?? 'bold' }};">
                                {{ $award->name }}
                            </span>
                        </div>

                        <!-- Player Image -->
                        <div class="preview-element" id="previewPlayerImage" data-element="player_image" data-label="Player Image">
                            <span class="preview-element-label">Player Image (Drag X & Y)</span>
                            <div class="rounded-full bg-gray-300 flex items-center justify-center text-gray-600 text-2xl font-bold"
                                 style="width: {{ ($settings['player_image']['width'] ?? 300) / 3 }}px; height: {{ ($settings['player_image']['height'] ?? 300) / 3 }}px; border: {{ ($settings['player_image']['border_width'] ?? 6) / 3 }}px solid {{ $settings['player_image']['border_color'] ?? '#fbbf24' }};">
                                P
                            </div>
                        </div>

                        <!-- Player Name -->
                        <div class="preview-element text-center" id="previewPlayerName" data-element="player_name" data-label="Player Name">
                            <span class="preview-element-label">Player Name</span>
                            <span style="font-size: {{ ($settings['player_name']['font_size'] ?? 48) / 3 }}px; color: {{ $settings['player_name']['font_color'] ?? '#ffffff' }}; font-weight: {{ $settings['player_name']['font_weight'] ?? 'bold' }};">
                                Player Name
                            </span>
                        </div>

                        <!-- Team Name -->
                        <div class="preview-element text-center" id="previewTeamName" data-element="team_name" data-label="Team Name">
                            <span class="preview-element-label">Team Name</span>
                            <span style="font-size: {{ ($settings['team_name']['font_size'] ?? 28) / 3 }}px; color: {{ $settings['team_name']['font_color'] ?? '#9ca3af' }};">
                                Team Name
                            </span>
                        </div>

                        <!-- Match Info -->
                        <div class="preview-element text-center" id="previewMatchInfo" data-element="match_info" data-label="Match Info">
                            <span class="preview-element-label">Match Info</span>
                            <span style="font-size: {{ ($settings['match_info']['font_size'] ?? 24) / 3 }}px; color: {{ $settings['match_info']['font_color'] ?? '#6b7280' }};">
                                Match #1 vs Opponent
                            </span>
                        </div>

                        <!-- Drag hint -->
                        <div class="drag-hint">Drag to move</div>
                    </div>
                </div>

                <div class="p-4 border-t border-gray-200 dark:border-gray-700 space-y-3">
                    <button type="submit" class="w-full px-4 py-3 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white font-semibold rounded-xl transition flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Save Template
                    </button>

                    <a href="{{ route('admin.awards.template.reset', $award) }}"
                       onclick="event.preventDefault(); if(confirm('Reset template to defaults?')) document.getElementById('resetForm').submit();"
                       class="w-full px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 font-medium rounded-xl transition flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Reset to Defaults
                    </a>
                </div>
            </div>
        </div>

        <!-- Settings Panel -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Background Settings -->
            <div class="card rounded-2xl overflow-hidden">
                <div class="bg-gradient-to-r from-gray-700 to-gray-800 px-6 py-4">
                    <h3 class="text-white font-bold text-lg">Background</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Gradient From</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="template_settings[background][gradient][from]"
                                       value="{{ $settings['background']['gradient']['from'] ?? '#1a1a2e' }}"
                                       class="color-picker" onchange="updatePreview()">
                                <input type="text" name="template_settings[background][gradient][from]"
                                       value="{{ $settings['background']['gradient']['from'] ?? '#1a1a2e' }}"
                                       class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                                       onchange="updatePreview()">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Gradient To</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="template_settings[background][gradient][to]"
                                       value="{{ $settings['background']['gradient']['to'] ?? '#16213e' }}"
                                       class="color-picker" onchange="updatePreview()">
                                <input type="text" name="template_settings[background][gradient][to]"
                                       value="{{ $settings['background']['gradient']['to'] ?? '#16213e' }}"
                                       class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                                       onchange="updatePreview()">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Direction</label>
                            <select name="template_settings[background][gradient][direction]"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                    onchange="updatePreview()">
                                <option value="135deg" {{ ($settings['background']['gradient']['direction'] ?? '135deg') == '135deg' ? 'selected' : '' }}>Diagonal (↘)</option>
                                <option value="180deg" {{ ($settings['background']['gradient']['direction'] ?? '') == '180deg' ? 'selected' : '' }}>Top to Bottom (↓)</option>
                                <option value="90deg" {{ ($settings['background']['gradient']['direction'] ?? '') == '90deg' ? 'selected' : '' }}>Left to Right (→)</option>
                                <option value="45deg" {{ ($settings['background']['gradient']['direction'] ?? '') == '45deg' ? 'selected' : '' }}>Diagonal (↗)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Background Image Upload -->
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Background Image (Optional)</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="backgroundImage" accept="image/*" class="hidden" onchange="uploadBackground(this)">
                            <button type="button" onclick="document.getElementById('backgroundImage').click()"
                                    class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm">
                                Upload Image
                            </button>
                            @if(isset($settings['background']['image']))
                                <span class="text-sm text-gray-500">Image uploaded</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Player Image Settings -->
            <div class="card rounded-2xl overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                    <h3 class="text-white font-bold text-lg">Player Image</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">X Position</label>
                            <input type="number" name="template_settings[player_image][x]"
                                   value="{{ $settings['player_image']['x'] ?? 390 }}"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                   onchange="updatePreview()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Y Position</label>
                            <input type="number" name="template_settings[player_image][y]"
                                   value="{{ $settings['player_image']['y'] ?? 300 }}"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                   onchange="updatePreview()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Width</label>
                            <input type="number" name="template_settings[player_image][width]"
                                   value="{{ $settings['player_image']['width'] ?? 300 }}"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                   onchange="updatePreview()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Height</label>
                            <input type="number" name="template_settings[player_image][height]"
                                   value="{{ $settings['player_image']['height'] ?? 300 }}"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                   onchange="updatePreview()">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Border Width</label>
                            <input type="number" name="template_settings[player_image][border_width]"
                                   value="{{ $settings['player_image']['border_width'] ?? 6 }}"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                   onchange="updatePreview()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Border Color</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="template_settings[player_image][border_color]"
                                       value="{{ $settings['player_image']['border_color'] ?? '#fbbf24' }}"
                                       class="color-picker" onchange="updatePreview()">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Corner Radius</label>
                            <input type="number" name="template_settings[player_image][radius]"
                                   value="{{ $settings['player_image']['radius'] ?? 150 }}"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                   onchange="updatePreview()">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Text Elements Settings -->
            @foreach([
                ['key' => 'award_name', 'title' => 'Award Name', 'color' => 'from-yellow-500 to-orange-500'],
                ['key' => 'player_name', 'title' => 'Player Name', 'color' => 'from-green-500 to-emerald-500'],
                ['key' => 'team_name', 'title' => 'Team Name', 'color' => 'from-purple-500 to-indigo-500'],
                ['key' => 'match_info', 'title' => 'Match Info', 'color' => 'from-gray-500 to-gray-600'],
            ] as $element)
            <div class="card rounded-2xl overflow-hidden">
                <div class="bg-gradient-to-r {{ $element['color'] }} px-6 py-4">
                    <h3 class="text-white font-bold text-lg">{{ $element['title'] }}</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">X Position</label>
                            <input type="number" name="template_settings[{{ $element['key'] }}][x]"
                                   value="{{ $settings[$element['key']]['x'] ?? 540 }}"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                   onchange="updatePreview()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Y Position</label>
                            <input type="number" name="template_settings[{{ $element['key'] }}][y]"
                                   value="{{ $settings[$element['key']]['y'] ?? 540 }}"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                   onchange="updatePreview()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Font Size</label>
                            <input type="number" name="template_settings[{{ $element['key'] }}][font_size]"
                                   value="{{ $settings[$element['key']]['font_size'] ?? 32 }}"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                   onchange="updatePreview()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Color</label>
                            <input type="color" name="template_settings[{{ $element['key'] }}][font_color]"
                                   value="{{ $settings[$element['key']]['font_color'] ?? '#ffffff' }}"
                                   class="color-picker" onchange="updatePreview()">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Font Weight</label>
                            <select name="template_settings[{{ $element['key'] }}][font_weight]"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                    onchange="updatePreview()">
                                <option value="normal" {{ ($settings[$element['key']]['font_weight'] ?? '') == 'normal' ? 'selected' : '' }}>Normal</option>
                                <option value="bold" {{ ($settings[$element['key']]['font_weight'] ?? 'bold') == 'bold' ? 'selected' : '' }}>Bold</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Text Align</label>
                            <select name="template_settings[{{ $element['key'] }}][text_align]"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                    onchange="updatePreview()">
                                <option value="left" {{ ($settings[$element['key']]['text_align'] ?? '') == 'left' ? 'selected' : '' }}>Left</option>
                                <option value="center" {{ ($settings[$element['key']]['text_align'] ?? 'center') == 'center' ? 'selected' : '' }}>Center</option>
                                <option value="right" {{ ($settings[$element['key']]['text_align'] ?? '') == 'right' ? 'selected' : '' }}>Right</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach

            <!-- Award Icon Settings -->
            <div class="card rounded-2xl overflow-hidden">
                <div class="bg-gradient-to-r from-pink-500 to-rose-500 px-6 py-4">
                    <h3 class="text-white font-bold text-lg">Award Icon</h3>
                </div>
                <div class="p-6">
                    <!-- Icon Type Selection -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Icon Type</label>
                        <div class="flex gap-4">
                            <label class="flex items-center">
                                <input type="radio" name="template_settings[award_icon][type]" value="emoji"
                                       {{ ($settings['award_icon']['type'] ?? 'emoji') === 'emoji' ? 'checked' : '' }}
                                       class="mr-2" onchange="toggleIconType()">
                                <span class="text-sm">Emoji</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="template_settings[award_icon][type]" value="image"
                                       {{ ($settings['award_icon']['type'] ?? '') === 'image' ? 'checked' : '' }}
                                       class="mr-2" onchange="toggleIconType()">
                                <span class="text-sm">Custom Image (PNG)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Emoji Input -->
                    <div id="emojiIconSection" class="{{ ($settings['award_icon']['type'] ?? 'emoji') === 'image' ? 'hidden' : '' }} mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Emoji Icon</label>
                        <input type="text" name="icon_emoji" value="{{ $award->icon ?? '🏆' }}" maxlength="5"
                               class="w-32 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-2xl text-center"
                               onchange="updatePreview()">
                    </div>

                    <!-- Image Upload -->
                    <div id="imageIconSection" class="{{ ($settings['award_icon']['type'] ?? 'emoji') !== 'image' ? 'hidden' : '' }} mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Custom Icon Image</label>
                        <div class="flex items-center gap-4">
                            <div id="iconPreviewBox" class="w-20 h-20 bg-gray-100 dark:bg-gray-700 rounded-xl flex items-center justify-center border-2 border-dashed border-gray-300 dark:border-gray-600 overflow-hidden">
                                @if(isset($settings['award_icon']['image']))
                                    <img src="{{ asset('storage/' . $settings['award_icon']['image']) }}" alt="Icon" class="w-full h-full object-contain" id="iconPreviewImg">
                                @else
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                @endif
                            </div>
                            <div>
                                <input type="file" id="iconImageInput" accept="image/png,image/svg+xml" class="hidden" onchange="uploadIconImage(this)">
                                <button type="button" onclick="document.getElementById('iconImageInput').click()"
                                        class="px-4 py-2 bg-pink-500 hover:bg-pink-600 text-white rounded-lg text-sm font-medium">
                                    Upload PNG/SVG
                                </button>
                                <p class="text-xs text-gray-500 mt-1">Recommended: 200x200px, transparent background</p>
                            </div>
                        </div>
                        <input type="hidden" name="template_settings[award_icon][image]" id="iconImagePath" value="{{ $settings['award_icon']['image'] ?? '' }}">
                    </div>

                    <!-- Position & Size -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">X Position</label>
                            <input type="number" name="template_settings[award_icon][x]"
                                   value="{{ $settings['award_icon']['x'] ?? 540 }}"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                   onchange="updatePreview()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Y Position</label>
                            <input type="number" name="template_settings[award_icon][y]"
                                   value="{{ $settings['award_icon']['y'] ?? 100 }}"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                   onchange="updatePreview()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Width</label>
                            <input type="number" name="template_settings[award_icon][width]"
                                   value="{{ $settings['award_icon']['width'] ?? 80 }}"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                   onchange="updatePreview()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Height</label>
                            <input type="number" name="template_settings[award_icon][height]"
                                   value="{{ $settings['award_icon']['height'] ?? 80 }}"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                   onchange="updatePreview()">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Reset Form -->
<form id="resetForm" action="{{ route('admin.awards.template.reset', $award) }}" method="POST" class="hidden">
    @csrf
</form>

@push('scripts')
<script>
    // Scale factor for preview (1080 -> 360)
    const scaleFactor = 3;
    const canvasWidth = 1080 / scaleFactor;
    const canvasHeight = 1350 / scaleFactor;

    // Dragging state
    let isDragging = false;
    let dragElement = null;
    let dragKey = null;
    let startX, startY, initialX, initialY;

    function updatePreview() {
        const form = document.getElementById('templateForm');
        const formData = new FormData(form);

        // Update background
        const gradientFrom = formData.get('template_settings[background][gradient][from]');
        const gradientTo = formData.get('template_settings[background][gradient][to]');
        const gradientDir = formData.get('template_settings[background][gradient][direction]');
        document.getElementById('previewCanvas').style.background =
            `linear-gradient(${gradientDir}, ${gradientFrom}, ${gradientTo})`;

        // Update player image
        const playerImg = document.getElementById('previewPlayerImage');
        const playerImgDiv = playerImg.querySelector('div:not(.preview-element-label)');
        const imgX = parseInt(formData.get('template_settings[player_image][x]')) / scaleFactor;
        const imgY = parseInt(formData.get('template_settings[player_image][y]')) / scaleFactor;
        const imgW = parseInt(formData.get('template_settings[player_image][width]')) / scaleFactor;
        const imgH = parseInt(formData.get('template_settings[player_image][height]')) / scaleFactor;
        const imgBorder = parseInt(formData.get('template_settings[player_image][border_width]')) / scaleFactor;
        const imgBorderColor = formData.get('template_settings[player_image][border_color]');

        playerImg.style.left = (imgX - imgW/2) + 'px';
        playerImg.style.top = imgY + 'px';
        if (playerImgDiv) {
            playerImgDiv.style.width = imgW + 'px';
            playerImgDiv.style.height = imgH + 'px';
            playerImgDiv.style.border = `${imgBorder}px solid ${imgBorderColor}`;
        }

        // Update award icon
        const iconElement = document.getElementById('previewAwardIcon');
        const iconX = parseInt(formData.get('template_settings[award_icon][x]') || 540) / scaleFactor;
        const iconY = parseInt(formData.get('template_settings[award_icon][y]')) / scaleFactor;

        // Handle icon type (emoji vs image)
        const iconType = formData.get('template_settings[award_icon][type]') || 'emoji';
        const iconImg = iconElement.querySelector('#previewIconImage');
        const iconEmoji = iconElement.querySelector('#previewIconEmoji');

        if (iconType === 'image') {
            const iconWidth = parseInt(formData.get('template_settings[award_icon][width]') || 80) / scaleFactor;
            const iconHeight = parseInt(formData.get('template_settings[award_icon][height]') || 80) / scaleFactor;

            // Position icon centered at X position
            iconElement.style.left = (iconX - iconWidth / 2) + 'px';
            iconElement.style.right = 'auto';
            iconElement.style.top = iconY + 'px';
            iconElement.style.textAlign = 'left';

            if (iconImg) {
                iconImg.style.width = iconWidth + 'px';
                iconImg.style.height = iconHeight + 'px';
                iconImg.style.display = 'block';
            }
        } else {
            // For emoji, center horizontally
            iconElement.style.left = '0';
            iconElement.style.right = '0';
            iconElement.style.top = iconY + 'px';
            iconElement.style.textAlign = 'center';

            const iconFontSize = parseInt(formData.get('template_settings[award_icon][font_size]') || 64) / scaleFactor;
            if (iconEmoji) {
                iconEmoji.style.fontSize = iconFontSize + 'px';
            }
        }

        // Update text elements
        const textElements = [
            { id: 'previewAwardName', key: 'award_name', type: 'text' },
            { id: 'previewPlayerName', key: 'player_name', type: 'text' },
            { id: 'previewTeamName', key: 'team_name', type: 'text' },
            { id: 'previewMatchInfo', key: 'match_info', type: 'text' },
        ];

        textElements.forEach(el => {
            const element = document.getElementById(el.id);
            // Get the last span (not the label span)
            const spans = element.querySelectorAll('span:not(.preview-element-label)');
            const span = spans[spans.length - 1];

            const y = parseInt(formData.get(`template_settings[${el.key}][y]`)) / scaleFactor;
            const fontSize = parseInt(formData.get(`template_settings[${el.key}][font_size]`)) / scaleFactor;

            element.style.left = '0';
            element.style.right = '0';
            element.style.top = y + 'px';
            if (span) {
                span.style.fontSize = fontSize + 'px';

                const color = formData.get(`template_settings[${el.key}][font_color]`);
                const weight = formData.get(`template_settings[${el.key}][font_weight]`);
                span.style.color = color;
                span.style.fontWeight = weight;
            }
        });
    }

    function uploadBackground(input) {
        if (input.files && input.files[0]) {
            const formData = new FormData();
            formData.append('background_image', input.files[0]);
            formData.append('_token', '{{ csrf_token() }}');

            fetch('{{ route('admin.awards.template.upload-background', $award) }}', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('previewCanvas').style.backgroundImage = `url(${data.image_url})`;
                    document.getElementById('previewCanvas').style.backgroundSize = 'cover';
                }
            });
        }
    }

    // Icon type toggle
    function toggleIconType() {
        const iconType = document.querySelector('input[name="template_settings[award_icon][type]"]:checked').value;
        const emojiSection = document.getElementById('emojiIconSection');
        const imageSection = document.getElementById('imageIconSection');

        if (iconType === 'emoji') {
            emojiSection.classList.remove('hidden');
            imageSection.classList.add('hidden');
            updateIconPreviewToEmoji();
        } else {
            emojiSection.classList.add('hidden');
            imageSection.classList.remove('hidden');
            updateIconPreviewToImage();
        }
    }

    function updateIconPreviewToEmoji() {
        const iconElement = document.getElementById('previewAwardIcon');
        const emoji = document.querySelector('input[name="icon_emoji"]').value || '🏆';
        const fontSize = parseInt(document.querySelector('input[name="template_settings[award_icon][font_size]"]')?.value || 64) / scaleFactor;
        const iconY = parseInt(document.querySelector('input[name="template_settings[award_icon][y]"]')?.value || 100) / scaleFactor;

        // Remove image if exists
        const img = iconElement.querySelector('img');
        if (img) img.remove();

        // Position for emoji (centered)
        iconElement.style.left = '0';
        iconElement.style.right = '0';
        iconElement.style.top = iconY + 'px';
        iconElement.style.textAlign = 'center';

        // Add or update emoji span
        let emojiSpan = iconElement.querySelector('#previewIconEmoji');
        if (!emojiSpan) {
            emojiSpan = document.createElement('span');
            emojiSpan.id = 'previewIconEmoji';
            iconElement.appendChild(emojiSpan);
        }
        emojiSpan.textContent = emoji;
        emojiSpan.style.fontSize = fontSize + 'px';
    }

    function updateIconPreviewToImage() {
        const iconElement = document.getElementById('previewAwardIcon');
        const imagePath = document.getElementById('iconImagePath').value;

        if (!imagePath) return;

        const iconX = parseInt(document.querySelector('input[name="template_settings[award_icon][x]"]')?.value || 540) / scaleFactor;
        const iconY = parseInt(document.querySelector('input[name="template_settings[award_icon][y]"]')?.value || 100) / scaleFactor;
        const width = parseInt(document.querySelector('input[name="template_settings[award_icon][width]"]')?.value || 80) / scaleFactor;
        const height = parseInt(document.querySelector('input[name="template_settings[award_icon][height]"]')?.value || 80) / scaleFactor;

        // Remove emoji if exists
        const emojiSpan = iconElement.querySelector('#previewIconEmoji');
        if (emojiSpan) emojiSpan.remove();

        // Position for image (centered at X position)
        iconElement.style.left = (iconX - width / 2) + 'px';
        iconElement.style.right = 'auto';
        iconElement.style.top = iconY + 'px';
        iconElement.style.textAlign = 'left';

        // Add or update image
        let img = iconElement.querySelector('#previewIconImage');
        if (!img) {
            img = document.createElement('img');
            img.id = 'previewIconImage';
            img.alt = 'Icon';
            iconElement.appendChild(img);
        }
        img.src = '{{ asset("storage") }}/' + imagePath;
        img.style.width = width + 'px';
        img.style.height = height + 'px';
        img.style.objectFit = 'contain';
        img.style.display = 'block';
    }

    function uploadIconImage(input) {
        if (input.files && input.files[0]) {
            const formData = new FormData();
            formData.append('icon_image', input.files[0]);
            formData.append('_token', '{{ csrf_token() }}');

            fetch('{{ route('admin.awards.template.upload-icon', $award) }}', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update hidden input
                    document.getElementById('iconImagePath').value = data.image_path;

                    // Update preview box
                    const previewBox = document.getElementById('iconPreviewBox');
                    previewBox.innerHTML = `<img src="${data.image_url}" alt="Icon" class="w-full h-full object-contain" id="iconPreviewImg">`;

                    // Update canvas preview
                    updateIconPreviewToImage();
                } else {
                    alert(data.message || 'Failed to upload icon');
                }
            })
            .catch(err => {
                alert('Error uploading icon');
                console.error(err);
            });
        }
    }

    // Drag functionality
    function initDraggable() {
        const canvas = document.getElementById('previewCanvas');
        const draggables = document.querySelectorAll('.preview-element');

        draggables.forEach(el => {
            el.addEventListener('mousedown', startDrag);
            el.addEventListener('touchstart', startDrag, { passive: false });
        });

        document.addEventListener('mousemove', drag);
        document.addEventListener('touchmove', drag, { passive: false });
        document.addEventListener('mouseup', endDrag);
        document.addEventListener('touchend', endDrag);
    }

    function startDrag(e) {
        e.preventDefault();
        dragElement = e.currentTarget;
        dragKey = dragElement.dataset.element;

        // Add selected and dragging states
        document.querySelectorAll('.preview-element').forEach(el => {
            el.classList.remove('selected', 'dragging');
        });
        dragElement.classList.add('selected', 'dragging');

        const canvas = document.getElementById('previewCanvas');
        const canvasRect = canvas.getBoundingClientRect();

        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;

        startX = clientX;
        startY = clientY;

        // Get current position from form inputs
        const yInput = document.querySelector(`input[name="template_settings[${dragKey}][y]"]`);
        initialY = yInput ? parseInt(yInput.value) : 0;

        if (dragKey === 'player_image') {
            const xInput = document.querySelector(`input[name="template_settings[${dragKey}][x]"]`);
            initialX = xInput ? parseInt(xInput.value) : 0;
        }

        isDragging = true;
        dragElement.style.zIndex = '100';

        // Hide drag hint while dragging
        const hint = document.querySelector('.drag-hint');
        if (hint) hint.style.opacity = '0';
    }

    function drag(e) {
        if (!isDragging || !dragElement) return;
        e.preventDefault();

        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;

        const deltaX = (clientX - startX) * scaleFactor;
        const deltaY = (clientY - startY) * scaleFactor;

        // Update Y position for all elements
        const newY = Math.max(0, Math.min(1350, initialY + deltaY));
        const yInput = document.querySelector(`input[name="template_settings[${dragKey}][y]"]`);
        if (yInput) {
            yInput.value = Math.round(newY);
        }

        // Update X position only for player_image
        if (dragKey === 'player_image') {
            const newX = Math.max(0, Math.min(1080, initialX + deltaX));
            const xInput = document.querySelector(`input[name="template_settings[${dragKey}][x]"]`);
            if (xInput) {
                xInput.value = Math.round(newX);
            }
        }

        updatePreview();
    }

    function endDrag(e) {
        if (dragElement) {
            dragElement.style.zIndex = '';
            dragElement.classList.remove('dragging');
        }
        isDragging = false;
        dragElement = null;
        dragKey = null;

        // Restore drag hint
        const hint = document.querySelector('.drag-hint');
        if (hint) hint.style.opacity = '1';
    }

    // Initialize preview positions and drag
    document.addEventListener('DOMContentLoaded', function() {
        updatePreview();
        initDraggable();
    });
</script>
@endpush
@endsection
