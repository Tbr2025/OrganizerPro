@extends('backend.layouts.app')

@section('title', ($template ? 'Edit' : 'Create') . ' Template | ' . $tournament->name)

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&family=Open+Sans:wght@300;400;600;700&family=Montserrat:wght@300;400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800;900&family=Oswald:wght@300;400;500;600;700&family=Bebas+Neue&family=Anton&family=Bangers&display=swap" rel="stylesheet">
<style>
    .editor-container { height: calc(100vh - 64px); display: flex; flex-direction: column; background: #0f0f1a; overflow: hidden; }
    .editor-header { height: 56px; background: #1a1a2e; border-bottom: 1px solid #2d2d44; display: flex; align-items: center; padding: 0 16px; flex-shrink: 0; }
    .editor-body { flex: 1; display: flex; overflow: hidden; }
    .editor-sidebar { width: 280px; background: #1a1a2e; border-right: 1px solid #2d2d44; display: flex; flex-direction: column; flex-shrink: 0; overflow: hidden; }
    .editor-canvas-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
    .editor-canvas-wrapper { flex: 1; overflow: auto; display: flex; align-items: center; justify-content: center; padding: 20px; background: #12121f; }
    .editor-properties { width: 300px; background: #1a1a2e; border-left: 1px solid #2d2d44; flex-shrink: 0; overflow-y: auto; }
    .editor-footer { height: 40px; background: #1a1a2e; border-top: 1px solid #2d2d44; display: flex; align-items: center; padding: 0 16px; flex-shrink: 0; }

    .sidebar-tabs { display: flex; border-bottom: 1px solid #2d2d44; flex-shrink: 0; }
    .sidebar-tab { flex: 1; padding: 12px 8px; text-align: center; font-size: 11px; font-weight: 500; color: #8b8ba7; cursor: pointer; border-bottom: 2px solid transparent; }
    .sidebar-tab:hover { color: #fff; background: rgba(99, 102, 241, 0.1); }
    .sidebar-tab.active { color: #818cf8; border-bottom-color: #818cf8; }
    .sidebar-tab svg { width: 20px; height: 20px; margin: 0 auto 4px; display: block; }
    .sidebar-content { flex: 1; overflow-y: auto; padding: 12px; }
    .sidebar-section { margin-bottom: 16px; }
    .sidebar-section-title { font-size: 11px; font-weight: 600; color: #8b8ba7; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }

    .draggable-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #252538; border-radius: 8px; cursor: grab; margin-bottom: 6px; border: 1px solid transparent; }
    .draggable-item:hover { background: #2d2d4a; border-color: #4f46e5; }
    .draggable-item .icon { width: 36px; height: 36px; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .draggable-item .icon.text { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
    .draggable-item .icon.image { background: linear-gradient(135deg, #ec4899, #f43f5e); }
    .draggable-item .icon svg { width: 18px; height: 18px; color: white; }
    .draggable-item .info { flex: 1; min-width: 0; }
    .draggable-item .name { font-size: 13px; font-weight: 500; color: #e2e2e2; }
    .draggable-item .type { font-size: 10px; color: #8b8ba7; }

    #canvas-container { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); border-radius: 4px; overflow: hidden; }

    .prop-section { padding: 16px; border-bottom: 1px solid #2d2d44; }
    .prop-section-title { font-size: 11px; font-weight: 600; color: #8b8ba7; text-transform: uppercase; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
    .prop-group { margin-bottom: 12px; }
    .prop-label { font-size: 11px; color: #8b8ba7; margin-bottom: 4px; display: block; }
    .prop-input { width: 100%; background: #252538; border: 1px solid #3d3d5c; border-radius: 6px; padding: 8px 10px; font-size: 13px; color: #e2e2e2; outline: none; }
    .prop-input:focus { border-color: #6366f1; }
    .prop-input-row { display: flex; gap: 8px; }
    .prop-input-row .prop-group { flex: 1; margin-bottom: 0; }
    .prop-btn { padding: 8px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 6px; }
    .prop-btn-primary { background: #6366f1; color: white; }
    .prop-btn-primary:hover { background: #4f46e5; }
    .prop-btn-secondary { background: #252538; color: #e2e2e2; border: 1px solid #3d3d5c; }
    .prop-btn-secondary:hover { background: #2d2d4a; }
    .prop-btn-danger { background: #ef4444; color: white; }
    .prop-btn-success { background: #10b981; color: white; }
    .prop-btn-success:hover { background: #059669; }

    .color-picker-wrapper { display: flex; align-items: center; gap: 8px; }
    .color-preview { width: 36px; height: 36px; border-radius: 6px; border: 2px solid #3d3d5c; cursor: pointer; }
    .color-presets { display: flex; gap: 4px; flex-wrap: wrap; }
    .color-preset { width: 24px; height: 24px; border-radius: 4px; cursor: pointer; border: 2px solid transparent; }
    .color-preset:hover { transform: scale(1.1); }

    .prop-slider { width: 100%; height: 4px; border-radius: 2px; background: #3d3d5c; appearance: none; cursor: pointer; }
    .prop-slider::-webkit-slider-thumb { appearance: none; width: 14px; height: 14px; border-radius: 50%; background: #6366f1; cursor: pointer; }

    .toolbar-group { display: flex; align-items: center; gap: 4px; padding: 0 12px; border-right: 1px solid #2d2d44; }
    .toolbar-btn { width: 36px; height: 36px; border-radius: 6px; display: flex; align-items: center; justify-content: center; background: transparent; border: none; color: #8b8ba7; cursor: pointer; }
    .toolbar-btn:hover { background: #252538; color: #fff; }
    .toolbar-btn svg { width: 18px; height: 18px; }

    .zoom-controls { display: flex; align-items: center; gap: 8px; }
    .zoom-btn { width: 28px; height: 28px; border-radius: 4px; display: flex; align-items: center; justify-content: center; background: #252538; border: none; color: #8b8ba7; cursor: pointer; }
    .zoom-btn:hover { background: #3d3d5c; color: #fff; }
    .zoom-value { font-size: 12px; color: #8b8ba7; min-width: 50px; text-align: center; }

    .layer-item { display: flex; align-items: center; gap: 8px; padding: 8px 10px; background: #252538; border-radius: 6px; margin-bottom: 4px; cursor: pointer; }
    .layer-item:hover { background: #2d2d4a; }
    .layer-item.selected { background: #3730a3; border: 1px solid #6366f1; }
    .layer-thumb { width: 32px; height: 32px; border-radius: 4px; background: #1a1a2e; display: flex; align-items: center; justify-content: center; }
    .layer-thumb svg { width: 16px; height: 16px; color: #8b8ba7; }
    .layer-info { flex: 1; min-width: 0; }
    .layer-name { font-size: 12px; color: #e2e2e2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .layer-type { font-size: 10px; color: #8b8ba7; }

    .no-selection { padding: 40px 20px; text-align: center; color: #8b8ba7; }
    .no-selection svg { width: 48px; height: 48px; margin: 0 auto 12px; opacity: 0.5; }

    .align-buttons { display: flex; gap: 4px; }
    .align-btn { flex: 1; height: 32px; border-radius: 4px; display: flex; align-items: center; justify-content: center; background: #252538; border: 1px solid #3d3d5c; color: #8b8ba7; cursor: pointer; }
    .align-btn:hover { background: #3d3d5c; color: #fff; }
    .align-btn.active { background: #6366f1; border-color: #6366f1; color: #fff; }
    .align-btn svg { width: 16px; height: 16px; }

    .bg-upload-area { border: 2px dashed #3d3d5c; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s; }
    .bg-upload-area:hover { border-color: #6366f1; background: rgba(99, 102, 241, 0.1); }
    .bg-upload-area.has-bg { border-style: solid; border-color: #10b981; }

    .preview-modal { position: fixed; inset: 0; z-index: 100; background: rgba(0,0,0,0.9); display: none; align-items: center; justify-content: center; flex-direction: column; padding: 20px; }
    .preview-modal.show { display: flex; }
    .preview-modal img { max-width: 90%; max-height: 80vh; border-radius: 8px; box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
    .preview-modal .close-btn { position: absolute; top: 20px; right: 20px; width: 40px; height: 40px; border-radius: 50%; background: #fff; border: none; cursor: pointer; font-size: 20px; }
    .preview-modal .actions { margin-top: 20px; display: flex; gap: 10px; }
</style>
@endpush

@section('admin-content')
<div class="editor-container" id="editorApp">
    {{-- Header --}}
    <div class="editor-header">
        <div class="flex items-center gap-4 flex-1">
            <a href="{{ route('admin.tournaments.templates.index', $tournament) }}" class="toolbar-btn" title="Back">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <input type="text" id="templateName" value="{{ $template?->name ?? 'Untitled Template' }}" class="bg-transparent border-none text-white font-medium text-sm focus:outline-none focus:bg-gray-800 px-2 py-1 rounded" style="min-width: 200px;">
            <span class="text-xs text-gray-500 bg-gray-800 px-2 py-1 rounded">{{ \App\Models\TournamentTemplate::getTypeDisplay($type) }}</span>
        </div>

        <div class="flex items-center">
            <div class="toolbar-group">
                <button class="toolbar-btn" onclick="editor.undo()" title="Undo"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg></button>
                <button class="toolbar-btn" onclick="editor.redo()" title="Redo"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"/></svg></button>
            </div>
            <div class="toolbar-group">
                <button class="toolbar-btn" onclick="editor.duplicate()" title="Duplicate"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></button>
                <button class="toolbar-btn" onclick="editor.deleteSelected()" title="Delete"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
            </div>
            <div class="toolbar-group">
                <button class="toolbar-btn" onclick="editor.bringForward()" title="Bring Forward"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 11l7-7 7 7M5 19l7-7 7 7"/></svg></button>
                <button class="toolbar-btn" onclick="editor.sendBackward()" title="Send Backward"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 13l-7 7-7-7m14-8l-7 7-7-7"/></svg></button>
            </div>
        </div>

        <div class="flex items-center gap-3 flex-1 justify-end">
            <button onclick="editor.showPreview()" class="prop-btn prop-btn-secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Preview
            </button>
            <button onclick="editor.save()" id="saveBtn" class="prop-btn prop-btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Save Template
            </button>
        </div>
    </div>

    {{-- Body --}}
    <div class="editor-body">
        {{-- Left Sidebar --}}
        <div class="editor-sidebar">
            <div class="sidebar-tabs">
                <div class="sidebar-tab active" data-tab="elements" onclick="switchTab('elements')">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6z"/></svg>
                    Elements
                </div>
                <div class="sidebar-tab" data-tab="background" onclick="switchTab('background')">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Background
                </div>
                <div class="sidebar-tab" data-tab="layers" onclick="switchTab('layers')">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    Layers
                </div>
            </div>

            <div class="sidebar-content">
                {{-- Elements Tab --}}
                <div id="tab-elements" class="tab-content">
                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Text Placeholders</div>
                        @foreach($placeholders as $placeholder)
                            @php $isImage = in_array($placeholder, ['player_image', 'team_logo', 'tournament_logo', 'team_a_logo', 'team_b_logo', 'team_a_captain_image', 'team_b_captain_image', 'man_of_the_match_image', 'qr_code']); @endphp
                            @if(!$isImage)
                            <div class="draggable-item" draggable="true" data-type="text" data-placeholder="{{ $placeholder }}">
                                <div class="icon text"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg></div>
                                <div class="info">
                                    <div class="name">{{ str_replace('_', ' ', ucwords($placeholder, '_')) }}</div>
                                    <div class="type">Text</div>
                                </div>
                            </div>
                            @endif
                        @endforeach
                    </div>

                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Image Placeholders</div>
                        @foreach($placeholders as $placeholder)
                            @php $isImage = in_array($placeholder, ['player_image', 'team_logo', 'tournament_logo', 'team_a_logo', 'team_b_logo', 'team_a_captain_image', 'team_b_captain_image', 'man_of_the_match_image', 'qr_code']); @endphp
                            @if($isImage)
                            <div class="draggable-item" draggable="true" data-type="image" data-placeholder="{{ $placeholder }}">
                                <div class="icon image"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                                <div class="info">
                                    <div class="name">{{ str_replace('_', ' ', ucwords($placeholder, '_')) }}</div>
                                    <div class="type">Image</div>
                                </div>
                            </div>
                            @endif
                        @endforeach
                    </div>

                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Shapes</div>
                        <div class="draggable-item" draggable="true" data-type="shape" data-shape="rect">
                            <div class="icon" style="background: linear-gradient(135deg, #10b981, #059669);"><svg fill="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/></svg></div>
                            <div class="info"><div class="name">Rectangle</div><div class="type">Shape</div></div>
                        </div>
                        <div class="draggable-item" draggable="true" data-type="shape" data-shape="circle">
                            <div class="icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);"><svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg></div>
                            <div class="info"><div class="name">Circle</div><div class="type">Shape</div></div>
                        </div>
                        <div class="draggable-item" draggable="true" data-type="shape" data-shape="triangle">
                            <div class="icon" style="background: linear-gradient(135deg, #ec4899, #be185d);"><svg fill="currentColor" viewBox="0 0 24 24"><polygon points="12,2 22,22 2,22"/></svg></div>
                            <div class="info"><div class="name">Triangle</div><div class="type">Shape</div></div>
                        </div>
                        <div class="draggable-item" draggable="true" data-type="shape" data-shape="line">
                            <div class="icon" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);"><svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/></svg></div>
                            <div class="info"><div class="name">Line</div><div class="type">Shape</div></div>
                        </div>
                        <div class="draggable-item" draggable="true" data-type="shape" data-shape="star">
                            <div class="icon" style="background: linear-gradient(135deg, #eab308, #ca8a04);"><svg fill="currentColor" viewBox="0 0 24 24"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/></svg></div>
                            <div class="info"><div class="name">Star</div><div class="type">Shape</div></div>
                        </div>
                        <div class="draggable-item" draggable="true" data-type="shape" data-shape="diamond">
                            <div class="icon" style="background: linear-gradient(135deg, #06b6d4, #0e7490);"><svg fill="currentColor" viewBox="0 0 24 24"><polygon points="12,2 22,12 12,22 2,12"/></svg></div>
                            <div class="info"><div class="name">Diamond</div><div class="type">Shape</div></div>
                        </div>
                    </div>

                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Image Layers</div>
                        <label class="bg-upload-area block" id="overlayUploadArea" style="padding: 12px; cursor: pointer;">
                            <input type="file" id="overlayImageInput" accept="image/png,image/jpeg,image/svg+xml" class="hidden" onchange="editor.uploadOverlayImage(this)">
                            <svg class="w-8 h-8 mx-auto text-gray-500 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <p class="text-xs text-gray-400 text-center">Upload PNG, SVG, JPG</p>
                        </label>
                        <div id="overlayImagesList" class="mt-2 space-y-1">
                            {{-- Populated dynamically --}}
                        </div>
                    </div>
                </div>

                {{-- Background Tab --}}
                <div id="tab-background" class="tab-content hidden">
                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Background Image</div>
                        <label class="bg-upload-area block" id="bgUploadArea">
                            <input type="file" id="bgImageInput" accept="image/*" class="hidden" onchange="editor.uploadBackground(this)">
                            <svg class="w-10 h-10 mx-auto text-gray-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <p class="text-sm text-gray-400">Click to upload background</p>
                            <p class="text-xs text-gray-500 mt-1">PNG, JPG (1080x1080 recommended)</p>
                        </label>
                        <div id="bgPreview" class="mt-3 hidden">
                            <img id="bgPreviewImg" class="w-full rounded-lg" alt="Background">
                            <button onclick="editor.removeBackground()" class="prop-btn prop-btn-danger w-full mt-2">Remove Background</button>
                        </div>
                    </div>

                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Background Color</div>
                        <input type="color" id="bgColorPicker" value="#1a1a2e" class="w-full h-10 rounded cursor-pointer" onchange="editor.setBackgroundColor(this.value)">
                    </div>
                </div>

                {{-- Layers Tab --}}
                <div id="tab-layers" class="tab-content hidden">
                    <div class="sidebar-section">
                        <div class="sidebar-section-title flex justify-between items-center">
                            <span>Layers</span>
                            <span id="layerCount" class="text-xs bg-gray-700 px-2 py-0.5 rounded">0</span>
                        </div>
                        <div id="layersList"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Canvas --}}
        <div class="editor-canvas-area">
            <div class="editor-canvas-wrapper" id="canvasWrapper">
                <div id="canvas-container">
                    <canvas id="fabricCanvas"></canvas>
                </div>
            </div>
        </div>

        {{-- Properties Panel --}}
        <div class="editor-properties" id="propertiesPanel">
            <div id="noSelectionPanel" class="no-selection">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                <p>Select an element</p>
                <small>Click on canvas elements to edit</small>
            </div>

            {{-- Text Properties --}}
            <div id="textPropertiesPanel" class="hidden">
                <div class="prop-section">
                    <div class="prop-section-title">Text Properties</div>
                    <div class="prop-group">
                        <label class="prop-label">Font Family</label>
                        <select id="propFontFamily" class="prop-input" onchange="editor.updateText('fontFamily', this.value)">
                            <option value="Arial">Arial</option>
                            <option value="Roboto">Roboto</option>
                            <option value="Open Sans">Open Sans</option>
                            <option value="Montserrat">Montserrat</option>
                            <option value="Poppins">Poppins</option>
                            <option value="Oswald">Oswald</option>
                            <option value="Bebas Neue">Bebas Neue</option>
                            <option value="Anton">Anton</option>
                            <option value="Impact">Impact</option>
                        </select>
                    </div>
                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Size</label>
                            <input type="number" id="propFontSize" class="prop-input" min="8" max="200" onchange="editor.updateText('fontSize', parseInt(this.value))">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Weight</label>
                            <select id="propFontWeight" class="prop-input" onchange="editor.updateText('fontWeight', this.value)">
                                <option value="400">Regular</option>
                                <option value="500">Medium</option>
                                <option value="600">SemiBold</option>
                                <option value="700">Bold</option>
                                <option value="900">Black</option>
                            </select>
                        </div>
                    </div>
                    <div class="prop-group">
                        <label class="prop-label">Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="propTextColor" class="color-preview" onchange="editor.updateText('fill', this.value)">
                            <div class="color-presets">
                                <div class="color-preset" style="background:#fff" onclick="editor.updateText('fill','#ffffff')"></div>
                                <div class="color-preset" style="background:#000" onclick="editor.updateText('fill','#000000')"></div>
                                <div class="color-preset" style="background:#FFD700" onclick="editor.updateText('fill','#FFD700')"></div>
                                <div class="color-preset" style="background:#EF4444" onclick="editor.updateText('fill','#EF4444')"></div>
                                <div class="color-preset" style="background:#3B82F6" onclick="editor.updateText('fill','#3B82F6')"></div>
                            </div>
                        </div>
                    </div>
                    <div class="prop-group">
                        <label class="prop-label">Alignment</label>
                        <div class="align-buttons">
                            <button class="align-btn" onclick="editor.updateText('textAlign','left')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h10M4 18h14"/></svg></button>
                            <button class="align-btn" onclick="editor.updateText('textAlign','center')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M7 12h10M5 18h14"/></svg></button>
                            <button class="align-btn" onclick="editor.updateText('textAlign','right')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M10 12h10M6 18h14"/></svg></button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Shape Properties --}}
            <div id="shapePropertiesPanel" class="hidden">
                <div class="prop-section">
                    <div class="prop-section-title">Shape Properties</div>
                    <div class="prop-group">
                        <label class="prop-label">Fill Type</label>
                        <select id="propFillType" class="prop-input" onchange="editor.updateShapeFillType(this.value)">
                            <option value="solid">Solid</option>
                            <option value="linear">Linear Gradient</option>
                            <option value="radial">Radial Gradient</option>
                        </select>
                    </div>
                    <div id="solidFillGroup" class="prop-group">
                        <label class="prop-label">Fill Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="propShapeFill" class="color-preview" value="#6366f1" onchange="editor.updateShapeFill(this.value)">
                            <div class="color-presets">
                                <div class="color-preset" style="background:#6366f1" onclick="editor.updateShapeFill('#6366f1')"></div>
                                <div class="color-preset" style="background:#ec4899" onclick="editor.updateShapeFill('#ec4899')"></div>
                                <div class="color-preset" style="background:#10b981" onclick="editor.updateShapeFill('#10b981')"></div>
                                <div class="color-preset" style="background:#f59e0b" onclick="editor.updateShapeFill('#f59e0b')"></div>
                                <div class="color-preset" style="background:#ef4444" onclick="editor.updateShapeFill('#ef4444')"></div>
                                <div class="color-preset" style="background:#3b82f6" onclick="editor.updateShapeFill('#3b82f6')"></div>
                                <div class="color-preset" style="background:#8b5cf6" onclick="editor.updateShapeFill('#8b5cf6')"></div>
                                <div class="color-preset" style="background:#06b6d4" onclick="editor.updateShapeFill('#06b6d4')"></div>
                                <div class="color-preset" style="background:#fff" onclick="editor.updateShapeFill('#ffffff')"></div>
                                <div class="color-preset" style="background:#000" onclick="editor.updateShapeFill('#000000')"></div>
                                <div class="color-preset" style="background:rgba(255,255,255,0.3);border:1px solid #555" onclick="editor.updateShapeFill('rgba(255,255,255,0.3)')"></div>
                                <div class="color-preset" style="background:rgba(0,0,0,0.5);border:1px solid #555" onclick="editor.updateShapeFill('rgba(0,0,0,0.5)')"></div>
                            </div>
                        </div>
                    </div>
                    <div id="gradientFillGroup" class="hidden">
                        <div class="prop-group">
                            <label class="prop-label">Color 1</label>
                            <input type="color" id="propGradientColor1" class="color-preview" value="#6366f1" onchange="editor.updateShapeGradient()">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Color 2</label>
                            <input type="color" id="propGradientColor2" class="color-preview" value="#ec4899" onchange="editor.updateShapeGradient()">
                        </div>
                        <div id="gradientAngleGroup" class="prop-group">
                            <label class="prop-label">Angle: <span id="gradientAngleValue">90</span>°</label>
                            <input type="range" id="propGradientAngle" class="prop-slider" min="0" max="360" value="90" oninput="document.getElementById('gradientAngleValue').textContent=this.value; editor.updateShapeGradient()">
                        </div>
                        <div class="color-presets" style="margin-top: 6px;">
                            <div class="color-preset" style="background:linear-gradient(135deg,#6366f1,#ec4899);width:36px" onclick="editor.applyGradientPreset('#6366f1','#ec4899')" title="Purple→Pink"></div>
                            <div class="color-preset" style="background:linear-gradient(135deg,#f59e0b,#ef4444);width:36px" onclick="editor.applyGradientPreset('#f59e0b','#ef4444')" title="Amber→Red"></div>
                            <div class="color-preset" style="background:linear-gradient(135deg,#10b981,#3b82f6);width:36px" onclick="editor.applyGradientPreset('#10b981','#3b82f6')" title="Green→Blue"></div>
                            <div class="color-preset" style="background:linear-gradient(135deg,#8b5cf6,#06b6d4);width:36px" onclick="editor.applyGradientPreset('#8b5cf6','#06b6d4')" title="Violet→Cyan"></div>
                            <div class="color-preset" style="background:linear-gradient(135deg,#000000,#6366f1);width:36px" onclick="editor.applyGradientPreset('#000000','#6366f1')" title="Black→Indigo"></div>
                        </div>
                    </div>
                    <div class="prop-group">
                        <label class="prop-label">Stroke Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="propShapeStroke" class="color-preview" value="#6366f1" onchange="editor.updateShapeStroke(this.value)">
                        </div>
                    </div>
                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Stroke Width</label>
                            <input type="number" id="propShapeStrokeWidth" class="prop-input" min="0" max="20" value="2" onchange="editor.updateShapeStrokeWidth(parseInt(this.value))">
                        </div>
                        <div class="prop-group" id="borderRadiusGroup">
                            <label class="prop-label">Border Radius</label>
                            <input type="number" id="propShapeBorderRadius" class="prop-input" min="0" max="100" value="8" onchange="editor.updateShapeBorderRadius(parseInt(this.value))">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Common Properties --}}
            <div id="commonPropertiesPanel" class="hidden">
                <div class="prop-section">
                    <div class="prop-section-title">Position</div>
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
                        <label class="prop-label">Transparency: <span id="opacityValue">0</span>%</label>
                        <input type="range" id="propOpacity" class="prop-slider" min="0" max="100" value="0" oninput="editor.updateOpacity(this.value)">
                    </div>
                </div>
                <div class="prop-section">
                    <button onclick="editor.deleteSelected()" class="prop-btn prop-btn-danger w-full">Delete Element</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="editor-footer">
        <div class="flex items-center gap-4 flex-1">
            <div class="zoom-controls">
                <button class="zoom-btn" onclick="editor.zoomOut()"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg></button>
                <span class="zoom-value" id="zoomValue">100%</span>
                <button class="zoom-btn" onclick="editor.zoomIn()"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg></button>
                <button class="zoom-btn" onclick="editor.zoomFit()" title="Fit to screen"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg></button>
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
<div id="previewModal" class="preview-modal">
    <button class="close-btn" onclick="editor.closePreview()">&times;</button>
    <img id="previewImage" src="" alt="Preview">
    <div class="actions">
        <a id="downloadBtn" href="#" download="template.png" class="prop-btn prop-btn-primary">Download PNG</a>
        <button onclick="editor.closePreview()" class="prop-btn prop-btn-secondary">Close</button>
    </div>
</div>

{{-- Hidden form --}}
<form id="saveForm" method="POST" action="{{ $template ? route('admin.tournaments.templates.update', [$tournament, $template]) : route('admin.tournaments.templates.store', $tournament) }}" enctype="multipart/form-data" class="hidden">
    @csrf
    @if($template) @method('PUT') @endif
    <input type="hidden" name="name" id="formName">
    <input type="hidden" name="type" value="{{ $type }}">
    <input type="hidden" name="layout_json" id="formLayoutJson">
    <input type="hidden" name="canvas_width" id="formCanvasWidth">
    <input type="hidden" name="canvas_height" id="formCanvasHeight">
    <input type="hidden" name="background_image_base64" id="formBackgroundBase64">
    <input type="hidden" name="overlay_images" id="formOverlayImages">
</form>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<script>
const editor = {
    canvas: null,
    history: [],
    historyIndex: -1,
    canvasWidth: {{ $template?->canvas_width ?? 1080 }},
    canvasHeight: {{ $template?->canvas_height ?? 1080 }},
    zoom: 1,
    backgroundImageData: null,

    init() {
        // Create canvas at fixed display size, we'll use viewportTransform for zooming
        this.canvas = new fabric.Canvas('fabricCanvas', {
            width: this.canvasWidth,
            height: this.canvasHeight,
            backgroundColor: '#1a1a2e',
            preserveObjectStacking: true,
        });

        // Fit canvas to available space
        this.zoomFit();

        this.setupEvents();
        this.setupDragDrop();
        this.setupKeyboard();

        // Load existing data
        @if($template && $template->layout_json)
            this.loadTemplate(@json($template->layout_json));
        @endif

        @if($template && $template->background_image)
            this.loadBackgroundFromUrl('{{ $template->background_image_url }}');
        @endif

        // Load existing overlay images into sidebar list
        @if($template && $template->overlay_images)
            @foreach($template->overlay_images as $ov)
                this.uploadedOverlays.push({ path: '{{ $ov['imagePath'] ?? $ov['path'] ?? '' }}', url: '{{ asset("storage/" . ($ov['imagePath'] ?? $ov['path'] ?? '')) }}', name: '{{ basename($ov['imagePath'] ?? $ov['path'] ?? 'image') }}' });
            @endforeach
            this.renderOverlayList();
        @endif

        // Update canvas size dropdown
        document.getElementById('canvasSizeSelect').value = this.canvasWidth + 'x' + this.canvasHeight;
        document.getElementById('canvasSizeDisplay').textContent = this.canvasWidth + ' x ' + this.canvasHeight;

        this.saveHistory();
        this.updateLayers();
    },

    setupEvents() {
        this.canvas.on('selection:created', (e) => this.showProperties(e.selected[0]));
        this.canvas.on('selection:updated', (e) => this.showProperties(e.selected[0]));
        this.canvas.on('selection:cleared', () => this.hideProperties());
        this.canvas.on('object:modified', () => { this.saveHistory(); this.updateProperties(); this.updateLayers(); });
        this.canvas.on('object:added', () => this.updateLayers());
        this.canvas.on('object:removed', () => this.updateLayers());
    },

    setupDragDrop() {
        document.querySelectorAll('.draggable-item').forEach(item => {
            item.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('type', item.dataset.type);
                e.dataTransfer.setData('placeholder', item.dataset.placeholder || '');
                e.dataTransfer.setData('shape', item.dataset.shape || '');
            });
        });

        const wrapper = document.getElementById('canvasWrapper');
        wrapper.addEventListener('dragover', (e) => e.preventDefault());
        wrapper.addEventListener('drop', (e) => {
            e.preventDefault();
            const type = e.dataTransfer.getData('type');
            const placeholder = e.dataTransfer.getData('placeholder');
            const shape = e.dataTransfer.getData('shape');

            // Calculate position in canvas coordinates
            const rect = this.canvas.getElement().getBoundingClientRect();
            const x = (e.clientX - rect.left) / this.zoom;
            const y = (e.clientY - rect.top) / this.zoom;

            if (type === 'text') this.addText(placeholder, x, y);
            else if (type === 'image') this.addImagePlaceholder(placeholder, x, y);
            else if (type === 'shape') this.addShape(shape, x, y);
        });
    },

    setupKeyboard() {
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
            if (e.ctrlKey && e.key === 'z') { e.preventDefault(); this.undo(); }
            if (e.ctrlKey && e.key === 'y') { e.preventDefault(); this.redo(); }
            if (e.ctrlKey && e.key === 's') { e.preventDefault(); this.save(); }
            if (e.ctrlKey && e.key === 'd') { e.preventDefault(); this.duplicate(); }
            if (e.key === 'Delete' || e.key === 'Backspace') this.deleteSelected();
            if (e.key === 'Escape') { this.canvas.discardActiveObject(); this.canvas.renderAll(); }
        });
    },

    // Add elements
    addText(placeholder, x, y) {
        const text = new fabric.IText('{{' + placeholder + '}}', {
            left: x, top: y,
            fontSize: 36,
            fontFamily: 'Montserrat',
            fontWeight: '700',
            fill: '#ffffff',
            originX: 'center', originY: 'center',
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
        const rect = new fabric.Rect({
            width: size, height: size,
            fill: 'rgba(99, 102, 241, 0.3)',
            stroke: '#6366f1', strokeWidth: 2, strokeDashArray: [5, 5],
            rx: 8, ry: 8,
            originX: 'center', originY: 'center',
        });
        const label = new fabric.Text(placeholder.replace(/_/g, '\n'), {
            fontSize: 14, fill: '#ffffff', fontFamily: 'Arial',
            originX: 'center', originY: 'center', textAlign: 'center',
        });
        const group = new fabric.Group([rect, label], {
            left: x, top: y, originX: 'center', originY: 'center',
        });
        group.placeholder = placeholder;
        group.elementType = 'image';
        group.placeholderWidth = size;
        group.placeholderHeight = size;
        this.canvas.add(group);
        this.canvas.setActiveObject(group);
        this.saveHistory();
    },

    addShape(type, x, y) {
        let shape;
        const props = { left: x, top: y, fill: '#6366f1', stroke: '#6366f1', strokeWidth: 2, originX: 'center', originY: 'center' };
        if (type === 'rect') shape = new fabric.Rect({ ...props, width: 150, height: 100, rx: 8, ry: 8 });
        else if (type === 'circle') shape = new fabric.Circle({ ...props, radius: 60 });
        else if (type === 'triangle') shape = new fabric.Triangle({ ...props, width: 120, height: 120 });
        else if (type === 'line') shape = new fabric.Line([0, 0, 200, 0], { stroke: '#6366f1', strokeWidth: 4, left: x, top: y, originX: 'center', originY: 'center' });
        else if (type === 'star') shape = new fabric.Polygon(this.starPoints(5, 60, 30), { ...props });
        else if (type === 'diamond') shape = new fabric.Polygon([{x:60,y:0},{x:120,y:80},{x:60,y:160},{x:0,y:80}], { ...props });
        if (shape) {
            shape.elementType = 'shape';
            shape.shapeType = type;
            this.canvas.add(shape);
            this.canvas.setActiveObject(shape);
            this.saveHistory();
        }
    },

    starPoints(spikes, outerR, innerR) {
        const points = [];
        const step = Math.PI / spikes;
        for (let i = 0; i < 2 * spikes; i++) {
            const r = i % 2 === 0 ? outerR : innerR;
            const angle = i * step - Math.PI / 2;
            points.push({ x: outerR + r * Math.cos(angle), y: outerR + r * Math.sin(angle) });
        }
        return points;
    },

    // Background
    uploadBackground(input) {
        if (!input.files[0]) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            this.backgroundImageData = e.target.result;
            fabric.Image.fromURL(e.target.result, (img) => {
                img.scaleToWidth(this.canvasWidth);
                img.scaleToHeight(this.canvasHeight);
                this.canvas.setBackgroundImage(img, this.canvas.renderAll.bind(this.canvas));
                document.getElementById('bgPreview').classList.remove('hidden');
                document.getElementById('bgPreviewImg').src = e.target.result;
                document.getElementById('bgUploadArea').classList.add('has-bg');
            });
        };
        reader.readAsDataURL(input.files[0]);
    },

    loadBackgroundFromUrl(url) {
        fabric.Image.fromURL(url, (img) => {
            img.scaleToWidth(this.canvasWidth);
            img.scaleToHeight(this.canvasHeight);
            this.canvas.setBackgroundImage(img, this.canvas.renderAll.bind(this.canvas));
            document.getElementById('bgPreview').classList.remove('hidden');
            document.getElementById('bgPreviewImg').src = url;
            document.getElementById('bgUploadArea').classList.add('has-bg');
        }, { crossOrigin: 'anonymous' });
    },

    removeBackground() {
        this.canvas.setBackgroundImage(null, this.canvas.renderAll.bind(this.canvas));
        this.backgroundImageData = null;
        document.getElementById('bgPreview').classList.add('hidden');
        document.getElementById('bgUploadArea').classList.remove('has-bg');
        document.getElementById('bgImageInput').value = '';
    },

    setBackgroundColor(color) {
        this.canvas.setBackgroundColor(color, this.canvas.renderAll.bind(this.canvas));
    },

    // Zoom
    zoomIn() { this.setZoom(Math.min(this.zoom * 1.2, 3)); },
    zoomOut() { this.setZoom(Math.max(this.zoom / 1.2, 0.1)); },
    zoomFit() {
        const wrapper = document.getElementById('canvasWrapper');
        const scaleX = (wrapper.clientWidth - 40) / this.canvasWidth;
        const scaleY = (wrapper.clientHeight - 40) / this.canvasHeight;
        this.setZoom(Math.min(scaleX, scaleY, 1));
    },
    setZoom(z) {
        this.zoom = z;
        this.canvas.setZoom(z);
        this.canvas.setWidth(this.canvasWidth * z);
        this.canvas.setHeight(this.canvasHeight * z);
        document.getElementById('zoomValue').textContent = Math.round(z * 100) + '%';
    },

    changeCanvasSize(size) {
        const [w, h] = size.split('x').map(Number);
        this.canvasWidth = w;
        this.canvasHeight = h;
        this.canvas.setWidth(w * this.zoom);
        this.canvas.setHeight(h * this.zoom);
        document.getElementById('canvasSizeDisplay').textContent = w + ' x ' + h;
        if (this.canvas.backgroundImage) {
            this.canvas.backgroundImage.scaleToWidth(w);
            this.canvas.backgroundImage.scaleToHeight(h);
        }
        this.canvas.renderAll();
    },

    // Properties panel
    showProperties(obj) {
        document.getElementById('noSelectionPanel').classList.add('hidden');
        document.getElementById('commonPropertiesPanel').classList.remove('hidden');

        const isText = obj.elementType === 'text' || obj.type === 'i-text';
        const isShape = obj.elementType === 'shape';
        document.getElementById('textPropertiesPanel').classList.toggle('hidden', !isText);
        document.getElementById('shapePropertiesPanel').classList.toggle('hidden', !isShape);

        if (isText) {
            document.getElementById('propFontFamily').value = obj.fontFamily || 'Arial';
            document.getElementById('propFontSize').value = Math.round(obj.fontSize || 24);
            document.getElementById('propFontWeight').value = obj.fontWeight || '400';
            document.getElementById('propTextColor').value = obj.fill || '#ffffff';
        }
        if (isShape) {
            this.updateShapePropertiesPanel(obj);
        }
        this.updateProperties();
    },

    updateShapePropertiesPanel(obj) {
        const fill = obj.fill;
        const isGradient = fill && typeof fill === 'object' && fill.type;

        if (isGradient) {
            document.getElementById('propFillType').value = fill.type;
            document.getElementById('solidFillGroup').classList.add('hidden');
            document.getElementById('gradientFillGroup').classList.remove('hidden');
            document.getElementById('gradientAngleGroup').style.display = fill.type === 'linear' ? '' : 'none';
            const stops = fill.colorStops || [];
            if (stops.length >= 2) {
                document.getElementById('propGradientColor1').value = stops[0].color || '#6366f1';
                document.getElementById('propGradientColor2').value = stops[1].color || '#ec4899';
            }
            document.getElementById('propGradientAngle').value = obj.gradientAngle || 90;
            document.getElementById('gradientAngleValue').textContent = obj.gradientAngle || 90;
        } else {
            document.getElementById('propFillType').value = 'solid';
            document.getElementById('solidFillGroup').classList.remove('hidden');
            document.getElementById('gradientFillGroup').classList.add('hidden');
            // Convert fill to hex for color picker
            const hex = this.colorToHex(fill || '#6366f1');
            document.getElementById('propShapeFill').value = hex;
        }

        document.getElementById('propShapeStroke').value = this.colorToHex(obj.stroke || '#6366f1');
        document.getElementById('propShapeStrokeWidth').value = obj.strokeWidth || 2;
        // Show border radius only for rect
        const brGroup = document.getElementById('borderRadiusGroup');
        brGroup.style.display = obj.shapeType === 'rect' ? '' : 'none';
        if (obj.shapeType === 'rect') {
            document.getElementById('propShapeBorderRadius').value = obj.rx || 0;
        }
    },

    colorToHex(color) {
        if (!color || typeof color !== 'string') return '#6366f1';
        if (color.startsWith('#')) return color.length === 4 ? '#' + color[1]+color[1]+color[2]+color[2]+color[3]+color[3] : color;
        const m = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        if (m) return '#' + [m[1],m[2],m[3]].map(x => parseInt(x).toString(16).padStart(2,'0')).join('');
        return '#6366f1';
    },

    hideProperties() {
        document.getElementById('noSelectionPanel').classList.remove('hidden');
        document.getElementById('commonPropertiesPanel').classList.add('hidden');
        document.getElementById('textPropertiesPanel').classList.add('hidden');
        document.getElementById('shapePropertiesPanel').classList.add('hidden');
    },

    updateProperties() {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        document.getElementById('propPosX').value = Math.round(obj.left);
        document.getElementById('propPosY').value = Math.round(obj.top);
        document.getElementById('propRotation').value = Math.round(obj.angle || 0);
        document.getElementById('rotationValue').textContent = Math.round(obj.angle || 0);
        document.getElementById('propOpacity').value = Math.round(100 - (obj.opacity ?? 1) * 100);
        document.getElementById('opacityValue').textContent = Math.round(100 - (obj.opacity ?? 1) * 100);
    },

    updateText(prop, value) {
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

    updateRotation(val) {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        document.getElementById('rotationValue').textContent = val;
        obj.set('angle', parseInt(val));
        this.canvas.renderAll();
    },

    updateOpacity(val) {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        document.getElementById('opacityValue').textContent = val;
        obj.set('opacity', (100 - val) / 100);
        this.canvas.renderAll();
    },

    // Shape property updates
    updateShapeFillType(type) {
        if (type === 'solid') {
            document.getElementById('solidFillGroup').classList.remove('hidden');
            document.getElementById('gradientFillGroup').classList.add('hidden');
            this.updateShapeFill(document.getElementById('propShapeFill').value);
        } else {
            document.getElementById('solidFillGroup').classList.add('hidden');
            document.getElementById('gradientFillGroup').classList.remove('hidden');
            document.getElementById('gradientAngleGroup').style.display = type === 'linear' ? '' : 'none';
            this.updateShapeGradient();
        }
    },

    updateShapeFill(color) {
        const obj = this.canvas.getActiveObject();
        if (!obj || obj.elementType !== 'shape') return;
        obj.set('fill', color);
        obj.gradientFillConfig = null;
        document.getElementById('propShapeFill').value = this.colorToHex(color);
        this.canvas.renderAll();
        this.saveHistory();
    },

    updateShapeGradient() {
        const obj = this.canvas.getActiveObject();
        if (!obj || obj.elementType !== 'shape') return;
        const type = document.getElementById('propFillType').value;
        const c1 = document.getElementById('propGradientColor1').value;
        const c2 = document.getElementById('propGradientColor2').value;
        const angle = parseInt(document.getElementById('propGradientAngle').value) || 90;

        const w = obj.width || 120;
        const h = obj.height || 120;

        let gradient;
        if (type === 'linear') {
            const rad = (angle * Math.PI) / 180;
            const cos = Math.cos(rad), sin = Math.sin(rad);
            gradient = new fabric.Gradient({
                type: 'linear',
                coords: { x1: w/2 - cos*w/2, y1: h/2 - sin*h/2, x2: w/2 + cos*w/2, y2: h/2 + sin*h/2 },
                colorStops: [{ offset: 0, color: c1 }, { offset: 1, color: c2 }]
            });
        } else {
            gradient = new fabric.Gradient({
                type: 'radial',
                coords: { x1: w/2, y1: h/2, r1: 0, x2: w/2, y2: h/2, r2: Math.max(w, h)/2 },
                colorStops: [{ offset: 0, color: c1 }, { offset: 1, color: c2 }]
            });
        }
        obj.set('fill', gradient);
        obj.gradientAngle = angle;
        obj.gradientFillConfig = { type, angle, colorStops: [{ offset: 0, color: c1 }, { offset: 1, color: c2 }] };
        this.canvas.renderAll();
        this.saveHistory();
    },

    applyGradientPreset(c1, c2) {
        document.getElementById('propGradientColor1').value = c1;
        document.getElementById('propGradientColor2').value = c2;
        if (document.getElementById('propFillType').value === 'solid') {
            document.getElementById('propFillType').value = 'linear';
            this.updateShapeFillType('linear');
        } else {
            this.updateShapeGradient();
        }
    },

    updateShapeStroke(color) {
        const obj = this.canvas.getActiveObject();
        if (!obj || obj.elementType !== 'shape') return;
        obj.set('stroke', color);
        this.canvas.renderAll();
        this.saveHistory();
    },

    updateShapeStrokeWidth(val) {
        const obj = this.canvas.getActiveObject();
        if (!obj || obj.elementType !== 'shape') return;
        obj.set('strokeWidth', val);
        this.canvas.renderAll();
        this.saveHistory();
    },

    updateShapeBorderRadius(val) {
        const obj = this.canvas.getActiveObject();
        if (!obj || obj.shapeType !== 'rect') return;
        obj.set({ rx: val, ry: val });
        this.canvas.renderAll();
        this.saveHistory();
    },

    // Image overlay upload
    uploadedOverlays: [],

    uploadOverlayImage(input) {
        if (!input.files[0]) return;
        const file = input.files[0];
        const formData = new FormData();
        formData.append('overlay_image', file);
        formData.append('_token', '{{ csrf_token() }}');

        fetch('{{ route("admin.tournaments.templates.upload-overlay", $tournament) }}', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                this.uploadedOverlays.push({ path: data.path, url: data.url, name: file.name });
                this.renderOverlayList();
                input.value = '';
            }
        })
        .catch(err => console.error('Upload failed:', err));
    },

    renderOverlayList() {
        const list = document.getElementById('overlayImagesList');
        list.innerHTML = this.uploadedOverlays.map((ov, i) => `
            <div class="flex items-center gap-2 p-2 rounded bg-gray-800/50 hover:bg-gray-700/50 cursor-pointer group" style="font-size:12px;">
                <img src="${ov.url}" class="w-8 h-8 rounded object-cover flex-shrink-0" onerror="this.style.display='none'">
                <span class="text-gray-300 truncate flex-1" onclick="editor.addUploadedImage('${ov.url}', '${ov.path}')" title="Click to add">${ov.name}</span>
                <button onclick="editor.removeOverlay(${i})" class="text-gray-500 hover:text-red-400 flex-shrink-0" title="Delete">&times;</button>
            </div>
        `).join('');
    },

    addUploadedImage(url, path) {
        fabric.Image.fromURL(url, (img) => {
            img.set({
                left: this.canvasWidth / 2,
                top: this.canvasHeight / 2,
                originX: 'center',
                originY: 'center',
                elementType: 'uploadedImage',
                imagePath: path,
            });
            img.scaleToWidth(Math.min(200, this.canvasWidth / 3));
            this.canvas.add(img);
            this.canvas.setActiveObject(img);
            this.saveHistory();
        }, { crossOrigin: 'anonymous' });
    },

    removeOverlay(index) {
        const ov = this.uploadedOverlays[index];
        if (!ov) return;
        fetch('{{ route("admin.tournaments.templates.delete-overlay", $tournament) }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ path: ov.path })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                this.uploadedOverlays.splice(index, 1);
                this.renderOverlayList();
            }
        });
    },

    // Actions
    deleteSelected() {
        const obj = this.canvas.getActiveObject();
        if (obj) { this.canvas.remove(obj); this.saveHistory(); }
    },

    duplicate() {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        obj.clone((cloned) => {
            cloned.set({ left: obj.left + 20, top: obj.top + 20 });
            cloned.placeholder = obj.placeholder;
            cloned.elementType = obj.elementType;
            cloned.shapeType = obj.shapeType;
            cloned.imagePath = obj.imagePath;
            cloned.gradientAngle = obj.gradientAngle;
            cloned.gradientFillConfig = obj.gradientFillConfig;
            cloned.placeholderWidth = obj.placeholderWidth;
            cloned.placeholderHeight = obj.placeholderHeight;
            this.canvas.add(cloned);
            this.canvas.setActiveObject(cloned);
            this.saveHistory();
        });
    },

    bringForward() { const obj = this.canvas.getActiveObject(); if (obj) { this.canvas.bringForward(obj); this.updateLayers(); } },
    sendBackward() { const obj = this.canvas.getActiveObject(); if (obj) { this.canvas.sendBackwards(obj); this.updateLayers(); } },

    // History
    saveHistory() {
        const json = this.canvas.toJSON(['placeholder', 'elementType', 'shapeType', 'placeholderWidth', 'placeholderHeight', 'imagePath', 'gradientAngle', 'gradientFillConfig']);
        this.history = this.history.slice(0, this.historyIndex + 1);
        this.history.push(JSON.stringify(json));
        this.historyIndex++;
        if (this.history.length > 30) { this.history.shift(); this.historyIndex--; }
    },

    undo() {
        if (this.historyIndex > 0) {
            this.historyIndex--;
            this.canvas.loadFromJSON(JSON.parse(this.history[this.historyIndex]), () => { this.canvas.renderAll(); this.updateLayers(); });
        }
    },

    redo() {
        if (this.historyIndex < this.history.length - 1) {
            this.historyIndex++;
            this.canvas.loadFromJSON(JSON.parse(this.history[this.historyIndex]), () => { this.canvas.renderAll(); this.updateLayers(); });
        }
    },

    // Layers
    updateLayers() {
        const objects = this.canvas.getObjects();
        document.getElementById('layerCount').textContent = objects.length;
        const list = document.getElementById('layersList');
        list.innerHTML = [...objects].reverse().map((obj, i) => {
            const name = obj.placeholder || obj.shapeType || obj.type || 'Element';
            const selected = obj === this.canvas.getActiveObject();
            const idx = objects.length - 1 - i;
            return `<div class="layer-item ${selected ? 'selected' : ''}" onclick="editor.selectLayer(${idx})">
                <div class="layer-thumb"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg></div>
                <div class="layer-info"><div class="layer-name">${name.replace(/_/g, ' ')}</div><div class="layer-type">${obj.elementType || obj.type}</div></div>
            </div>`;
        }).join('');
    },

    selectLayer(idx) {
        const obj = this.canvas.getObjects()[idx];
        if (obj) { this.canvas.setActiveObject(obj); this.canvas.renderAll(); }
    },

    // Preview
    showPreview() {
        const dataUrl = this.canvas.toDataURL({ format: 'png', quality: 1, multiplier: 1 / this.zoom });
        document.getElementById('previewImage').src = dataUrl;
        document.getElementById('downloadBtn').href = dataUrl;
        document.getElementById('previewModal').classList.add('show');
    },

    closePreview() {
        document.getElementById('previewModal').classList.remove('show');
    },

    // Load template
    loadTemplate(layout) {
        if (!layout || !Array.isArray(layout)) return;
        let pendingImages = 0;
        const totalItems = layout.length;

        layout.forEach((item, layoutIndex) => {
            const x = (item.x / 100) * this.canvasWidth;
            const y = (item.y / 100) * this.canvasHeight;

            if (item.type === 'text' || item.type === 'i-text') {
                const text = new fabric.IText(item.placeholder ? '{{' + item.placeholder + '}}' : 'Text', {
                    left: x, top: y,
                    fontSize: item.fontSize || 24,
                    fontFamily: item.fontFamily || 'Arial',
                    fontWeight: item.fontWeight || '400',
                    fill: item.color || '#ffffff',
                    angle: item.rotation || 0,
                    opacity: (item.opacity ?? 100) / 100,
                    textAlign: item.textAlign || 'center',
                    originX: 'center', originY: 'center',
                });
                if (item.shadow) text.shadow = new fabric.Shadow({ color: 'rgba(0,0,0,0.5)', blur: item.shadow.blur || 5, offsetX: item.shadow.offsetX || 2, offsetY: item.shadow.offsetY || 2 });
                text.placeholder = item.placeholder;
                text.elementType = 'text';
                text._layoutIndex = layoutIndex;
                this.canvas.add(text);
            } else if (item.type === 'image') {
                const w = item.width || 150, h = item.height || 150;
                const rect = new fabric.Rect({ width: w, height: h, fill: 'rgba(99, 102, 241, 0.3)', stroke: '#6366f1', strokeWidth: 2, strokeDashArray: [5, 5], rx: 8, ry: 8, originX: 'center', originY: 'center' });
                const label = new fabric.Text((item.placeholder || 'image').replace(/_/g, '\n'), { fontSize: 14, fill: '#fff', fontFamily: 'Arial', originX: 'center', originY: 'center', textAlign: 'center' });
                const group = new fabric.Group([rect, label], { left: x, top: y, angle: item.rotation || 0, opacity: (item.opacity ?? 100) / 100, originX: 'center', originY: 'center' });
                group.placeholder = item.placeholder;
                group.elementType = 'image';
                group.placeholderWidth = w;
                group.placeholderHeight = h;
                group._layoutIndex = layoutIndex;
                this.canvas.add(group);
            } else if (item.type === 'shape') {
                let shape;
                const solidFill = (typeof item.fill === 'string') ? item.fill : '#6366f1';
                const props = { left: x, top: y, fill: solidFill, stroke: item.stroke || '#6366f1', strokeWidth: item.strokeWidth || 2, angle: item.rotation || 0, opacity: (item.opacity ?? 100) / 100, originX: 'center', originY: 'center' };
                if (item.shapeType === 'rect') shape = new fabric.Rect({ ...props, width: item.width || 150, height: item.height || 100, rx: item.rx ?? 8, ry: item.ry ?? 8 });
                else if (item.shapeType === 'circle') shape = new fabric.Circle({ ...props, radius: (item.width || 120) / 2 });
                else if (item.shapeType === 'triangle') shape = new fabric.Triangle({ ...props, width: item.width || 120, height: item.height || 120 });
                else if (item.shapeType === 'line') shape = new fabric.Line([0, 0, item.width || 200, 0], { ...props, fill: null });
                else if (item.shapeType === 'star') shape = new fabric.Polygon(this.starPoints(5, (item.width || 120) / 2, (item.width || 120) / 4), { ...props });
                else if (item.shapeType === 'diamond') {
                    const w = item.width || 120, h = item.height || 160;
                    shape = new fabric.Polygon([{x:w/2,y:0},{x:w,y:h/2},{x:w/2,y:h},{x:0,y:h/2}], { ...props });
                }
                if (shape) {
                    shape.elementType = 'shape';
                    shape.shapeType = item.shapeType;
                    shape._layoutIndex = layoutIndex;
                    // Restore gradient fill if saved
                    if (item.fill && typeof item.fill === 'object' && item.fill.type) {
                        const gc = item.fill;
                        const w = shape.width || 120, h = shape.height || 120;
                        let gradient;
                        if (gc.type === 'linear') {
                            const angle = gc.angle || 90;
                            const rad = (angle * Math.PI) / 180;
                            const cos = Math.cos(rad), sin = Math.sin(rad);
                            gradient = new fabric.Gradient({
                                type: 'linear',
                                coords: { x1: w/2 - cos*w/2, y1: h/2 - sin*h/2, x2: w/2 + cos*w/2, y2: h/2 + sin*h/2 },
                                colorStops: gc.colorStops || [{ offset: 0, color: '#6366f1' }, { offset: 1, color: '#ec4899' }]
                            });
                        } else {
                            gradient = new fabric.Gradient({
                                type: 'radial',
                                coords: { x1: w/2, y1: h/2, r1: 0, x2: w/2, y2: h/2, r2: Math.max(w, h)/2 },
                                colorStops: gc.colorStops || [{ offset: 0, color: '#6366f1' }, { offset: 1, color: '#ec4899' }]
                            });
                        }
                        shape.set('fill', gradient);
                        shape.gradientAngle = gc.angle || 90;
                        shape.gradientFillConfig = gc;
                    }
                    this.canvas.add(shape);
                }
            } else if (item.type === 'uploadedImage') {
                pendingImages++;
                const imgPath = item.imagePath || item.path || '';
                if (imgPath) {
                    const imgUrl = imgPath.startsWith('http') ? imgPath : '{{ asset("storage") }}/' + imgPath;
                    fabric.Image.fromURL(imgUrl, (img) => {
                        img.set({
                            left: x, top: y,
                            originX: 'center', originY: 'center',
                            angle: item.rotation || 0,
                            opacity: (item.opacity ?? 100) / 100,
                            elementType: 'uploadedImage',
                            imagePath: imgPath,
                        });
                        img._layoutIndex = layoutIndex;
                        if (item.width) img.scaleToWidth(item.width);
                        this.canvas.add(img);
                        pendingImages--;
                        if (pendingImages === 0) {
                            this._reorderByLayoutIndex();
                        }
                        this.canvas.renderAll();
                    }, { crossOrigin: 'anonymous' });
                } else {
                    pendingImages--;
                }
            }
        });
        this.canvas.renderAll();
    },

    _reorderByLayoutIndex() {
        const objects = this.canvas.getObjects().slice();
        objects.sort((a, b) => (a._layoutIndex ?? 0) - (b._layoutIndex ?? 0));
        this.canvas._objects.length = 0;
        objects.forEach(obj => this.canvas._objects.push(obj));
        this.canvas.renderAll();
    },

    // Save
    save() {
        const btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.innerHTML = '<svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Saving...';

        const objects = this.canvas.getObjects();
        const layout = objects.map((obj, i) => {
            const base = {
                type: obj.elementType || obj.type,
                placeholder: obj.placeholder || null,
                x: (obj.left / this.canvasWidth) * 100,
                y: (obj.top / this.canvasHeight) * 100,
                rotation: obj.angle || 0,
                opacity: (obj.opacity ?? 1) * 100,
                zIndex: i,
            };

            if (obj.elementType === 'text' || obj.type === 'i-text') {
                return { ...base, fontSize: obj.fontSize, fontFamily: obj.fontFamily, fontWeight: obj.fontWeight, color: obj.fill, textAlign: obj.textAlign, shadow: obj.shadow ? { blur: obj.shadow.blur, offsetX: obj.shadow.offsetX, offsetY: obj.shadow.offsetY } : null };
            } else if (obj.elementType === 'image') {
                return { ...base, width: (obj.placeholderWidth || 150) * (obj.scaleX || 1), height: (obj.placeholderHeight || 150) * (obj.scaleY || 1) };
            } else if (obj.elementType === 'shape') {
                // Serialize gradient fill config if present
                let fillData = obj.fill;
                if (obj.gradientFillConfig) {
                    fillData = obj.gradientFillConfig;
                } else if (obj.fill && typeof obj.fill === 'object' && obj.fill.type) {
                    // Extract gradient info from fabric gradient object
                    fillData = { type: obj.fill.type, angle: obj.gradientAngle || 90, colorStops: obj.fill.colorStops };
                }
                return { ...base, shapeType: obj.shapeType, fill: fillData, stroke: obj.stroke, strokeWidth: obj.strokeWidth, width: (obj.width || 150) * (obj.scaleX || 1), height: (obj.height || 100) * (obj.scaleY || 1), rx: obj.rx || 0, ry: obj.ry || 0 };
            } else if (obj.elementType === 'uploadedImage') {
                return { ...base, type: 'uploadedImage', imagePath: obj.imagePath, width: (obj.width || 150) * (obj.scaleX || 1), height: (obj.height || 150) * (obj.scaleY || 1) };
            }
            return base;
        });

        // Separate uploaded images into overlay_images array
        const overlayImages = layout.filter(el => el.type === 'uploadedImage');

        document.getElementById('formName').value = document.getElementById('templateName').value;
        document.getElementById('formLayoutJson').value = JSON.stringify(layout);
        document.getElementById('formOverlayImages').value = JSON.stringify(overlayImages);
        document.getElementById('formCanvasWidth').value = this.canvasWidth;
        document.getElementById('formCanvasHeight').value = this.canvasHeight;

        if (this.backgroundImageData) {
            document.getElementById('formBackgroundBase64').value = this.backgroundImageData;
        }

        document.getElementById('saveForm').submit();
    },
};

function switchTab(tab) {
    document.querySelectorAll('.sidebar-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
    document.getElementById(`tab-${tab}`).classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', () => editor.init());
</script>
@endpush
