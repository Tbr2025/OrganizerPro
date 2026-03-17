@extends('backend.layouts.app')

@section('title', ($template ? 'Edit' : 'Create') . ' Template | ' . $tournament->name)

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&family=Open+Sans:wght@300;400;600;700&family=Montserrat:wght@300;400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800;900&family=Oswald:wght@300;400;500;600;700&family=Bebas+Neue&family=Anton&family=Bangers&display=swap" rel="stylesheet">
<style>
    /* Full-screen editor layout */
    .editor-container { height: calc(100vh - 64px); display: flex; flex-direction: column; background: #0f0f1a; overflow: hidden; }
    .editor-header { height: 56px; background: #1a1a2e; border-bottom: 1px solid #2d2d44; display: flex; align-items: center; padding: 0 16px; flex-shrink: 0; }
    .editor-body { flex: 1; display: flex; overflow: hidden; }
    .editor-sidebar { width: 280px; background: #1a1a2e; border-right: 1px solid #2d2d44; display: flex; flex-direction: column; flex-shrink: 0; }
    .editor-canvas-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
    .editor-canvas-wrapper { flex: 1; overflow: auto; display: flex; align-items: center; justify-content: center; padding: 40px; background: linear-gradient(45deg, #0a0a14 25%, transparent 25%), linear-gradient(-45deg, #0a0a14 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #0a0a14 75%), linear-gradient(-45deg, transparent 75%, #0a0a14 75%); background-size: 20px 20px; background-position: 0 0, 0 10px, 10px -10px, -10px 0px; background-color: #12121f; }
    .editor-properties { width: 300px; background: #1a1a2e; border-left: 1px solid #2d2d44; flex-shrink: 0; overflow-y: auto; }
    .editor-footer { height: 40px; background: #1a1a2e; border-top: 1px solid #2d2d44; display: flex; align-items: center; padding: 0 16px; flex-shrink: 0; }

    /* Sidebar tabs */
    .sidebar-tabs { display: flex; border-bottom: 1px solid #2d2d44; }
    .sidebar-tab { flex: 1; padding: 12px 8px; text-align: center; font-size: 11px; font-weight: 500; color: #8b8ba7; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s; }
    .sidebar-tab:hover { color: #fff; background: rgba(99, 102, 241, 0.1); }
    .sidebar-tab.active { color: #818cf8; border-bottom-color: #818cf8; }
    .sidebar-tab svg { width: 20px; height: 20px; margin: 0 auto 4px; display: block; }
    .sidebar-content { flex: 1; overflow-y: auto; padding: 12px; }
    .sidebar-section { margin-bottom: 16px; }
    .sidebar-section-title { font-size: 11px; font-weight: 600; color: #8b8ba7; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }

    /* Draggable items */
    .draggable-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #252538; border-radius: 8px; cursor: grab; margin-bottom: 6px; transition: all 0.2s; border: 1px solid transparent; }
    .draggable-item:hover { background: #2d2d4a; border-color: #4f46e5; }
    .draggable-item:active { cursor: grabbing; }
    .draggable-item .icon { width: 36px; height: 36px; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .draggable-item .icon.text { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
    .draggable-item .icon.image { background: linear-gradient(135deg, #ec4899, #f43f5e); }
    .draggable-item .icon svg { width: 18px; height: 18px; color: white; }
    .draggable-item .info { flex: 1; min-width: 0; }
    .draggable-item .name { font-size: 13px; font-weight: 500; color: #e2e2e2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .draggable-item .type { font-size: 10px; color: #8b8ba7; }

    /* Canvas container */
    #canvas-container { position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); border-radius: 4px; overflow: hidden; }

    /* Properties panel */
    .prop-section { padding: 16px; border-bottom: 1px solid #2d2d44; }
    .prop-section-title { font-size: 11px; font-weight: 600; color: #8b8ba7; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
    .prop-group { margin-bottom: 12px; }
    .prop-label { font-size: 11px; color: #8b8ba7; margin-bottom: 4px; display: block; }
    .prop-input { width: 100%; background: #252538; border: 1px solid #3d3d5c; border-radius: 6px; padding: 8px 10px; font-size: 13px; color: #e2e2e2; outline: none; transition: border-color 0.2s; }
    .prop-input:focus { border-color: #6366f1; }
    .prop-input-row { display: flex; gap: 8px; }
    .prop-input-row .prop-group { flex: 1; }
    .prop-btn { padding: 8px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; transition: all 0.2s; border: none; }
    .prop-btn-primary { background: #6366f1; color: white; }
    .prop-btn-primary:hover { background: #4f46e5; }
    .prop-btn-secondary { background: #252538; color: #e2e2e2; border: 1px solid #3d3d5c; }
    .prop-btn-secondary:hover { background: #2d2d4a; }
    .prop-btn-danger { background: #ef4444; color: white; }
    .prop-btn-danger:hover { background: #dc2626; }

    /* Color picker */
    .color-picker-wrapper { display: flex; align-items: center; gap: 8px; }
    .color-preview { width: 36px; height: 36px; border-radius: 6px; border: 2px solid #3d3d5c; cursor: pointer; }
    .color-presets { display: flex; gap: 4px; flex-wrap: wrap; }
    .color-preset { width: 24px; height: 24px; border-radius: 4px; cursor: pointer; border: 2px solid transparent; transition: all 0.2s; }
    .color-preset:hover { transform: scale(1.1); }
    .color-preset.active { border-color: #fff; }

    /* Slider */
    .prop-slider { width: 100%; height: 4px; border-radius: 2px; background: #3d3d5c; appearance: none; cursor: pointer; }
    .prop-slider::-webkit-slider-thumb { appearance: none; width: 14px; height: 14px; border-radius: 50%; background: #6366f1; cursor: pointer; }

    /* Toolbar buttons */
    .toolbar-group { display: flex; align-items: center; gap: 4px; padding: 0 12px; border-right: 1px solid #2d2d44; }
    .toolbar-group:last-child { border-right: none; }
    .toolbar-btn { width: 36px; height: 36px; border-radius: 6px; display: flex; align-items: center; justify-content: center; background: transparent; border: none; color: #8b8ba7; cursor: pointer; transition: all 0.2s; }
    .toolbar-btn:hover { background: #252538; color: #fff; }
    .toolbar-btn.active { background: #6366f1; color: #fff; }
    .toolbar-btn svg { width: 18px; height: 18px; }
    .toolbar-divider { width: 1px; height: 24px; background: #2d2d44; margin: 0 8px; }

    /* Zoom controls */
    .zoom-controls { display: flex; align-items: center; gap: 8px; }
    .zoom-btn { width: 28px; height: 28px; border-radius: 4px; display: flex; align-items: center; justify-content: center; background: #252538; border: none; color: #8b8ba7; cursor: pointer; }
    .zoom-btn:hover { background: #3d3d5c; color: #fff; }
    .zoom-value { font-size: 12px; color: #8b8ba7; min-width: 50px; text-align: center; }

    /* Layers panel */
    .layer-item { display: flex; align-items: center; gap: 8px; padding: 8px 10px; background: #252538; border-radius: 6px; margin-bottom: 4px; cursor: pointer; transition: all 0.2s; }
    .layer-item:hover { background: #2d2d4a; }
    .layer-item.selected { background: #3730a3; border: 1px solid #6366f1; }
    .layer-thumb { width: 32px; height: 32px; border-radius: 4px; background: #1a1a2e; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
    .layer-thumb svg { width: 16px; height: 16px; color: #8b8ba7; }
    .layer-info { flex: 1; min-width: 0; }
    .layer-name { font-size: 12px; color: #e2e2e2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .layer-type { font-size: 10px; color: #8b8ba7; }
    .layer-actions { display: flex; gap: 4px; }
    .layer-action { width: 24px; height: 24px; border-radius: 4px; display: flex; align-items: center; justify-content: center; background: transparent; border: none; color: #8b8ba7; cursor: pointer; }
    .layer-action:hover { background: #3d3d5c; color: #fff; }

    /* No selection state */
    .no-selection { padding: 40px 20px; text-align: center; color: #8b8ba7; }
    .no-selection svg { width: 48px; height: 48px; margin: 0 auto 12px; opacity: 0.5; }
    .no-selection p { font-size: 13px; margin-bottom: 4px; }
    .no-selection small { font-size: 11px; opacity: 0.7; }

    /* Font selector */
    .font-option { padding: 8px 12px; cursor: pointer; }
    .font-option:hover { background: #252538; }

    /* Alignment buttons */
    .align-buttons { display: flex; gap: 4px; }
    .align-btn { flex: 1; height: 32px; border-radius: 4px; display: flex; align-items: center; justify-content: center; background: #252538; border: 1px solid #3d3d5c; color: #8b8ba7; cursor: pointer; transition: all 0.2s; }
    .align-btn:hover { background: #3d3d5c; color: #fff; }
    .align-btn.active { background: #6366f1; border-color: #6366f1; color: #fff; }
    .align-btn svg { width: 16px; height: 16px; }

    /* Keyboard shortcuts hint */
    .shortcuts-hint { position: fixed; bottom: 60px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.9); padding: 8px 16px; border-radius: 8px; font-size: 11px; color: #8b8ba7; display: none; z-index: 100; }
    .shortcuts-hint kbd { background: #3d3d5c; padding: 2px 6px; border-radius: 3px; margin: 0 2px; color: #fff; }
</style>
@endpush

@section('admin-content')
<div class="editor-container" id="editorApp">
    {{-- Header Toolbar --}}
    <div class="editor-header">
        <div class="flex items-center gap-4 flex-1">
            {{-- Back button --}}
            <a href="{{ route('admin.tournaments.templates.index', $tournament) }}" class="toolbar-btn" title="Back to templates">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>

            {{-- Template name --}}
            <div class="flex items-center gap-2">
                <input type="text" id="templateName" value="{{ $template?->name ?? 'Untitled Template' }}"
                       class="bg-transparent border-none text-white font-medium text-sm focus:outline-none focus:bg-gray-800 px-2 py-1 rounded" style="min-width: 200px;">
                <span class="text-xs text-gray-500 bg-gray-800 px-2 py-1 rounded">{{ \App\Models\TournamentTemplate::getTypeDisplay($type) }}</span>
            </div>
        </div>

        {{-- Center toolbar --}}
        <div class="flex items-center">
            <div class="toolbar-group">
                <button class="toolbar-btn" onclick="editor.undo()" title="Undo (Ctrl+Z)">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                </button>
                <button class="toolbar-btn" onclick="editor.redo()" title="Redo (Ctrl+Y)">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"/></svg>
                </button>
            </div>
            <div class="toolbar-group">
                <button class="toolbar-btn" onclick="editor.duplicate()" title="Duplicate (Ctrl+D)">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </button>
                <button class="toolbar-btn" onclick="editor.delete()" title="Delete (Del)">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
            <div class="toolbar-group">
                <button class="toolbar-btn" onclick="editor.bringForward()" title="Bring Forward">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 11l7-7 7 7M5 19l7-7 7 7"/></svg>
                </button>
                <button class="toolbar-btn" onclick="editor.sendBackward()" title="Send Backward">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 13l-7 7-7-7m14-8l-7 7-7-7"/></svg>
                </button>
            </div>
        </div>

        {{-- Right actions --}}
        <div class="flex items-center gap-3 flex-1 justify-end">
            <button onclick="editor.preview()" class="prop-btn prop-btn-secondary flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Preview
            </button>
            <button onclick="editor.save()" class="prop-btn prop-btn-primary flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Save Template
            </button>
        </div>
    </div>

    {{-- Main Editor Body --}}
    <div class="editor-body">
        {{-- Left Sidebar --}}
        <div class="editor-sidebar">
            <div class="sidebar-tabs">
                <div class="sidebar-tab active" data-tab="elements" onclick="switchTab('elements')">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                    Elements
                </div>
                <div class="sidebar-tab" data-tab="uploads" onclick="switchTab('uploads')">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Uploads
                </div>
                <div class="sidebar-tab" data-tab="layers" onclick="switchTab('layers')">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    Layers
                </div>
            </div>

            <div class="sidebar-content">
                {{-- Elements Tab --}}
                <div id="tab-elements" class="tab-content">
                    {{-- Text Placeholders --}}
                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Text Placeholders</div>
                        @foreach($placeholders as $placeholder)
                            @php $isImage = in_array($placeholder, ['player_image', 'team_logo', 'tournament_logo', 'team_a_logo', 'team_b_logo', 'team_a_captain_image', 'team_b_captain_image', 'team_a_sponsor_logo', 'team_b_sponsor_logo', 'man_of_the_match_image', 'qr_code']); @endphp
                            @if(!$isImage)
                            <div class="draggable-item" draggable="true" data-type="text" data-placeholder="{{ $placeholder }}">
                                <div class="icon text">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                                </div>
                                <div class="info">
                                    <div class="name">{{ str_replace('_', ' ', ucwords($placeholder, '_')) }}</div>
                                    <div class="type">Text</div>
                                </div>
                            </div>
                            @endif
                        @endforeach
                    </div>

                    {{-- Image Placeholders --}}
                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Image Placeholders</div>
                        @foreach($placeholders as $placeholder)
                            @php $isImage = in_array($placeholder, ['player_image', 'team_logo', 'tournament_logo', 'team_a_logo', 'team_b_logo', 'team_a_captain_image', 'team_b_captain_image', 'team_a_sponsor_logo', 'team_b_sponsor_logo', 'man_of_the_match_image', 'qr_code']); @endphp
                            @if($isImage)
                            <div class="draggable-item" draggable="true" data-type="image" data-placeholder="{{ $placeholder }}">
                                <div class="icon image">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                                <div class="info">
                                    <div class="name">{{ str_replace('_', ' ', ucwords($placeholder, '_')) }}</div>
                                    <div class="type">Image</div>
                                </div>
                            </div>
                            @endif
                        @endforeach
                    </div>

                    {{-- Shapes --}}
                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Shapes</div>
                        <div class="draggable-item" draggable="true" data-type="shape" data-shape="rect">
                            <div class="icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                                <svg fill="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
                            </div>
                            <div class="info"><div class="name">Rectangle</div><div class="type">Shape</div></div>
                        </div>
                        <div class="draggable-item" draggable="true" data-type="shape" data-shape="circle">
                            <div class="icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
                            </div>
                            <div class="info"><div class="name">Circle</div><div class="type">Shape</div></div>
                        </div>
                        <div class="draggable-item" draggable="true" data-type="shape" data-shape="line">
                            <div class="icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                                <svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            </div>
                            <div class="info"><div class="name">Line</div><div class="type">Shape</div></div>
                        </div>
                    </div>
                </div>

                {{-- Uploads Tab --}}
                <div id="tab-uploads" class="tab-content hidden">
                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Upload Images</div>
                        <label class="block w-full cursor-pointer">
                            <div class="border-2 border-dashed border-gray-600 rounded-lg p-6 text-center hover:border-indigo-500 transition">
                                <input type="file" id="imageUpload" accept="image/*" class="hidden" onchange="editor.uploadImage(this)">
                                <svg class="w-8 h-8 mx-auto text-gray-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <p class="text-sm text-gray-400">Click to upload</p>
                                <p class="text-xs text-gray-500 mt-1">PNG, JPG up to 5MB</p>
                            </div>
                        </label>
                    </div>
                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Uploaded Images</div>
                        <div id="uploadedImages" class="grid grid-cols-2 gap-2">
                            {{-- Populated by JS --}}
                        </div>
                    </div>
                </div>

                {{-- Layers Tab --}}
                <div id="tab-layers" class="tab-content hidden">
                    <div class="sidebar-section">
                        <div class="sidebar-section-title flex justify-between items-center">
                            <span>Layers</span>
                            <span id="layerCount" class="text-xs bg-gray-700 px-2 py-0.5 rounded">0</span>
                        </div>
                        <div id="layersList">
                            {{-- Populated by JS --}}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Canvas Area --}}
        <div class="editor-canvas-area">
            <div class="editor-canvas-wrapper" id="canvasWrapper">
                <div id="canvas-container">
                    <canvas id="fabricCanvas"></canvas>
                </div>
            </div>
        </div>

        {{-- Properties Panel --}}
        <div class="editor-properties" id="propertiesPanel">
            {{-- No Selection State --}}
            <div id="noSelectionPanel" class="no-selection">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                <p>Select an element</p>
                <small>Click on an element to edit its properties</small>
            </div>

            {{-- Text Properties --}}
            <div id="textPropertiesPanel" class="hidden">
                <div class="prop-section">
                    <div class="prop-section-title">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                        Text Properties
                    </div>

                    <div class="prop-group">
                        <label class="prop-label">Font Family</label>
                        <select id="propFontFamily" class="prop-input" onchange="editor.updateTextProperty('fontFamily', this.value)">
                            <option value="Arial">Arial</option>
                            <option value="Roboto">Roboto</option>
                            <option value="Open Sans">Open Sans</option>
                            <option value="Montserrat">Montserrat</option>
                            <option value="Poppins">Poppins</option>
                            <option value="Oswald">Oswald</option>
                            <option value="Bebas Neue">Bebas Neue</option>
                            <option value="Anton">Anton</option>
                            <option value="Bangers">Bangers</option>
                            <option value="Impact">Impact</option>
                        </select>
                    </div>

                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Size</label>
                            <input type="number" id="propFontSize" class="prop-input" min="8" max="200" onchange="editor.updateTextProperty('fontSize', parseInt(this.value))">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Weight</label>
                            <select id="propFontWeight" class="prop-input" onchange="editor.updateTextProperty('fontWeight', this.value)">
                                <option value="300">Light</option>
                                <option value="400">Regular</option>
                                <option value="500">Medium</option>
                                <option value="600">SemiBold</option>
                                <option value="700">Bold</option>
                                <option value="800">ExtraBold</option>
                                <option value="900">Black</option>
                            </select>
                        </div>
                    </div>

                    <div class="prop-group">
                        <label class="prop-label">Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="propTextColor" class="color-preview" onchange="editor.updateTextProperty('fill', this.value)">
                            <div class="color-presets">
                                <div class="color-preset" style="background: #ffffff" onclick="editor.setTextColor('#ffffff')"></div>
                                <div class="color-preset" style="background: #000000" onclick="editor.setTextColor('#000000')"></div>
                                <div class="color-preset" style="background: #FFD700" onclick="editor.setTextColor('#FFD700')"></div>
                                <div class="color-preset" style="background: #EF4444" onclick="editor.setTextColor('#EF4444')"></div>
                                <div class="color-preset" style="background: #3B82F6" onclick="editor.setTextColor('#3B82F6')"></div>
                                <div class="color-preset" style="background: #10B981" onclick="editor.setTextColor('#10B981')"></div>
                            </div>
                        </div>
                    </div>

                    <div class="prop-group">
                        <label class="prop-label">Alignment</label>
                        <div class="align-buttons">
                            <button class="align-btn" data-align="left" onclick="editor.updateTextProperty('textAlign', 'left')">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h14"/></svg>
                            </button>
                            <button class="align-btn" data-align="center" onclick="editor.updateTextProperty('textAlign', 'center')">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10M5 18h14"/></svg>
                            </button>
                            <button class="align-btn" data-align="right" onclick="editor.updateTextProperty('textAlign', 'right')">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M10 12h10M6 18h14"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="prop-group">
                        <label class="prop-label">Letter Spacing</label>
                        <input type="range" id="propLetterSpacing" class="prop-slider" min="0" max="50" value="0" oninput="editor.updateTextProperty('charSpacing', parseInt(this.value) * 10)">
                    </div>
                </div>

                <div class="prop-section">
                    <div class="prop-section-title">Effects</div>
                    <div class="prop-group">
                        <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                            <input type="checkbox" id="propTextShadow" class="rounded" onchange="editor.toggleTextShadow(this.checked)">
                            Text Shadow
                        </label>
                    </div>
                    <div id="shadowControls" class="hidden mt-3 space-y-3">
                        <div class="prop-input-row">
                            <div class="prop-group">
                                <label class="prop-label">Offset X</label>
                                <input type="number" id="propShadowX" class="prop-input" value="2" onchange="editor.updateShadow()">
                            </div>
                            <div class="prop-group">
                                <label class="prop-label">Offset Y</label>
                                <input type="number" id="propShadowY" class="prop-input" value="2" onchange="editor.updateShadow()">
                            </div>
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Blur</label>
                            <input type="range" id="propShadowBlur" class="prop-slider" min="0" max="20" value="5" oninput="editor.updateShadow()">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Image Properties --}}
            <div id="imagePropertiesPanel" class="hidden">
                <div class="prop-section">
                    <div class="prop-section-title">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Image Properties
                    </div>

                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Width</label>
                            <input type="number" id="propImageWidth" class="prop-input" onchange="editor.updateImageSize()">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Height</label>
                            <input type="number" id="propImageHeight" class="prop-input" onchange="editor.updateImageSize()">
                        </div>
                    </div>

                    <div class="prop-group">
                        <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                            <input type="checkbox" id="propLockRatio" class="rounded" checked>
                            Lock aspect ratio
                        </label>
                    </div>

                    <div class="prop-group">
                        <label class="prop-label">Border Radius</label>
                        <input type="range" id="propBorderRadius" class="prop-slider" min="0" max="50" value="0" oninput="editor.updateImageRadius(this.value)">
                    </div>
                </div>
            </div>

            {{-- Shape Properties --}}
            <div id="shapePropertiesPanel" class="hidden">
                <div class="prop-section">
                    <div class="prop-section-title">Shape Properties</div>

                    <div class="prop-group">
                        <label class="prop-label">Fill Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="propShapeFill" class="color-preview" onchange="editor.updateShapeProperty('fill', this.value)">
                        </div>
                    </div>

                    <div class="prop-group">
                        <label class="prop-label">Stroke Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="propShapeStroke" class="color-preview" value="#ffffff" onchange="editor.updateShapeProperty('stroke', this.value)">
                        </div>
                    </div>

                    <div class="prop-group">
                        <label class="prop-label">Stroke Width</label>
                        <input type="range" id="propStrokeWidth" class="prop-slider" min="0" max="20" value="0" oninput="editor.updateShapeProperty('strokeWidth', parseInt(this.value))">
                    </div>
                </div>
            </div>

            {{-- Common Properties (Position, Rotation, Opacity) --}}
            <div id="commonPropertiesPanel" class="hidden">
                <div class="prop-section">
                    <div class="prop-section-title">Position & Size</div>
                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">X</label>
                            <input type="number" id="propPosX" class="prop-input" onchange="editor.updatePosition()">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Y</label>
                            <input type="number" id="propPosY" class="prop-input" onchange="editor.updatePosition()">
                        </div>
                    </div>
                </div>

                <div class="prop-section">
                    <div class="prop-section-title">Transform</div>
                    <div class="prop-group">
                        <label class="prop-label">Rotation: <span id="rotationValue">0</span>°</label>
                        <input type="range" id="propRotation" class="prop-slider" min="-180" max="180" value="0" oninput="editor.updateRotation(this.value)">
                    </div>
                    <div class="prop-group">
                        <label class="prop-label">Opacity: <span id="opacityValue">100</span>%</label>
                        <input type="range" id="propOpacity" class="prop-slider" min="0" max="100" value="100" oninput="editor.updateOpacity(this.value)">
                    </div>
                </div>

                <div class="prop-section">
                    <button onclick="editor.delete()" class="prop-btn prop-btn-danger w-full">
                        Delete Element
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="editor-footer">
        <div class="flex items-center gap-4 flex-1">
            <div class="zoom-controls">
                <button class="zoom-btn" onclick="editor.zoomOut()">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                </button>
                <span class="zoom-value" id="zoomValue">100%</span>
                <button class="zoom-btn" onclick="editor.zoomIn()">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </button>
                <button class="zoom-btn" onclick="editor.zoomReset()" title="Reset zoom">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                </button>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-xs text-gray-500">Canvas: <span id="canvasSizeDisplay">1080 x 1080</span></span>
            <select id="canvasSizeSelect" class="bg-gray-800 border border-gray-700 rounded px-2 py-1 text-xs text-gray-300" onchange="editor.changeCanvasSize(this.value)">
                <option value="1080x1080">Square (1080x1080)</option>
                <option value="1080x1350">Portrait (1080x1350)</option>
                <option value="1080x1920">Story (1080x1920)</option>
                <option value="1920x1080">Landscape (1920x1080)</option>
            </select>
        </div>
    </div>
</div>

{{-- Preview Modal --}}
<div id="previewModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/80" onclick="editor.closePreview()"></div>
    <div class="absolute inset-8 bg-gray-900 rounded-xl overflow-hidden flex flex-col">
        <div class="flex justify-between items-center p-4 border-b border-gray-700">
            <h3 class="font-semibold text-white">Preview</h3>
            <button onclick="editor.closePreview()" class="text-gray-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-auto p-8 flex items-center justify-center bg-gray-950">
            <img id="previewImage" src="" alt="Preview" class="max-w-full max-h-full shadow-2xl rounded-lg">
        </div>
        <div class="p-4 border-t border-gray-700 flex justify-end gap-3">
            <a id="downloadPreviewBtn" href="#" download="template.png" class="prop-btn prop-btn-primary">Download PNG</a>
        </div>
    </div>
</div>

{{-- Hidden form for saving --}}
<form id="saveForm" method="POST" action="{{ $template ? route('admin.tournaments.templates.update', [$tournament, $template]) : route('admin.tournaments.templates.store', $tournament) }}" enctype="multipart/form-data" class="hidden">
    @csrf
    @if($template) @method('PUT') @endif
    <input type="hidden" name="name" id="formName">
    <input type="hidden" name="type" value="{{ $type }}">
    <input type="hidden" name="layout_json" id="formLayoutJson">
    <input type="hidden" name="canvas_width" id="formCanvasWidth">
    <input type="hidden" name="canvas_height" id="formCanvasHeight">
    <input type="hidden" name="background_image_data" id="formBackgroundData">
    <input type="file" name="background_image" id="formBackgroundFile">
</form>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<script>
// Template Editor using Fabric.js
const editor = {
    canvas: null,
    history: [],
    historyIndex: -1,
    zoom: 1,
    displayScale: 0.5,
    canvasWidth: 1080,
    canvasHeight: 1080,
    backgroundImage: null,

    init() {
        // Calculate display scale based on available space
        const wrapper = document.getElementById('canvasWrapper');
        const availableWidth = wrapper.clientWidth - 80;
        const availableHeight = wrapper.clientHeight - 80;
        this.displayScale = Math.min(availableWidth / this.canvasWidth, availableHeight / this.canvasHeight, 0.6);

        // Initialize Fabric.js canvas at display scale
        this.canvas = new fabric.Canvas('fabricCanvas', {
            width: this.canvasWidth * this.displayScale,
            height: this.canvasHeight * this.displayScale,
            backgroundColor: '#1a1a2e',
            preserveObjectStacking: true,
            selection: true,
        });

        // Set canvas zoom to match display scale
        this.canvas.setZoom(this.displayScale);

        // Set up event listeners
        this.setupEventListeners();
        this.setupDragAndDrop();
        this.setupKeyboardShortcuts();

        // Load existing template data if editing
        @if($template && $template->layout_json)
            this.loadTemplate(@json($template->layout_json));
        @endif

        @if($template && $template->background_image)
            this.loadBackgroundImage('{{ $template->background_image_url }}');
        @endif

        // Initial state
        this.saveHistory();
        this.updateLayersList();
    },

    setupEventListeners() {
        this.canvas.on('selection:created', (e) => this.onSelectionChange(e.selected[0]));
        this.canvas.on('selection:updated', (e) => this.onSelectionChange(e.selected[0]));
        this.canvas.on('selection:cleared', () => this.onSelectionClear());
        this.canvas.on('object:modified', () => {
            this.saveHistory();
            this.updatePropertiesPanel();
            this.updateLayersList();
        });
        this.canvas.on('object:added', () => this.updateLayersList());
        this.canvas.on('object:removed', () => this.updateLayersList());
    },

    setupDragAndDrop() {
        const items = document.querySelectorAll('.draggable-item');
        const canvasWrapper = document.getElementById('canvasWrapper');

        items.forEach(item => {
            item.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('type', item.dataset.type);
                e.dataTransfer.setData('placeholder', item.dataset.placeholder || '');
                e.dataTransfer.setData('shape', item.dataset.shape || '');
            });
        });

        canvasWrapper.addEventListener('dragover', (e) => e.preventDefault());
        canvasWrapper.addEventListener('drop', (e) => {
            e.preventDefault();
            const type = e.dataTransfer.getData('type');
            const placeholder = e.dataTransfer.getData('placeholder');
            const shape = e.dataTransfer.getData('shape');

            const rect = this.canvas.getElement().getBoundingClientRect();
            const currentZoom = this.canvas.getZoom();
            // Convert screen coordinates to canvas coordinates
            const x = (e.clientX - rect.left) / currentZoom;
            const y = (e.clientY - rect.top) / currentZoom;

            if (type === 'text') {
                this.addTextElement(placeholder, x, y);
            } else if (type === 'image') {
                this.addImagePlaceholder(placeholder, x, y);
            } else if (type === 'shape') {
                this.addShape(shape, x, y);
            }
        });
    },

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

            if (e.ctrlKey || e.metaKey) {
                if (e.key === 'z') { e.preventDefault(); this.undo(); }
                if (e.key === 'y') { e.preventDefault(); this.redo(); }
                if (e.key === 'd') { e.preventDefault(); this.duplicate(); }
                if (e.key === 's') { e.preventDefault(); this.save(); }
                if (e.key === 'c') { e.preventDefault(); this.copy(); }
                if (e.key === 'v') { e.preventDefault(); this.paste(); }
            }
            if (e.key === 'Delete' || e.key === 'Backspace') { this.delete(); }
            if (e.key === 'Escape') { this.canvas.discardActiveObject(); this.canvas.renderAll(); }
        });
    },

    addTextElement(placeholder, x, y) {
        const displayText = '{{' + placeholder + '}}';
        const text = new fabric.IText(displayText, {
            left: x,
            top: y,
            fontSize: 32,
            fontFamily: 'Montserrat',
            fontWeight: '700',
            fill: '#ffffff',
            originX: 'center',
            originY: 'center',
            textAlign: 'center',
            shadow: new fabric.Shadow({ color: 'rgba(0,0,0,0.5)', blur: 5, offsetX: 2, offsetY: 2 }),
        });
        text.placeholder = placeholder;
        text.elementType = 'text';
        this.canvas.add(text);
        this.canvas.setActiveObject(text);
        this.saveHistory();
    },

    addImagePlaceholder(placeholder, x, y) {
        const size = 150;
        const group = new fabric.Group([], {
            left: x,
            top: y,
            originX: 'center',
            originY: 'center',
        });

        // Background rect
        const rect = new fabric.Rect({
            width: size,
            height: size,
            fill: 'rgba(99, 102, 241, 0.3)',
            stroke: '#6366f1',
            strokeWidth: 2,
            strokeDashArray: [5, 5],
            rx: 8,
            ry: 8,
            originX: 'center',
            originY: 'center',
        });

        // Icon
        const iconText = new fabric.Text('🖼️', {
            fontSize: 32,
            originX: 'center',
            originY: 'center',
            top: -15,
        });

        // Label
        const label = new fabric.Text(placeholder.replace(/_/g, ' '), {
            fontSize: 12,
            fill: '#ffffff',
            fontFamily: 'Arial',
            originX: 'center',
            originY: 'center',
            top: 30,
        });

        group.addWithUpdate(rect);
        group.addWithUpdate(iconText);
        group.addWithUpdate(label);

        group.placeholder = placeholder;
        group.elementType = 'image';
        group.placeholderWidth = size;
        group.placeholderHeight = size;

        this.canvas.add(group);
        this.canvas.setActiveObject(group);
        this.saveHistory();
    },

    addShape(shape, x, y) {
        let obj;
        const commonProps = {
            left: x,
            top: y,
            fill: 'rgba(99, 102, 241, 0.5)',
            stroke: '#6366f1',
            strokeWidth: 2,
            originX: 'center',
            originY: 'center',
        };

        if (shape === 'rect') {
            obj = new fabric.Rect({ ...commonProps, width: 150, height: 100, rx: 8, ry: 8 });
        } else if (shape === 'circle') {
            obj = new fabric.Circle({ ...commonProps, radius: 60 });
        } else if (shape === 'line') {
            obj = new fabric.Line([0, 0, 150, 0], { ...commonProps, strokeWidth: 4 });
        }

        obj.elementType = 'shape';
        obj.shapeType = shape;
        this.canvas.add(obj);
        this.canvas.setActiveObject(obj);
        this.saveHistory();
    },

    uploadImage(input) {
        if (!input.files || !input.files[0]) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            fabric.Image.fromURL(e.target.result, (img) => {
                const maxSize = 300;
                const scale = Math.min(maxSize / img.width, maxSize / img.height);
                img.scale(scale);
                img.set({
                    left: this.canvasWidth / 2,
                    top: this.canvasHeight / 2,
                    originX: 'center',
                    originY: 'center',
                });
                img.elementType = 'uploadedImage';
                this.canvas.add(img);
                this.canvas.setActiveObject(img);
                this.saveHistory();
            });
        };
        reader.readAsDataURL(input.files[0]);
        input.value = '';
    },

    loadBackgroundImage(url) {
        fabric.Image.fromURL(url, (img) => {
            // Scale to fit the full canvas dimensions
            const scaleX = this.canvasWidth / img.width;
            const scaleY = this.canvasHeight / img.height;
            img.set({
                scaleX: scaleX,
                scaleY: scaleY,
                originX: 'left',
                originY: 'top',
            });
            this.canvas.setBackgroundImage(img, this.canvas.renderAll.bind(this.canvas));
            this.backgroundImage = url;
        }, { crossOrigin: 'anonymous' });
    },

    onSelectionChange(obj) {
        document.getElementById('noSelectionPanel').classList.add('hidden');
        document.getElementById('commonPropertiesPanel').classList.remove('hidden');

        // Hide all type-specific panels first
        document.getElementById('textPropertiesPanel').classList.add('hidden');
        document.getElementById('imagePropertiesPanel').classList.add('hidden');
        document.getElementById('shapePropertiesPanel').classList.add('hidden');

        // Show appropriate panel
        if (obj.elementType === 'text' || obj.type === 'i-text') {
            document.getElementById('textPropertiesPanel').classList.remove('hidden');
            this.updateTextPropertiesUI(obj);
        } else if (obj.elementType === 'image') {
            document.getElementById('imagePropertiesPanel').classList.remove('hidden');
            this.updateImagePropertiesUI(obj);
        } else if (obj.elementType === 'shape') {
            document.getElementById('shapePropertiesPanel').classList.remove('hidden');
            this.updateShapePropertiesUI(obj);
        } else if (obj.type === 'image') {
            document.getElementById('imagePropertiesPanel').classList.remove('hidden');
            this.updateImagePropertiesUI(obj);
        }

        this.updateCommonPropertiesUI(obj);
    },

    onSelectionClear() {
        document.getElementById('noSelectionPanel').classList.remove('hidden');
        document.getElementById('commonPropertiesPanel').classList.add('hidden');
        document.getElementById('textPropertiesPanel').classList.add('hidden');
        document.getElementById('imagePropertiesPanel').classList.add('hidden');
        document.getElementById('shapePropertiesPanel').classList.add('hidden');
    },

    updateTextPropertiesUI(obj) {
        document.getElementById('propFontFamily').value = obj.fontFamily || 'Arial';
        document.getElementById('propFontSize').value = Math.round(obj.fontSize || 24);
        document.getElementById('propFontWeight').value = obj.fontWeight || '400';
        document.getElementById('propTextColor').value = obj.fill || '#ffffff';
        document.getElementById('propLetterSpacing').value = (obj.charSpacing || 0) / 10;
        document.getElementById('propTextShadow').checked = !!obj.shadow;
        document.getElementById('shadowControls').classList.toggle('hidden', !obj.shadow);
    },

    updateImagePropertiesUI(obj) {
        const width = obj.width * (obj.scaleX || 1);
        const height = obj.height * (obj.scaleY || 1);
        document.getElementById('propImageWidth').value = Math.round(width);
        document.getElementById('propImageHeight').value = Math.round(height);
    },

    updateShapePropertiesUI(obj) {
        document.getElementById('propShapeFill').value = obj.fill || '#6366f1';
        document.getElementById('propShapeStroke').value = obj.stroke || '#ffffff';
        document.getElementById('propStrokeWidth').value = obj.strokeWidth || 0;
    },

    updateCommonPropertiesUI(obj) {
        document.getElementById('propPosX').value = Math.round(obj.left || 0);
        document.getElementById('propPosY').value = Math.round(obj.top || 0);
        document.getElementById('propRotation').value = Math.round(obj.angle || 0);
        document.getElementById('rotationValue').textContent = Math.round(obj.angle || 0);
        document.getElementById('propOpacity').value = Math.round((obj.opacity || 1) * 100);
        document.getElementById('opacityValue').textContent = Math.round((obj.opacity || 1) * 100);
    },

    updatePropertiesPanel() {
        const obj = this.canvas.getActiveObject();
        if (obj) this.onSelectionChange(obj);
    },

    updateTextProperty(prop, value) {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        obj.set(prop, value);
        this.canvas.renderAll();
        this.saveHistory();
    },

    setTextColor(color) {
        document.getElementById('propTextColor').value = color;
        this.updateTextProperty('fill', color);
    },

    toggleTextShadow(enabled) {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;

        document.getElementById('shadowControls').classList.toggle('hidden', !enabled);

        if (enabled) {
            obj.set('shadow', new fabric.Shadow({
                color: 'rgba(0,0,0,0.5)',
                blur: 5,
                offsetX: 2,
                offsetY: 2
            }));
        } else {
            obj.set('shadow', null);
        }
        this.canvas.renderAll();
        this.saveHistory();
    },

    updateShadow() {
        const obj = this.canvas.getActiveObject();
        if (!obj || !obj.shadow) return;

        obj.shadow.offsetX = parseInt(document.getElementById('propShadowX').value) || 0;
        obj.shadow.offsetY = parseInt(document.getElementById('propShadowY').value) || 0;
        obj.shadow.blur = parseInt(document.getElementById('propShadowBlur').value) || 0;
        this.canvas.renderAll();
    },

    updateImageSize() {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;

        const newWidth = parseInt(document.getElementById('propImageWidth').value);
        const newHeight = parseInt(document.getElementById('propImageHeight').value);
        const lockRatio = document.getElementById('propLockRatio').checked;

        if (lockRatio) {
            const ratio = obj.width / obj.height;
            obj.scaleX = newWidth / obj.width;
            obj.scaleY = newWidth / obj.width;
        } else {
            obj.scaleX = newWidth / obj.width;
            obj.scaleY = newHeight / obj.height;
        }

        this.canvas.renderAll();
        this.saveHistory();
    },

    updateShapeProperty(prop, value) {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        obj.set(prop, value);
        this.canvas.renderAll();
        this.saveHistory();
    },

    updatePosition() {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        obj.set({
            left: parseInt(document.getElementById('propPosX').value) || 0,
            top: parseInt(document.getElementById('propPosY').value) || 0,
        });
        this.canvas.renderAll();
        this.saveHistory();
    },

    updateRotation(value) {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        document.getElementById('rotationValue').textContent = value;
        obj.set('angle', parseInt(value));
        this.canvas.renderAll();
        this.saveHistory();
    },

    updateOpacity(value) {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        document.getElementById('opacityValue').textContent = value;
        obj.set('opacity', value / 100);
        this.canvas.renderAll();
        this.saveHistory();
    },

    delete() {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        this.canvas.remove(obj);
        this.saveHistory();
    },

    duplicate() {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;

        obj.clone((cloned) => {
            cloned.set({ left: obj.left + 20, top: obj.top + 20 });
            cloned.placeholder = obj.placeholder;
            cloned.elementType = obj.elementType;
            this.canvas.add(cloned);
            this.canvas.setActiveObject(cloned);
            this.saveHistory();
        });
    },

    bringForward() {
        const obj = this.canvas.getActiveObject();
        if (obj) { this.canvas.bringForward(obj); this.saveHistory(); this.updateLayersList(); }
    },

    sendBackward() {
        const obj = this.canvas.getActiveObject();
        if (obj) { this.canvas.sendBackwards(obj); this.saveHistory(); this.updateLayersList(); }
    },

    // Clipboard
    clipboard: null,
    copy() {
        const obj = this.canvas.getActiveObject();
        if (obj) obj.clone((cloned) => { this.clipboard = cloned; });
    },
    paste() {
        if (!this.clipboard) return;
        this.clipboard.clone((cloned) => {
            cloned.set({ left: cloned.left + 20, top: cloned.top + 20 });
            this.canvas.add(cloned);
            this.canvas.setActiveObject(cloned);
            this.saveHistory();
        });
    },

    // History (Undo/Redo)
    saveHistory() {
        const json = this.canvas.toJSON(['placeholder', 'elementType', 'shapeType', 'placeholderWidth', 'placeholderHeight']);
        this.history = this.history.slice(0, this.historyIndex + 1);
        this.history.push(JSON.stringify(json));
        this.historyIndex++;
        if (this.history.length > 50) { this.history.shift(); this.historyIndex--; }
    },

    undo() {
        if (this.historyIndex > 0) {
            this.historyIndex--;
            this.loadFromHistory();
        }
    },

    redo() {
        if (this.historyIndex < this.history.length - 1) {
            this.historyIndex++;
            this.loadFromHistory();
        }
    },

    loadFromHistory() {
        const json = JSON.parse(this.history[this.historyIndex]);
        this.canvas.loadFromJSON(json, () => {
            this.canvas.renderAll();
            this.updateLayersList();
        });
    },

    // Zoom
    zoomIn() { this.setZoom(Math.min(this.zoom + 0.1, 2)); },
    zoomOut() { this.setZoom(Math.max(this.zoom - 0.1, 0.25)); },
    zoomReset() { this.setZoom(1); },

    setZoom(level) {
        this.zoom = level;
        const effectiveZoom = this.displayScale * level;
        this.canvas.setZoom(effectiveZoom);
        this.canvas.setWidth(this.canvasWidth * effectiveZoom);
        this.canvas.setHeight(this.canvasHeight * effectiveZoom);
        document.getElementById('zoomValue').textContent = Math.round(level * 100) + '%';
    },

    changeCanvasSize(size) {
        const [width, height] = size.split('x').map(Number);
        this.canvasWidth = width;
        this.canvasHeight = height;
        const effectiveZoom = this.displayScale * this.zoom;
        this.canvas.setWidth(width * effectiveZoom);
        this.canvas.setHeight(height * effectiveZoom);
        document.getElementById('canvasSizeDisplay').textContent = `${width} x ${height}`;

        // Rescale background if exists
        if (this.canvas.backgroundImage) {
            const img = this.canvas.backgroundImage;
            img.scaleX = this.canvasWidth / img.width;
            img.scaleY = this.canvasHeight / img.height;
        }
        this.canvas.renderAll();
    },

    // Layers
    updateLayersList() {
        const objects = this.canvas.getObjects();
        const list = document.getElementById('layersList');
        document.getElementById('layerCount').textContent = objects.length;

        list.innerHTML = objects.reverse().map((obj, i) => {
            const name = obj.placeholder ? obj.placeholder.replace(/_/g, ' ') : (obj.shapeType || obj.type || 'Element');
            const isSelected = obj === this.canvas.getActiveObject();
            return `
                <div class="layer-item ${isSelected ? 'selected' : ''}" onclick="editor.selectLayer(${objects.length - 1 - i})">
                    <div class="layer-thumb">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${obj.elementType === 'text' ? 'M4 6h16M4 12h16M4 18h7' : 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'}"/></svg>
                    </div>
                    <div class="layer-info">
                        <div class="layer-name">${name}</div>
                        <div class="layer-type">${obj.elementType || obj.type}</div>
                    </div>
                </div>
            `;
        }).join('');
    },

    selectLayer(index) {
        const obj = this.canvas.getObjects()[index];
        if (obj) {
            this.canvas.setActiveObject(obj);
            this.canvas.renderAll();
        }
    },

    // Preview
    preview() {
        // Export at full resolution by calculating proper multiplier
        const currentZoom = this.canvas.getZoom();
        const multiplier = 1 / currentZoom; // This gives us 1:1 pixel ratio

        const dataUrl = this.canvas.toDataURL({
            format: 'png',
            quality: 1,
            multiplier: multiplier,
        });
        document.getElementById('previewImage').src = dataUrl;
        document.getElementById('downloadPreviewBtn').href = dataUrl;
        document.getElementById('previewModal').classList.remove('hidden');
    },

    closePreview() {
        document.getElementById('previewModal').classList.add('hidden');
    },

    // Save
    save() {
        // Prepare layout JSON - save as percentage of full canvas size for portability
        const objects = this.canvas.getObjects();
        const layoutJson = objects.map(obj => {
            const base = {
                type: obj.elementType || obj.type,
                placeholder: obj.placeholder || null,
                x: (obj.left / this.canvasWidth) * 100,
                y: (obj.top / this.canvasHeight) * 100,
                rotation: obj.angle || 0,
                opacity: (obj.opacity || 1) * 100,
                zIndex: objects.indexOf(obj),
            };

            if (obj.elementType === 'text' || obj.type === 'i-text') {
                return {
                    ...base,
                    fontSize: obj.fontSize,
                    fontFamily: obj.fontFamily,
                    fontWeight: obj.fontWeight,
                    color: obj.fill,
                    textAlign: obj.textAlign,
                    charSpacing: obj.charSpacing,
                    shadow: obj.shadow ? {
                        blur: obj.shadow.blur,
                        offsetX: obj.shadow.offsetX,
                        offsetY: obj.shadow.offsetY,
                    } : null,
                };
            } else if (obj.elementType === 'image') {
                return {
                    ...base,
                    width: (obj.placeholderWidth || obj.width || 150) * (obj.scaleX || 1),
                    height: (obj.placeholderHeight || obj.height || 150) * (obj.scaleY || 1),
                };
            } else if (obj.elementType === 'uploadedImage') {
                return {
                    ...base,
                    width: (obj.placeholderWidth || obj.width || 150) * (obj.scaleX || 1),
                    height: (obj.placeholderHeight || obj.height || 150) * (obj.scaleY || 1),
                };
            } else if (obj.elementType === 'shape') {
                return {
                    ...base,
                    shapeType: obj.shapeType,
                    fill: obj.fill,
                    stroke: obj.stroke,
                    strokeWidth: obj.strokeWidth,
                    width: (obj.width || 150) * (obj.scaleX || 1),
                    height: (obj.height || 100) * (obj.scaleY || 1),
                };
            } else if (obj.type === 'image') {
                // Regular Fabric image (uploaded)
                return {
                    ...base,
                    type: 'uploadedImage',
                    width: obj.width * (obj.scaleX || 1),
                    height: obj.height * (obj.scaleY || 1),
                };
            }
            return base;
        });

        document.getElementById('formName').value = document.getElementById('templateName').value;
        document.getElementById('formLayoutJson').value = JSON.stringify(layoutJson);
        document.getElementById('formCanvasWidth').value = this.canvasWidth;
        document.getElementById('formCanvasHeight').value = this.canvasHeight;

        // Show saving indicator
        const saveBtn = document.querySelector('[onclick="editor.save()"]');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Saving...';
        }

        // Submit form
        document.getElementById('saveForm').submit();
    },

    loadTemplate(layoutJson) {
        if (!layoutJson || !Array.isArray(layoutJson)) return;

        layoutJson.forEach(item => {
            // Convert percentage back to canvas coordinates
            const x = (item.x / 100) * this.canvasWidth;
            const y = (item.y / 100) * this.canvasHeight;

            if (item.type === 'text' || item.type === 'i-text') {
                const displayText = item.placeholder ? '{{' + item.placeholder + '}}' : 'Text';
                const text = new fabric.IText(displayText, {
                    left: x,
                    top: y,
                    fontSize: item.fontSize || 24,
                    fontFamily: item.fontFamily || 'Arial',
                    fontWeight: item.fontWeight || '400',
                    fill: item.color || '#ffffff',
                    angle: item.rotation || 0,
                    opacity: (item.opacity || 100) / 100,
                    textAlign: item.textAlign || 'center',
                    charSpacing: item.charSpacing || 0,
                    originX: 'center',
                    originY: 'center',
                });
                if (item.shadow) {
                    text.shadow = new fabric.Shadow({
                        blur: item.shadow.blur || 5,
                        offsetX: item.shadow.offsetX || 2,
                        offsetY: item.shadow.offsetY || 2,
                        color: 'rgba(0,0,0,0.5)',
                    });
                }
                text.placeholder = item.placeholder;
                text.elementType = 'text';
                this.canvas.add(text);
            } else if (item.type === 'image') {
                this.addImagePlaceholderAtPos(item, x, y);
            } else if (item.type === 'uploadedImage') {
                // Show placeholder for uploaded images (they'd need to be re-uploaded)
                this.addUploadedImagePlaceholder(item, x, y);
            } else if (item.type === 'shape') {
                this.addShapeFromData(item, x, y);
            }
        });

        this.canvas.renderAll();
    },

    addImagePlaceholderAtPos(item, x, y) {
        const placeholder = item.placeholder || 'image';
        const width = item.width || 150;
        const height = item.height || 150;

        const group = new fabric.Group([], {
            left: x,
            top: y,
            angle: item.rotation || 0,
            opacity: (item.opacity || 100) / 100,
            originX: 'center',
            originY: 'center',
        });

        const rect = new fabric.Rect({
            width: width,
            height: height,
            fill: 'rgba(99, 102, 241, 0.3)',
            stroke: '#6366f1',
            strokeWidth: 2,
            strokeDashArray: [5, 5],
            rx: 8,
            ry: 8,
            originX: 'center',
            originY: 'center',
        });

        const iconText = new fabric.Text('🖼️', {
            fontSize: 32,
            originX: 'center',
            originY: 'center',
            top: -15,
        });

        const label = new fabric.Text(placeholder.replace(/_/g, ' '), {
            fontSize: 12,
            fill: '#ffffff',
            fontFamily: 'Arial',
            originX: 'center',
            originY: 'center',
            top: 30,
        });

        group.addWithUpdate(rect);
        group.addWithUpdate(iconText);
        group.addWithUpdate(label);

        group.placeholder = placeholder;
        group.elementType = 'image';
        group.placeholderWidth = width;
        group.placeholderHeight = height;

        this.canvas.add(group);
    },

    addUploadedImagePlaceholder(item, x, y) {
        const width = item.width || 150;
        const height = item.height || 150;

        const group = new fabric.Group([], {
            left: x,
            top: y,
            angle: item.rotation || 0,
            opacity: (item.opacity || 100) / 100,
            originX: 'center',
            originY: 'center',
        });

        const rect = new fabric.Rect({
            width: width,
            height: height,
            fill: 'rgba(236, 72, 153, 0.3)',
            stroke: '#ec4899',
            strokeWidth: 2,
            strokeDashArray: [5, 5],
            rx: 8,
            ry: 8,
            originX: 'center',
            originY: 'center',
        });

        const iconText = new fabric.Text('📷', {
            fontSize: 32,
            originX: 'center',
            originY: 'center',
            top: -15,
        });

        const label = new fabric.Text('Re-upload Image', {
            fontSize: 10,
            fill: '#ffffff',
            fontFamily: 'Arial',
            originX: 'center',
            originY: 'center',
            top: 30,
        });

        group.addWithUpdate(rect);
        group.addWithUpdate(iconText);
        group.addWithUpdate(label);

        group.elementType = 'uploadedImage';
        group.placeholderWidth = width;
        group.placeholderHeight = height;

        this.canvas.add(group);
    },

    addShapeFromData(item, x, y) {
        let obj;
        const commonProps = {
            left: x,
            top: y,
            fill: item.fill || 'rgba(99, 102, 241, 0.5)',
            stroke: item.stroke || '#6366f1',
            strokeWidth: item.strokeWidth || 2,
            angle: item.rotation || 0,
            opacity: (item.opacity || 100) / 100,
            originX: 'center',
            originY: 'center',
        };

        if (item.shapeType === 'rect') {
            obj = new fabric.Rect({ ...commonProps, width: item.width || 150, height: item.height || 100, rx: 8, ry: 8 });
        } else if (item.shapeType === 'circle') {
            obj = new fabric.Circle({ ...commonProps, radius: (item.width || 120) / 2 });
        } else if (item.shapeType === 'line') {
            obj = new fabric.Line([0, 0, item.width || 150, 0], { ...commonProps, strokeWidth: item.strokeWidth || 4 });
        }

        if (obj) {
            obj.elementType = 'shape';
            obj.shapeType = item.shapeType;
            this.canvas.add(obj);
        }
    },
};

// Tab switching
function switchTab(tab) {
    document.querySelectorAll('.sidebar-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
    document.getElementById(`tab-${tab}`).classList.remove('hidden');
}

// Initialize editor on load
document.addEventListener('DOMContentLoaded', () => editor.init());
</script>
@endpush
