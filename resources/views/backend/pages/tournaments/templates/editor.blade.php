@extends('backend.layouts.app')

@section('title', ($template ? 'Edit' : 'Create') . ' Template | ' . $tournament->name)

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&family=Open+Sans:wght@300;400;600;700&family=Montserrat:wght@300;400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800;900&family=Oswald:wght@300;400;500;600;700&family=Bebas+Neue&family=Anton&family=Bangers&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
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
    .sidebar-accordion { background: #252538; border-radius: 8px; margin-bottom: 6px; overflow: hidden; border: 1px solid #2d2d44; }
    .sidebar-accordion-header { display: flex; align-items: center; gap: 8px; padding: 10px 12px; cursor: pointer; user-select: none; }
    .sidebar-accordion-header:hover { background: #2d2d4a; }
    .sidebar-accordion-header .acc-icon { width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .sidebar-accordion-header .acc-icon svg { width: 14px; height: 14px; color: white; }
    .sidebar-accordion-header .acc-title { flex: 1; font-size: 12px; font-weight: 600; color: #e2e2e2; }
    .sidebar-accordion-header .acc-chevron { width: 16px; height: 16px; color: #8b8ba7; transition: transform 0.2s; }
    .sidebar-accordion-header.open .acc-chevron { transform: rotate(180deg); }
    .sidebar-accordion-body { display: none; padding: 4px 8px 8px; }
    .sidebar-accordion-body.open { display: block; }
    .sidebar-accordion-body .draggable-item { background: #1a1a2e; margin-bottom: 4px; padding: 8px 10px; }
    .sidebar-accordion-body .draggable-item:hover { background: #2d2d4a; }

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
    .toolbar-btn.active { background: #4f46e5; color: #fff; }
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
    .layer-actions { display: flex; align-items: center; gap: 2px; margin-left: auto; flex-shrink: 0; }
    .layer-action-btn { width: 22px; height: 22px; border-radius: 4px; display: flex; align-items: center; justify-content: center; background: transparent; border: none; color: #8b8ba7; cursor: pointer; padding: 0; }
    .layer-action-btn:hover { background: rgba(99, 102, 241, 0.2); color: #c4b5fd; }
    .layer-action-btn.active { color: #818cf8; }
    .layer-action-btn svg { width: 14px; height: 14px; }
    .layer-item.hidden-layer { opacity: 0.45; }
    .layer-item.locked-layer { border-left: 2px solid #f59e0b; }
    .layer-rename-input { background: #1a1a2e; border: 1px solid #6366f1; border-radius: 4px; color: #e2e2e2; font-size: 12px; padding: 2px 6px; width: 100%; outline: none; }
    .layer-drag-handle { cursor: grab; color: #5a5a7a; display: flex; align-items: center; flex-shrink: 0; padding: 0 2px; }
    .layer-drag-handle:active { cursor: grabbing; }
    .layer-drag-handle svg { width: 14px; height: 14px; }
    .layer-item.drag-over { border-top: 2px solid #818cf8; }

    .icon-item { display: flex; align-items: center; justify-content: center; padding: 8px; background: #252538; border-radius: 8px; cursor: pointer; border: 1px solid transparent; }
    .icon-item:hover { background: #2d2d4a; border-color: #4f46e5; }

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
                <button class="toolbar-btn" onclick="editor.bringToFront()" title="Bring to Front"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 11l7-7 7 7M5 19l7-7 7 7"/></svg></button>
                <button class="toolbar-btn" onclick="editor.bringForward()" title="Bring Forward"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg></button>
                <button class="toolbar-btn" onclick="editor.sendBackward()" title="Send Backward"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
                <button class="toolbar-btn" onclick="editor.sendToBack()" title="Send to Back"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 13l-7 7-7-7m14-8l-7 7-7-7"/></svg></button>
            </div>
            <div class="toolbar-group">
                <button class="toolbar-btn" id="gridToggleBtn" onclick="editor.toggleGrid()" title="Toggle Grid">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h16v16H4zM4 9h16M4 14h16M9 4v16M14 4v16"/></svg>
                </button>
            </div>
            <div class="toolbar-group" style="border-right: none;">
                <button class="toolbar-btn" onclick="editor.alignObjects('left')" title="Align Left">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v16M8 8h12M8 16h8"/></svg>
                </button>
                <button class="toolbar-btn" onclick="editor.alignObjects('centerH')" title="Align Center Horizontal">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16M6 8h12M8 16h8"/></svg>
                </button>
                <button class="toolbar-btn" onclick="editor.alignObjects('right')" title="Align Right">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 4v16M4 8h12M8 16h8"/></svg>
                </button>
                <button class="toolbar-btn" onclick="editor.alignObjects('top')" title="Align Top">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h16M8 8v12M16 8v8"/></svg>
                </button>
                <button class="toolbar-btn" onclick="editor.alignObjects('centerV')" title="Align Center Vertical">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12h16M8 6v12M16 8v8"/></svg>
                </button>
                <button class="toolbar-btn" onclick="editor.alignObjects('bottom')" title="Align Bottom">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 20h16M8 4v12M16 8v8"/></svg>
                </button>
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
                    {{-- Quick Add --}}
                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Quick Add</div>
                        <div class="draggable-item" draggable="true" data-type="customText" style="cursor: pointer;" onclick="editor.addCustomText()">
                            <div class="icon" style="background: linear-gradient(135deg, #6366f1, #a855f7);"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></div>
                            <div class="info"><div class="name">Custom Text</div><div class="type">Editable text</div></div>
                        </div>
                    </div>

                    @php
                        $isImage = fn($p) => in_array($p, ['player_image', 'team_logo', 'tournament_logo', 'team_a_logo', 'team_b_logo', 'team_a_captain_image', 'team_b_captain_image', 'man_of_the_match_image', 'best_batsman_image', 'best_bowler_image', 'winner_logo', 'qr_code']);
                        $awardGroups = [
                            'Man of the Match' => ['prefix' => 'man_of_the_match', 'color' => '#f59e0b, #d97706'],
                            'Best Batsman' => ['prefix' => 'best_batsman', 'color' => '#10b981, #059669'],
                            'Best Bowler' => ['prefix' => 'best_bowler', 'color' => '#ec4899, #be185d'],
                        ];
                        $awardPrefixes = array_column($awardGroups, 'prefix');
                        $isAwardPlaceholder = fn($p) => collect($awardPrefixes)->contains(fn($pre) => str_starts_with($p, $pre . '_'));
                    @endphp

                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Text Placeholders</div>
                        @foreach($placeholders as $placeholder)
                            @if(!$isImage($placeholder) && $placeholder !== 'table_data' && $placeholder !== 'fixture_area' && !$isAwardPlaceholder($placeholder))
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

                    @foreach($awardGroups as $groupLabel => $groupInfo)
                        @php $groupPlaceholders = collect($placeholders)->filter(fn($p) => str_starts_with($p, $groupInfo['prefix'] . '_'))->values(); @endphp
                        @if($groupPlaceholders->isNotEmpty())
                        <div class="sidebar-section" style="margin-bottom: 6px;">
                            <div class="sidebar-accordion">
                                <div class="sidebar-accordion-header" onclick="this.classList.toggle('open'); this.nextElementSibling.classList.toggle('open');">
                                    <div class="acc-icon" style="background: linear-gradient(135deg, {{ $groupInfo['color'] }});">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                                    </div>
                                    <span class="acc-title">{{ $groupLabel }}</span>
                                    <svg class="acc-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="sidebar-accordion-body">
                                    @foreach($groupPlaceholders as $placeholder)
                                        @if($isImage($placeholder))
                                        <div class="draggable-item" draggable="true" data-type="image" data-placeholder="{{ $placeholder }}">
                                            <div class="icon image"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                                            <div class="info">
                                                <div class="name">{{ str_replace('_', ' ', ucwords(str_replace($groupInfo['prefix'] . '_', '', $placeholder), '_')) }}</div>
                                                <div class="type">Image</div>
                                            </div>
                                        </div>
                                        @else
                                        <div class="draggable-item" draggable="true" data-type="text" data-placeholder="{{ $placeholder }}">
                                            <div class="icon text"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg></div>
                                            <div class="info">
                                                <div class="name">{{ str_replace('_', ' ', ucwords(str_replace($groupInfo['prefix'] . '_', '', $placeholder), '_')) }}</div>
                                                <div class="type">Text</div>
                                            </div>
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endif
                    @endforeach

                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Image Placeholders</div>
                        @foreach($placeholders as $placeholder)
                            @if($isImage($placeholder) && !$isAwardPlaceholder($placeholder))
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
                        <div class="sidebar-section-title">Icons</div>
                        <input type="text" id="iconSearch" class="prop-input mb-2" placeholder="Search icons..." oninput="filterIcons(this.value)" style="font-size:12px;">
                        <div id="iconGrid" class="icon-grid-scroll" style="max-height:320px; overflow-y:auto;">
                            {{-- Cricket --}}
                            <div class="icon-category" data-category="cricket">
                                <div class="text-[10px] text-gray-500 uppercase tracking-wider mb-1 mt-1 px-1">Cricket</div>
                                <div class="grid grid-cols-5 gap-1">
                                    <div class="icon-item" title="Cricket Bat" onclick="editor.addSvgIcon('cricket_bat')">
                                        <svg viewBox="0 0 64 64" fill="currentColor" class="w-5 h-5"><path d="M14 50l-4 4a2 2 0 002.83 2.83l4-4L14 50zm4-4l22-22c2-2 6-3 8-1s1 6-1 8L25 53l-7-7zm26-26l6-6a4 4 0 00-5.66-5.66l-6 6 5.66 5.66z"/></svg>
                                    </div>
                                    <div class="icon-item" title="Cricket Ball" onclick="editor.addSvgIcon('cricket_ball')">
                                        <svg viewBox="0 0 64 64" class="w-5 h-5"><circle cx="32" cy="32" r="20" fill="none" stroke="currentColor" stroke-width="5"/><path d="M22 16c4 8 4 24 0 32M42 16c-4 8-4 24 0 32" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/></svg>
                                    </div>
                                    <div class="icon-item" title="Stumps" onclick="editor.addSvgIcon('stumps')">
                                        <svg viewBox="0 0 64 64" fill="currentColor" class="w-5 h-5"><rect x="22" y="16" width="4" height="36" rx="1"/><rect x="30" y="16" width="4" height="36" rx="1"/><rect x="38" y="16" width="4" height="36" rx="1"/><rect x="20" y="18" width="24" height="3" rx="1"/><rect x="20" y="26" width="24" height="3" rx="1"/></svg>
                                    </div>
                                    <div class="icon-item" title="Bat & Ball" onclick="editor.addSvgIcon('bat_ball')">
                                        <svg viewBox="0 0 64 64" fill="currentColor" class="w-5 h-5"><path d="M10 52l-3 3a1.5 1.5 0 002.12 2.12l3-3L10 52zm3-3l18-18c1.5-1.5 5-2.5 6.5-1s.5 5-1 6.5L18.5 54.5l-5.5-5.5zM35 31l5-5a3 3 0 00-4.24-4.24l-5 5L35 31z"/><circle cx="48" cy="16" r="8" fill="none" stroke="currentColor" stroke-width="3.5"/><path d="M44 10c1.5 3 1.5 9 0 12M52 10c-1.5 3-1.5 9 0 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                    </div>
                                    <div class="icon-item" title="Wicket" onclick="editor.addSvgIcon('wicket')">
                                        <svg viewBox="0 0 64 64" fill="currentColor" class="w-5 h-5"><rect x="18" y="14" width="4" height="40" rx="1"/><rect x="30" y="14" width="4" height="40" rx="1"/><rect x="42" y="14" width="4" height="40" rx="1"/><path d="M20 16 L26 10 L32 16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><path d="M32 16 L38 10 L44 16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                                    </div>
                                </div>
                            </div>
                            {{-- Sports --}}
                            <div class="icon-category" data-category="sports">
                                <div class="text-[10px] text-gray-500 uppercase tracking-wider mb-1 mt-2 px-1">Sports</div>
                                <div class="grid grid-cols-5 gap-1">
                                    <div class="icon-item" title="Baseball" onclick="editor.addIcon('\uf433','baseball')"><i class="fa-solid fa-baseball"></i></div>
                                    <div class="icon-item" title="Table Tennis" onclick="editor.addIcon('\uf45d','table-tennis')"><i class="fa-solid fa-table-tennis-paddle-ball"></i></div>
                                    <div class="icon-item" title="Bullseye" onclick="editor.addIcon('\uf140','bullseye')"><i class="fa-solid fa-bullseye"></i></div>
                                    <div class="icon-item" title="Stopwatch" onclick="editor.addIcon('\uf2f2','stopwatch')"><i class="fa-solid fa-stopwatch"></i></div>
                                    <div class="icon-item" title="Person Running" onclick="editor.addIcon('\uf70c','person-running')"><i class="fa-solid fa-person-running"></i></div>
                                    <div class="icon-item" title="Flag" onclick="editor.addIcon('\uf024','flag')"><i class="fa-solid fa-flag"></i></div>
                                    <div class="icon-item" title="Flag Checkered" onclick="editor.addIcon('\uf11e','flag-checkered')"><i class="fa-solid fa-flag-checkered"></i></div>
                                    <div class="icon-item" title="Hand Fist" onclick="editor.addIcon('\uf6de','hand-fist')"><i class="fa-solid fa-hand-fist"></i></div>
                                    <div class="icon-item" title="Ranking Star" onclick="editor.addIcon('\ue561','ranking-star')"><i class="fa-solid fa-ranking-star"></i></div>
                                    <div class="icon-item" title="Volleyball" onclick="editor.addIcon('\uf45f','volleyball')"><i class="fa-solid fa-volleyball"></i></div>
                                </div>
                            </div>
                            {{-- Awards & Trophies --}}
                            <div class="icon-category" data-category="awards">
                                <div class="text-[10px] text-gray-500 uppercase tracking-wider mb-1 mt-2 px-1">Awards & Trophies</div>
                                <div class="grid grid-cols-5 gap-1">
                                    <div class="icon-item" title="Trophy" onclick="editor.addIcon('\uf091','trophy')"><i class="fa-solid fa-trophy"></i></div>
                                    <div class="icon-item" title="Medal" onclick="editor.addIcon('\uf5a2','medal')"><i class="fa-solid fa-medal"></i></div>
                                    <div class="icon-item" title="Award" onclick="editor.addIcon('\uf559','award')"><i class="fa-solid fa-award"></i></div>
                                    <div class="icon-item" title="Star" onclick="editor.addIcon('\uf005','star')"><i class="fa-solid fa-star"></i></div>
                                    <div class="icon-item" title="Crown" onclick="editor.addIcon('\uf521','crown')"><i class="fa-solid fa-crown"></i></div>
                                    <div class="icon-item" title="Certificate" onclick="editor.addIcon('\uf0a3','certificate')"><i class="fa-solid fa-certificate"></i></div>
                                    <div class="icon-item" title="Gem" onclick="editor.addIcon('\uf3a5','gem')"><i class="fa-solid fa-gem"></i></div>
                                    <div class="icon-item" title="Shield" onclick="editor.addIcon('\uf132','shield')"><i class="fa-solid fa-shield"></i></div>
                                    <div class="icon-item" title="Shield Halved" onclick="editor.addIcon('\uf3ed','shield-halved')"><i class="fa-solid fa-shield-halved"></i></div>
                                    <div class="icon-item" title="Thumbs Up" onclick="editor.addIcon('\uf164','thumbs-up')"><i class="fa-solid fa-thumbs-up"></i></div>
                                </div>
                            </div>
                            {{-- People & Teams --}}
                            <div class="icon-category" data-category="people">
                                <div class="text-[10px] text-gray-500 uppercase tracking-wider mb-1 mt-2 px-1">People & Teams</div>
                                <div class="grid grid-cols-5 gap-1">
                                    <div class="icon-item" title="People Group" onclick="editor.addIcon('\ue533','people-group')"><i class="fa-solid fa-people-group"></i></div>
                                    <div class="icon-item" title="Users" onclick="editor.addIcon('\uf0c0','users')"><i class="fa-solid fa-users"></i></div>
                                    <div class="icon-item" title="User" onclick="editor.addIcon('\uf007','user')"><i class="fa-solid fa-user"></i></div>
                                    <div class="icon-item" title="Hands Clapping" onclick="editor.addIcon('\ue1a8','hands-clapping')"><i class="fa-solid fa-hands-clapping"></i></div>
                                    <div class="icon-item" title="Hand Fist" onclick="editor.addIcon('\uf6de','hand-fist-raised')"><i class="fa-solid fa-hand-back-fist"></i></div>
                                </div>
                            </div>
                            {{-- Decorative --}}
                            <div class="icon-category" data-category="decorative">
                                <div class="text-[10px] text-gray-500 uppercase tracking-wider mb-1 mt-2 px-1">Decorative</div>
                                <div class="grid grid-cols-5 gap-1">
                                    <div class="icon-item" title="Fire" onclick="editor.addIcon('\uf06d','fire')"><i class="fa-solid fa-fire"></i></div>
                                    <div class="icon-item" title="Fire Flame" onclick="editor.addIcon('\uf7e4','fire-flame-curved')"><i class="fa-solid fa-fire-flame-curved"></i></div>
                                    <div class="icon-item" title="Bolt" onclick="editor.addIcon('\uf0e7','bolt')"><i class="fa-solid fa-bolt"></i></div>
                                    <div class="icon-item" title="Bolt Lightning" onclick="editor.addIcon('\ue0b7','bolt-lightning')"><i class="fa-solid fa-bolt-lightning"></i></div>
                                    <div class="icon-item" title="Burst" onclick="editor.addIcon('\ue4dc','burst')"><i class="fa-solid fa-burst"></i></div>
                                    <div class="icon-item" title="Explosion" onclick="editor.addIcon('\ue4e9','explosion')"><i class="fa-solid fa-explosion"></i></div>
                                    <div class="icon-item" title="Wand Sparkles" onclick="editor.addIcon('\uf72b','wand-sparkles')"><i class="fa-solid fa-wand-sparkles"></i></div>
                                    <div class="icon-item" title="Sun" onclick="editor.addIcon('\uf185','sun')"><i class="fa-solid fa-sun"></i></div>
                                    <div class="icon-item" title="Moon" onclick="editor.addIcon('\uf186','moon')"><i class="fa-solid fa-moon"></i></div>
                                    <div class="icon-item" title="Heart" onclick="editor.addIcon('\uf004','heart')"><i class="fa-solid fa-heart"></i></div>
                                </div>
                            </div>
                            {{-- Arrows & Symbols --}}
                            <div class="icon-category" data-category="symbols">
                                <div class="text-[10px] text-gray-500 uppercase tracking-wider mb-1 mt-2 px-1">Arrows & Symbols</div>
                                <div class="grid grid-cols-5 gap-1">
                                    <div class="icon-item" title="Arrow Right" onclick="editor.addIcon('\uf061','arrow-right')"><i class="fa-solid fa-arrow-right"></i></div>
                                    <div class="icon-item" title="Arrow Left" onclick="editor.addIcon('\uf060','arrow-left')"><i class="fa-solid fa-arrow-left"></i></div>
                                    <div class="icon-item" title="Arrows Rotate" onclick="editor.addIcon('\uf021','arrows-rotate')"><i class="fa-solid fa-arrows-rotate"></i></div>
                                    <div class="icon-item" title="Angles Right" onclick="editor.addIcon('\uf101','angles-right')"><i class="fa-solid fa-angles-right"></i></div>
                                    <div class="icon-item" title="Circle Check" onclick="editor.addIcon('\uf058','circle-check')"><i class="fa-solid fa-circle-check"></i></div>
                                    <div class="icon-item" title="Circle Xmark" onclick="editor.addIcon('\uf057','circle-xmark')"><i class="fa-solid fa-circle-xmark"></i></div>
                                    <div class="icon-item" title="Hashtag" onclick="editor.addIcon('\u0023','hashtag')"><i class="fa-solid fa-hashtag"></i></div>
                                    <div class="icon-item" title="At" onclick="editor.addIcon('\u0040','at')"><i class="fa-solid fa-at"></i></div>
                                    <div class="icon-item" title="Quote Left" onclick="editor.addIcon('\uf10d','quote-left')"><i class="fa-solid fa-quote-left"></i></div>
                                    <div class="icon-item" title="Quote Right" onclick="editor.addIcon('\uf10e','quote-right')"><i class="fa-solid fa-quote-right"></i></div>
                                </div>
                            </div>
                            {{-- Info & Schedule --}}
                            <div class="icon-category" data-category="info">
                                <div class="text-[10px] text-gray-500 uppercase tracking-wider mb-1 mt-2 px-1">Info & Schedule</div>
                                <div class="grid grid-cols-5 gap-1">
                                    <div class="icon-item" title="Calendar" onclick="editor.addIcon('\uf133','calendar')"><i class="fa-solid fa-calendar"></i></div>
                                    <div class="icon-item" title="Calendar Days" onclick="editor.addIcon('\uf073','calendar-days')"><i class="fa-solid fa-calendar-days"></i></div>
                                    <div class="icon-item" title="Clock" onclick="editor.addIcon('\uf017','clock')"><i class="fa-solid fa-clock"></i></div>
                                    <div class="icon-item" title="Location" onclick="editor.addIcon('\uf3c5','location-dot')"><i class="fa-solid fa-location-dot"></i></div>
                                    <div class="icon-item" title="Map Pin" onclick="editor.addIcon('\uf276','map-pin')"><i class="fa-solid fa-map-pin"></i></div>
                                    <div class="icon-item" title="Phone" onclick="editor.addIcon('\uf095','phone')"><i class="fa-solid fa-phone"></i></div>
                                    <div class="icon-item" title="Envelope" onclick="editor.addIcon('\uf0e0','envelope')"><i class="fa-solid fa-envelope"></i></div>
                                    <div class="icon-item" title="Globe" onclick="editor.addIcon('\uf0ac','globe')"><i class="fa-solid fa-globe"></i></div>
                                    <div class="icon-item" title="Info Circle" onclick="editor.addIcon('\uf05a','circle-info')"><i class="fa-solid fa-circle-info"></i></div>
                                    <div class="icon-item" title="Camera" onclick="editor.addIcon('\uf030','camera')"><i class="fa-solid fa-camera"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($type === 'point_table')
                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Table Area</div>
                        <div class="draggable-item" draggable="true" data-type="tableArea">
                            <div class="icon" style="background: linear-gradient(135deg, #0ea5e9, #2563eb);">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M3 6h18M3 18h18M8 6v12M16 6v12"/></svg>
                            </div>
                            <div class="info">
                                <div class="name">Point Table Area</div>
                                <div class="type">Table</div>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($type === 'fixtures_poster')
                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Fixture Area</div>
                        <div class="draggable-item" style="cursor:pointer;" onclick="editor.addFixtureArea()">
                            <div class="icon" style="background: linear-gradient(135deg, #14b8a6, #06b6d4);">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <div class="info"><div class="name">Fixtures List</div><div class="type">Fixture Area</div></div>
                        </div>
                    </div>
                    @endif
                    @if($type === 'match_summary')
                    <div class="sidebar-section">
                        <div class="sidebar-section-title">Scorecard Tables</div>
                        <div class="draggable-item" style="cursor:pointer;" onclick="editor.addScorecardTable('batting_table_a', 'batting', 'a')">
                            <div class="icon" style="background: linear-gradient(135deg, #0ea5e9, #2563eb);">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M3 6h18M3 18h18"/></svg>
                            </div>
                            <div class="info"><div class="name">Team A Batting</div><div class="type">Scorecard</div></div>
                        </div>
                        <div class="draggable-item" style="cursor:pointer;" onclick="editor.addScorecardTable('batting_table_b', 'batting', 'b')">
                            <div class="icon" style="background: linear-gradient(135deg, #0ea5e9, #2563eb);">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M3 6h18M3 18h18"/></svg>
                            </div>
                            <div class="info"><div class="name">Team B Batting</div><div class="type">Scorecard</div></div>
                        </div>
                        <div class="draggable-item" style="cursor:pointer;" onclick="editor.addScorecardTable('bowling_table_a', 'bowling', 'a')">
                            <div class="icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M3 6h18M3 18h18"/></svg>
                            </div>
                            <div class="info"><div class="name">Team A Bowling</div><div class="type">Scorecard</div></div>
                        </div>
                        <div class="draggable-item" style="cursor:pointer;" onclick="editor.addScorecardTable('bowling_table_b', 'bowling', 'b')">
                            <div class="icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M3 6h18M3 18h18"/></svg>
                            </div>
                            <div class="info"><div class="name">Team B Bowling</div><div class="type">Scorecard</div></div>
                        </div>
                    </div>
                    @endif

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
                    <div id="propPlaceholderInfo" class="hidden mb-2 px-2 py-1.5 rounded bg-indigo-900/40 border border-indigo-700/50">
                        <span class="text-[10px] uppercase tracking-wider text-indigo-400 font-semibold">Placeholder</span>
                        <div id="propPlaceholderName" class="text-xs text-indigo-200 font-mono mt-0.5"></div>
                    </div>
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
                            <option value="Bangers">Bangers</option>
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
                                <option value="100">Thin</option>
                                <option value="300">Light</option>
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
                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Text Transform</label>
                            <select id="propTextTransform" class="prop-input" onchange="editor.setTextTransform(this.value)">
                                <option value="none">Normal</option>
                                <option value="uppercase">UPPERCASE</option>
                                <option value="capitalize">Capitalize</option>
                                <option value="lowercase">lowercase</option>
                            </select>
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Style</label>
                            <div class="flex gap-1">
                                <button id="propBoldBtn" class="prop-btn prop-btn-secondary flex-1 font-bold" onclick="editor.toggleBold()" title="Bold">B</button>
                                <button id="propItalicBtn" class="prop-btn prop-btn-secondary flex-1" onclick="editor.toggleItalic()" style="font-style:italic;" title="Italic">I</button>
                                <button id="propUnderlineBtn" class="prop-btn prop-btn-secondary flex-1" onclick="editor.toggleUnderline()" style="text-decoration:underline;" title="Underline">U</button>
                                <button id="propLinethroughBtn" class="prop-btn prop-btn-secondary flex-1" onclick="editor.toggleLinethrough()" style="text-decoration:line-through;" title="Strikethrough">S</button>
                            </div>
                        </div>
                    </div>
                    <div class="prop-group">
                        <label class="prop-label">Skew <span id="skewValue" class="text-xs text-gray-500">0°</span></label>
                        <input type="range" id="propSkewX" class="prop-slider" min="-45" max="45" value="0" oninput="editor.updateSkew(parseInt(this.value))">
                        <div class="flex justify-between mt-1">
                            <button class="text-[10px] text-gray-500 hover:text-white" onclick="editor.updateSkew(-15)">-15°</button>
                            <button class="text-[10px] text-gray-500 hover:text-white" onclick="editor.updateSkew(0)">Reset</button>
                            <button class="text-[10px] text-gray-500 hover:text-white" onclick="editor.updateSkew(15)">+15°</button>
                        </div>
                    </div>
                    <div class="prop-group">
                        <label class="prop-label">Text Shadow</label>
                        <div class="prop-input-row">
                            <div class="prop-group">
                                <label class="prop-label text-[10px]">Offset X</label>
                                <input type="number" id="propShadowX" class="prop-input" min="-20" max="20" value="0" onchange="editor.updateShadow()">
                            </div>
                            <div class="prop-group">
                                <label class="prop-label text-[10px]">Offset Y</label>
                                <input type="number" id="propShadowY" class="prop-input" min="-20" max="20" value="0" onchange="editor.updateShadow()">
                            </div>
                            <div class="prop-group">
                                <label class="prop-label text-[10px]">Blur</label>
                                <input type="number" id="propShadowBlur" class="prop-input" min="0" max="30" value="0" onchange="editor.updateShadow()">
                            </div>
                            <div class="prop-group">
                                <label class="prop-label text-[10px]">Color</label>
                                <input type="color" id="propShadowColor" class="color-preview" value="#000000" onchange="editor.updateShadow()" style="width:100%;height:32px;">
                            </div>
                        </div>
                    </div>
                    <div class="prop-group">
                        <label class="prop-label">Text Stroke</label>
                        <div class="prop-input-row">
                            <div class="prop-group">
                                <label class="prop-label text-[10px]">Color</label>
                                <input type="color" id="propTextStroke" class="color-preview" value="#000000" onchange="editor.updateTextStroke(this.value)" style="width:100%;height:32px;">
                            </div>
                            <div class="prop-group">
                                <label class="prop-label text-[10px]">Width</label>
                                <input type="number" id="propTextStrokeWidth" class="prop-input" min="0" max="10" value="0" onchange="editor.updateTextStrokeWidth(parseInt(this.value))">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Icon Properties --}}
            <div id="iconPropertiesPanel" class="hidden">
                <div class="prop-section">
                    <div class="prop-section-title">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                        Icon Properties
                    </div>
                    <div class="prop-group">
                        <label class="prop-label">Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="propIconColor" class="color-preview" onchange="editor.updateIcon('fill', this.value)">
                            <div class="color-presets">
                                <div class="color-preset" style="background:#fff" onclick="editor.updateIcon('fill','#ffffff')"></div>
                                <div class="color-preset" style="background:#000" onclick="editor.updateIcon('fill','#000000')"></div>
                                <div class="color-preset" style="background:#FFD700" onclick="editor.updateIcon('fill','#FFD700')"></div>
                                <div class="color-preset" style="background:#EF4444" onclick="editor.updateIcon('fill','#EF4444')"></div>
                                <div class="color-preset" style="background:#3B82F6" onclick="editor.updateIcon('fill','#3B82F6')"></div>
                                <div class="color-preset" style="background:#10B981" onclick="editor.updateIcon('fill','#10B981')"></div>
                                <div class="color-preset" style="background:#F59E0B" onclick="editor.updateIcon('fill','#F59E0B')"></div>
                                <div class="color-preset" style="background:#8B5CF6" onclick="editor.updateIcon('fill','#8B5CF6')"></div>
                                <div class="color-preset" style="background:#EC4899" onclick="editor.updateIcon('fill','#EC4899')"></div>
                                <div class="color-preset" style="background:#06B6D4" onclick="editor.updateIcon('fill','#06B6D4')"></div>
                            </div>
                        </div>
                    </div>
                    <div class="prop-group">
                        <label class="prop-label">Size</label>
                        <input type="number" id="propIconSize" class="prop-input" min="16" max="400" onchange="editor.updateIcon('fontSize', parseInt(this.value))">
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
                            <div class="prop-input-row" id="borderRadiusLinked">
                                <input type="number" id="propShapeBorderRadius" class="prop-input" min="0" max="100" value="8" onchange="editor.updateShapeBorderRadius(parseInt(this.value))">
                                <button id="borderRadiusLinkBtn" class="prop-btn prop-btn-primary" onclick="editor.toggleBorderRadiusLink()" title="Unlink corners" style="padding:4px 8px;font-size:11px;">&#x1f517;</button>
                            </div>
                            <div class="prop-input-row hidden" id="borderRadiusUnlinked" style="flex-wrap:wrap;gap:4px;">
                                <div class="prop-group" style="flex:1;min-width:45%;">
                                    <label class="prop-label text-[10px]">TL</label>
                                    <input type="number" id="propBorderRadiusTL" class="prop-input" min="0" max="100" value="0" onchange="editor.updateShapeBorderRadiusCorners()">
                                </div>
                                <div class="prop-group" style="flex:1;min-width:45%;">
                                    <label class="prop-label text-[10px]">TR</label>
                                    <input type="number" id="propBorderRadiusTR" class="prop-input" min="0" max="100" value="0" onchange="editor.updateShapeBorderRadiusCorners()">
                                </div>
                                <div class="prop-group" style="flex:1;min-width:45%;">
                                    <label class="prop-label text-[10px]">BL</label>
                                    <input type="number" id="propBorderRadiusBL" class="prop-input" min="0" max="100" value="0" onchange="editor.updateShapeBorderRadiusCorners()">
                                </div>
                                <div class="prop-group" style="flex:1;min-width:45%;">
                                    <label class="prop-label text-[10px]">BR</label>
                                    <input type="number" id="propBorderRadiusBR" class="prop-input" min="0" max="100" value="0" onchange="editor.updateShapeBorderRadiusCorners()">
                                </div>
                                <button class="prop-btn prop-btn-secondary" onclick="editor.toggleBorderRadiusLink()" title="Link corners" style="padding:4px 8px;font-size:10px;width:100%;">Link All Corners</button>
                                <small style="color:#6b7280;font-size:9px;display:block;margin-top:4px;">Per-corner radii apply in final render. Canvas shows average.</small>
                            </div>
                        </div>
                    </div>
                    <div class="prop-group">
                        <label class="prop-label">Shape Shadow</label>
                        <div class="prop-input-row">
                            <div class="prop-group">
                                <label class="prop-label text-[10px]">X</label>
                                <input type="number" id="propShapeShadowX" class="prop-input" min="-20" max="20" value="0" onchange="editor.updateShapeShadow()">
                            </div>
                            <div class="prop-group">
                                <label class="prop-label text-[10px]">Y</label>
                                <input type="number" id="propShapeShadowY" class="prop-input" min="-20" max="20" value="0" onchange="editor.updateShapeShadow()">
                            </div>
                            <div class="prop-group">
                                <label class="prop-label text-[10px]">Blur</label>
                                <input type="number" id="propShapeShadowBlur" class="prop-input" min="0" max="30" value="0" onchange="editor.updateShapeShadow()">
                            </div>
                            <div class="prop-group">
                                <label class="prop-label text-[10px]">Color</label>
                                <input type="color" id="propShapeShadowColor" class="color-preview" value="#000000" onchange="editor.updateShapeShadow()" style="width:100%;height:32px;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Table Properties --}}
            <div id="tablePropertiesPanel" class="hidden">
                <div class="prop-section">
                    <div class="prop-section-title">Table Colors</div>
                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Header BG</label>
                            <input type="color" id="propTableHeaderBg" class="color-preview" value="#1e40af" onchange="editor.updateTableConfig('headerBg', this.value)">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Header Text</label>
                            <input type="color" id="propTableHeaderText" class="color-preview" value="#ffffff" onchange="editor.updateTableConfig('headerText', this.value)">
                        </div>
                    </div>
                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Even Row</label>
                            <input type="color" id="propTableEvenRowBg" class="color-preview" value="#1e293b" onchange="editor.updateTableConfig('evenRowBg', this.value)">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Odd Row</label>
                            <input type="color" id="propTableOddRowBg" class="color-preview" value="#334155" onchange="editor.updateTableConfig('oddRowBg', this.value)">
                        </div>
                    </div>
                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Qualified BG</label>
                            <input type="color" id="propTableQualifiedBg" class="color-preview" value="#064e3b" onchange="editor.updateTableConfig('qualifiedBg', this.value)">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Text Color</label>
                            <input type="color" id="propTableTextColor" class="color-preview" value="#ffffff" onchange="editor.updateTableConfig('textColor', this.value)">
                        </div>
                    </div>
                    <div class="prop-group">
                        <label class="prop-label">Points Color</label>
                        <input type="color" id="propTablePointsColor" class="color-preview" value="#FFD700" onchange="editor.updateTableConfig('pointsColor', this.value)">
                    </div>
                </div>
                <div class="prop-section">
                    <div class="prop-section-title">Table Layout</div>
                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Font Size</label>
                            <input type="number" id="propTableFontSize" class="prop-input" min="10" max="32" value="16" onchange="editor.updateTableConfig('fontSize', parseInt(this.value))">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Row Height</label>
                            <input type="number" id="propTableRowHeight" class="prop-input" min="30" max="120" value="80" onchange="editor.updateTableConfig('rowHeight', parseInt(this.value))">
                        </div>
                    </div>
                    <div class="prop-group" style="margin-top:12px;">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="propTableShowLogo" checked onchange="editor.updateTableConfig('showTeamLogo', this.checked)" class="rounded border-gray-600 bg-gray-700 text-indigo-500">
                            <span class="prop-label" style="margin:0">Show Team Logo</span>
                        </label>
                    </div>
                    <div class="prop-group">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="propTableShowNRR" checked onchange="editor.updateTableConfig('showNRR', this.checked)" class="rounded border-gray-600 bg-gray-700 text-indigo-500">
                            <span class="prop-label" style="margin:0">Show NRR</span>
                        </label>
                    </div>
                    <div class="prop-group">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="propTableShowLegend" checked onchange="editor.updateTableConfig('showLegend', this.checked)" class="rounded border-gray-600 bg-gray-700 text-indigo-500">
                            <span class="prop-label" style="margin:0">Show Legend</span>
                        </label>
                    </div>
                </div>
                <div class="prop-section">
                    <div class="prop-section-title">Style Presets</div>
                    <div class="grid grid-cols-2 gap-2">
                        <button onclick="editor.applyTablePreset('dark')" class="prop-btn prop-btn-secondary text-xs justify-center" style="background:#0f172a;color:#fff;border-color:#1e40af;">Dark Blue</button>
                        <button onclick="editor.applyTablePreset('light')" class="prop-btn prop-btn-secondary text-xs justify-center" style="background:#f8fafc;color:#334155;border-color:#e2e8f0;">Light</button>
                        <button onclick="editor.applyTablePreset('ipl')" class="prop-btn prop-btn-secondary text-xs justify-center" style="background:#1a0533;color:#fff;border-color:#7c3aed;">IPL Style</button>
                        <button onclick="editor.applyTablePreset('minimal')" class="prop-btn prop-btn-secondary text-xs justify-center" style="background:#18181b;color:#a1a1aa;border-color:#3f3f46;">Minimal</button>
                    </div>
                </div>
            </div>

            {{-- Scorecard Table Properties --}}
            <div id="scorecardPropertiesPanel" class="hidden">
                <div class="prop-section">
                    <div class="prop-section-title">Scorecard Colors</div>
                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Header BG</label>
                            <input type="color" id="propScHeaderBg" class="color-preview" value="#1e40af" onchange="editor.updateScorecardConfig('headerBg', this.value)">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Header Text</label>
                            <input type="color" id="propScHeaderText" class="color-preview" value="#ffffff" onchange="editor.updateScorecardConfig('headerText', this.value)">
                        </div>
                    </div>
                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Row BG</label>
                            <input type="color" id="propScRowBg" class="color-preview" value="#1e293b" onchange="editor.updateScorecardConfig('rowBg', this.value)">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Alt Row BG</label>
                            <input type="color" id="propScAltRowBg" class="color-preview" value="#334155" onchange="editor.updateScorecardConfig('altRowBg', this.value)">
                        </div>
                    </div>
                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Text Color</label>
                            <input type="color" id="propScTextColor" class="color-preview" value="#ffffff" onchange="editor.updateScorecardConfig('textColor', this.value)">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Accent Color</label>
                            <input type="color" id="propScAccentColor" class="color-preview" value="#FFD700" onchange="editor.updateScorecardConfig('accentColor', this.value)">
                        </div>
                    </div>
                </div>
                <div class="prop-section">
                    <div class="prop-section-title">Layout</div>
                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Font Size</label>
                            <input type="number" id="propScFontSize" class="prop-input" min="10" max="24" value="14" onchange="editor.updateScorecardConfig('fontSize', parseInt(this.value))">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Row Height</label>
                            <input type="number" id="propScRowHeight" class="prop-input" min="25" max="60" value="40" onchange="editor.updateScorecardConfig('rowHeight', parseInt(this.value))">
                        </div>
                    </div>
                    <div class="prop-group">
                        <label class="prop-label">Max Rows</label>
                        <input type="number" id="propScMaxRows" class="prop-input" min="2" max="5" value="3" onchange="editor.updateScorecardConfig('maxRows', parseInt(this.value))">
                    </div>
                    <div class="prop-group" style="margin-top:8px;">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="propScTransparentBg" onchange="editor.updateScorecardConfig('transparentBg', this.checked)" class="rounded border-gray-600 bg-gray-700 text-indigo-500">
                            <span class="prop-label" style="margin:0">Transparent Background</span>
                        </label>
                    </div>
                </div>
                <div class="prop-section">
                    <div class="prop-section-title">Style Presets</div>
                    <div class="grid grid-cols-2 gap-2">
                        <button onclick="editor.applyScorecardPreset('dark')" class="prop-btn prop-btn-secondary text-xs justify-center" style="background:#0f172a;color:#fff;border-color:#1e40af;">Dark</button>
                        <button onclick="editor.applyScorecardPreset('light')" class="prop-btn prop-btn-secondary text-xs justify-center" style="background:#f8fafc;color:#334155;border-color:#e2e8f0;">Light</button>
                        <button onclick="editor.applyScorecardPreset('ipl')" class="prop-btn prop-btn-secondary text-xs justify-center" style="background:#1a0533;color:#fff;border-color:#7c3aed;">IPL</button>
                    </div>
                </div>
            </div>

            {{-- Fixture Area Properties --}}
            <div id="fixturePropertiesPanel" class="hidden">
                <div class="prop-section">
                    <div class="prop-section-title">Design Layout</div>
                    <div class="grid grid-cols-2 gap-2" id="propFxLayoutSelector">
                        <button type="button" onclick="editor.updateFixtureConfig('layout', 'row')" data-layout="row" class="prop-btn text-xs justify-center flex items-center gap-1 transition-all" style="padding:8px 6px;">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                            Row List
                        </button>
                        <button type="button" onclick="editor.updateFixtureConfig('layout', 'card')" data-layout="card" class="prop-btn text-xs justify-center flex items-center gap-1 transition-all" style="padding:8px 6px;">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zM14 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/></svg>
                            Card Grid
                        </button>
                    </div>
                </div>
                <div class="prop-section">
                    <div class="prop-section-title">Fixture Colors</div>
                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Row BG</label>
                            <input type="color" id="propFxRowBg" class="color-preview" value="#1e293b" onchange="editor.updateFixtureConfig('rowBg', this.value)">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Alt Row BG</label>
                            <input type="color" id="propFxAltRowBg" class="color-preview" value="#293548" onchange="editor.updateFixtureConfig('altRowBg', this.value)">
                        </div>
                    </div>
                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Text Color</label>
                            <input type="color" id="propFxTextColor" class="color-preview" value="#ffffff" onchange="editor.updateFixtureConfig('textColor', this.value)">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Accent Color</label>
                            <input type="color" id="propFxAccentColor" class="color-preview" value="#FFD700" onchange="editor.updateFixtureConfig('accentColor', this.value)">
                        </div>
                    </div>
                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Muted Text</label>
                            <input type="color" id="propFxMutedColor" class="color-preview" value="#94a3b8" onchange="editor.updateFixtureConfig('mutedColor', this.value)">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Divider</label>
                            <input type="color" id="propFxDividerColor" class="color-preview" value="#d4a843" onchange="editor.updateFixtureConfig('dividerColor', this.value)">
                        </div>
                    </div>
                    <div class="prop-input-row" id="propFxCardColorGroup" style="display:none;">
                        <div class="prop-group">
                            <label class="prop-label">Header BG</label>
                            <input type="color" id="propFxHeaderBg" class="color-preview" value="#1e40af" onchange="editor.updateFixtureConfig('headerBg', this.value)">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Header Text</label>
                            <input type="color" id="propFxHeaderText" class="color-preview" value="#ffffff" onchange="editor.updateFixtureConfig('headerText', this.value)">
                        </div>
                    </div>
                </div>
                <div class="prop-section">
                    <div class="prop-section-title">Fixture Layout</div>
                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Font Size</label>
                            <input type="number" id="propFxFontSize" class="prop-input" min="8" max="32" value="16" onchange="editor.updateFixtureConfig('fontSize', parseInt(this.value))">
                        </div>
                        <div class="prop-group" id="propFxRowHeightGroup">
                            <label class="prop-label">Row Height</label>
                            <input type="number" id="propFxRowHeight" class="prop-input" min="40" max="200" value="100" onchange="editor.updateFixtureConfig('rowHeight', parseInt(this.value))">
                        </div>
                    </div>
                    <div class="prop-input-row">
                        <div class="prop-group">
                            <label class="prop-label">Max Fixtures</label>
                            <input type="number" id="propFxMaxRows" class="prop-input" min="1" max="20" value="5" onchange="editor.updateFixtureConfig('maxRows', parseInt(this.value))">
                        </div>
                        <div class="prop-group" id="propFxCardColumnsGroup" style="display:none;">
                            <label class="prop-label">Columns</label>
                            <select id="propFxCardColumns" class="prop-input" onchange="editor.updateFixtureConfig('cardColumns', parseInt(this.value))">
                                <option value="2">2 Columns</option>
                                <option value="3">3 Columns</option>
                                <option value="4">4 Columns</option>
                            </select>
                        </div>
                    </div>
                    <div class="prop-group" style="margin-top:12px;">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="propFxShowTeamLogo" checked onchange="editor.updateFixtureConfig('showTeamLogo', this.checked)" class="rounded border-gray-600 bg-gray-700 text-indigo-500">
                            <span class="prop-label" style="margin:0">Show Team Logo</span>
                        </label>
                    </div>
                    <div class="prop-group">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="propFxUseShortName" onchange="editor.updateFixtureConfig('useShortName', this.checked)" class="rounded border-gray-600 bg-gray-700 text-indigo-500">
                            <span class="prop-label" style="margin:0">Use Short Name</span>
                        </label>
                    </div>
                    <div class="prop-group">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="propFxShowMatchNum" onchange="editor.updateFixtureConfig('showMatchNum', this.checked)" class="rounded border-gray-600 bg-gray-700 text-indigo-500">
                            <span class="prop-label" style="margin:0">Show Match Number</span>
                        </label>
                    </div>
                    <div class="prop-group">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="propFxShowVenue" checked onchange="editor.updateFixtureConfig('showVenue', this.checked)" class="rounded border-gray-600 bg-gray-700 text-indigo-500">
                            <span class="prop-label" style="margin:0">Show Venue</span>
                        </label>
                    </div>
                    <div class="prop-group">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="propFxShowDateTime" checked onchange="editor.updateFixtureConfig('showDateTime', this.checked)" class="rounded border-gray-600 bg-gray-700 text-indigo-500">
                            <span class="prop-label" style="margin:0">Show Date & Time</span>
                        </label>
                    </div>
                    <div class="prop-group">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="propFxTransparentBg" onchange="editor.updateFixtureConfig('transparentBg', this.checked)" class="rounded border-gray-600 bg-gray-700 text-indigo-500">
                            <span class="prop-label" style="margin:0">Transparent Background</span>
                        </label>
                    </div>
                    <div class="prop-group">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="propFxShowBorder" onchange="editor.updateFixtureConfig('showBorder', this.checked)" class="rounded border-gray-600 bg-gray-700 text-indigo-500">
                            <span class="prop-label" style="margin:0">Show Border</span>
                        </label>
                    </div>
                    <div class="prop-input-row" style="margin-top:8px;">
                        <div class="prop-group">
                            <label class="prop-label">Row Gap</label>
                            <input type="number" id="propFxRowGap" class="prop-input" min="0" max="30" value="4" onchange="editor.updateFixtureConfig('rowGap', parseInt(this.value))">
                        </div>
                        <div class="prop-group">
                            <label class="prop-label">Padding</label>
                            <input type="number" id="propFxRowPadding" class="prop-input" min="0" max="40" value="16" onchange="editor.updateFixtureConfig('rowPadding', parseInt(this.value))">
                        </div>
                    </div>
                </div>
                <div class="prop-section">
                    <div class="prop-section-title">Row Effect</div>
                    <div class="grid grid-cols-4 gap-2" id="propFxCardStyleSelector">
                        <button type="button" onclick="editor.updateFixtureConfig('cardStyle', 'flat')" data-style="flat" class="prop-btn text-xs justify-center transition-all" style="padding:6px 4px;">Flat</button>
                        <button type="button" onclick="editor.updateFixtureConfig('cardStyle', 'gradient')" data-style="gradient" class="prop-btn text-xs justify-center transition-all" style="padding:6px 4px;">Gradient</button>
                        <button type="button" onclick="editor.updateFixtureConfig('cardStyle', 'stripe')" data-style="stripe" class="prop-btn text-xs justify-center transition-all" style="padding:6px 4px;">Stripe</button>
                        <button type="button" onclick="editor.updateFixtureConfig('cardStyle', 'glow')" data-style="glow" class="prop-btn text-xs justify-center transition-all" style="padding:6px 4px;">Glow</button>
                    </div>
                </div>
                <div class="prop-section">
                    <div class="prop-section-title">Preset Layouts</div>
                    <div class="grid grid-cols-3 gap-2">
                        <button onclick="editor.applyFixturePreset('classic')" class="prop-btn prop-btn-secondary text-xs justify-center" style="background:#0f172a;color:#d4a843;border-color:#1e40af;">Classic</button>
                        <button onclick="editor.applyFixturePreset('modern')" class="prop-btn prop-btn-secondary text-xs justify-center" style="background:#1e293b;color:#fff;border-color:#3b82f6;">Modern</button>
                        <button onclick="editor.applyFixturePreset('minimal')" class="prop-btn prop-btn-secondary text-xs justify-center" style="background:#18181b;color:#a1a1aa;border-color:#3f3f46;">Minimal</button>
                        <button onclick="editor.applyFixturePreset('ipl')" class="prop-btn prop-btn-secondary text-xs justify-center" style="background:#1a0533;color:#f59e0b;border-color:#7c3aed;">IPL</button>
                        <button onclick="editor.applyFixturePreset('cardDark')" class="prop-btn prop-btn-secondary text-xs justify-center" style="background:#0a1628;color:#fff;border-color:#334155;">Card Dark</button>
                        <button onclick="editor.applyFixturePreset('cardLight')" class="prop-btn prop-btn-secondary text-xs justify-center" style="background:#f8fafc;color:#334155;border-color:#e2e8f0;">Card Light</button>
                        <button onclick="editor.applyFixturePreset('neonGlow')" class="prop-btn prop-btn-secondary text-xs justify-center" style="background:#0a0a0a;color:#22d3ee;border-color:#f43f5e;">Neon Glow</button>
                        <button onclick="editor.applyFixturePreset('royal')" class="prop-btn prop-btn-secondary text-xs justify-center" style="background:#1e1b4b;color:#fbbf24;border-color:#4338ca;">Royal</button>
                        <button onclick="editor.applyFixturePreset('tournament')" class="prop-btn prop-btn-secondary text-xs justify-center" style="background:#064e3b;color:#FFD700;border-color:#047857;">Tournament</button>
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
                <optgroup label="Instagram">
                    <option value="1080x1080">Post Square (1080x1080)</option>
                    <option value="1080x1350">Post Portrait 4:5 (1080x1350)</option>
                    <option value="1080x566">Post Landscape 1.91:1 (1080x566)</option>
                    <option value="1080x1920">Story / Reels 9:16 (1080x1920)</option>
                </optgroup>
                <optgroup label="WhatsApp">
                    <option value="1080x1920">Status 9:16 (1080x1920)</option>
                    <option value="800x800">DP / Group Icon (800x800)</option>
                </optgroup>
                <optgroup label="YouTube">
                    <option value="1080x1920">Shorts 9:16 (1080x1920)</option>
                    <option value="1280x720">Thumbnail HD 16:9 (1280x720)</option>
                    <option value="2560x1440">Banner (2560x1440)</option>
                </optgroup>
                <optgroup label="Facebook">
                    <option value="1200x630">Post Link 1.91:1 (1200x630)</option>
                    <option value="1080x1080">Post Square (1080x1080)</option>
                    <option value="820x312">Cover Photo (820x312)</option>
                </optgroup>
                <optgroup label="Standard">
                    <option value="1920x1080">Full HD 16:9 (1920x1080)</option>
                    <option value="1440x1080">HD 4:3 (1440x1080)</option>
                    <option value="1080x1080">Square 1:1 (1080x1080)</option>
                    <option value="1080x1350">Portrait 4:5 (1080x1350)</option>
                    <option value="1080x1920">Portrait 9:16 (1080x1920)</option>
                </optgroup>
                <optgroup label="Print">
                    <option value="2480x3508">A4 Portrait (2480x3508)</option>
                    <option value="3508x2480">A4 Landscape (3508x2480)</option>
                </optgroup>
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
const placeholderExamples = {
    player_name: 'John Doe',
    jersey_name: 'J. DOE',
    jersey_number: '10',
    team_name: 'Sample Team FC',
    tournament_name: 'Tournament Name',
    team_a_name: 'Royal Strikers',
    team_a_short_name: 'RST',
    team_a_location: 'Dubai',
    team_a_captain_name: 'Captain Alpha',
    team_b_name: 'Thunder Kings',
    team_b_short_name: 'THK',
    team_b_location: 'Abu Dhabi',
    team_b_captain_name: 'Captain Beta',
    team_a_score: '185/4 (20.0)',
    team_b_score: '172/8 (20.0)',
    team_a_score_wickets: '185/4',
    team_b_score_wickets: '172/8',
    team_a_runs: '185',
    team_b_runs: '172',
    team_a_wickets: '4',
    team_b_wickets: '8',
    team_a_overs: '20.0',
    team_b_overs: '20.0',
    team_a_score_overs: '185/4 (20 Ov)',
    team_b_score_overs: '172/8 (20 Ov)',
    match_date: 'May 08, 2026',
    match_date_day: '08',
    match_date_month: 'MAY',
    match_date_weekday: 'FRI',
    match_time: '07:00 PM',
    match_day: 'Friday',
    venue: 'Dubai International Cricket Ground',
    ground_name: 'Ground-2',
    match_stage: 'Group Stage',
    match_number: '1',
    result_summary: 'Royal Strikers won by 13 runs',
    winner_name: 'Royal Strikers',
    win_margin: 'Won by 13 runs',
    toss_result: 'RST won toss, chose to bat',
    man_of_the_match_name: 'Player Name',
    match_details: 'Team A vs Team B',
    player_type: 'All Rounder',
    batting_style: 'Right Handed',
    bowling_style: 'Right Arm Medium',
    award_name: 'Player of the Match',
    achievement_text: '75 runs off 45 balls',
    batting_figures: '59 (36) 9x4 1x6',
    bowling_figures: '4 - 0 - 25 - 2',
    batting_runs: '59',
    batting_balls: '36',
    batting_fours: '9',
    batting_sixes: '1',
    bowling_overs: '4',
    bowling_runs: '25',
    bowling_maidens: '0',
    bowling_wickets: '2',
    description: 'Cricket Tournament',
    location: 'City Sports Complex',
    title: 'Champions',
    season: 'Season 1',
    group_name: 'Group A',
    best_batsman_name: 'Best Batsman',
    best_bowler_name: 'Best Bowler',
};

function getExampleText(placeholder) {
    return placeholderExamples[placeholder] || placeholder.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

const editor = {
    canvas: null,
    history: [],
    historyIndex: -1,
    canvasWidth: {{ $template?->canvas_width ?? 1080 }},
    canvasHeight: {{ $template?->canvas_height ?? 1080 }},
    zoom: 1,
    showGrid: false,
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

        // Preload all Google Fonts before rendering template text
        // (display=swap makes fonts lazy; explicitly loading ensures they're ready)
        const fontFamilies = ['Roboto', 'Open Sans', 'Montserrat', 'Poppins', 'Oswald', 'Bebas Neue', 'Anton', 'Bangers'];
        const fontLoads = fontFamilies.map(f => document.fonts.load(`16px "${f}"`).catch(() => {}));
        Promise.all(fontLoads).then(() => {
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
        });
    },

    setupEvents() {
        this.canvas.on('selection:created', (e) => this.showProperties(e.selected[0]));
        this.canvas.on('selection:updated', (e) => this.showProperties(e.selected[0]));
        this.canvas.on('selection:cleared', () => this.hideProperties());
        this.canvas.on('object:modified', () => { this.saveHistory(); this.updateProperties(); this.updateLayers(); });
        this.canvas.on('object:added', () => this.updateLayers());
        this.canvas.on('object:removed', () => this.updateLayers());

        // Grid rendering
        this.canvas.on('after:render', () => {
            if (!this.showGrid) return;
            const ctx = this.canvas.getContext();
            const w = this.canvasWidth;
            const h = this.canvasHeight;
            const gridSize = 20;
            const zoom = this.canvas.getZoom();
            ctx.save();
            ctx.transform(zoom, 0, 0, zoom, 0, 0);
            for (let x = 0; x <= w; x += gridSize) {
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x, h);
                ctx.strokeStyle = (x % (gridSize * 5) === 0) ? 'rgba(255,255,255,0.15)' : 'rgba(255,255,255,0.06)';
                ctx.lineWidth = 1 / zoom;
                ctx.stroke();
            }
            for (let y = 0; y <= h; y += gridSize) {
                ctx.beginPath();
                ctx.moveTo(0, y);
                ctx.lineTo(w, y);
                ctx.strokeStyle = (y % (gridSize * 5) === 0) ? 'rgba(255,255,255,0.15)' : 'rgba(255,255,255,0.06)';
                ctx.lineWidth = 1 / zoom;
                ctx.stroke();
            }
            ctx.restore();
        });

        // Snap to grid on move
        this.canvas.on('object:moving', (e) => {
            if (!this.showGrid) return;
            const obj = e.target;
            const snap = 10;
            obj.set({
                left: Math.round(obj.left / snap) * snap,
                top: Math.round(obj.top / snap) * snap,
            });
        });
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

            if (type === 'customText') this.addCustomText();
            else if (type === 'text') this.addText(placeholder, x, y);
            else if (type === 'image') this.addImagePlaceholder(placeholder, x, y);
            else if (type === 'shape') this.addShape(shape, x, y);
            else if (type === 'tableArea') this.addTableArea(x, y);
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
        const text = new fabric.IText(getExampleText(placeholder), {
            left: x, top: y,
            fontSize: 36,
            fontFamily: 'Montserrat',
            fontWeight: '700',
            fill: '#ffffff',
            originX: 'center', originY: 'center',
            textAlign: 'center',
        });
        text.placeholder = placeholder;
        text.elementType = 'text';
        this.canvas.add(text);
        this.canvas.setActiveObject(text);
        this.saveHistory();
    },

    addCustomText() {
        const cx = this.canvasWidth / 2;
        const cy = this.canvasHeight / 2;
        const text = new fabric.IText('Your Text Here', {
            left: cx, top: cy,
            fontSize: 36,
            fontFamily: 'Montserrat',
            fontWeight: '700',
            fill: '#ffffff',
            originX: 'center', originY: 'center',
            textAlign: 'center',
        });
        text.placeholder = '';
        text.elementType = 'text';
        this.canvas.add(text);
        this.canvas.setActiveObject(text);
        this.canvas.renderAll();
        // Enter editing mode so user can type immediately
        text.enterEditing();
        text.selectAll();
        this.saveHistory();
    },

    addIcon(unicode, name) {
        const cx = this.canvasWidth / 2;
        const cy = this.canvasHeight / 2;
        const icon = new fabric.Text(unicode, {
            left: cx, top: cy,
            fontSize: 64,
            fontFamily: 'Font Awesome 6 Free',
            fontWeight: '900',
            fill: '#ffffff',
            originX: 'center', originY: 'center',
            textAlign: 'center',
        });
        icon.elementType = 'icon';
        icon.iconName = name || 'icon';
        icon.iconUnicode = unicode;
        icon.placeholder = '';
        this.canvas.add(icon);
        this.canvas.setActiveObject(icon);
        this.canvas.renderAll();
        this.saveHistory();
    },

    updateIcon(prop, value) {
        const obj = this.canvas.getActiveObject();
        if (!obj || obj.elementType !== 'icon') return;
        if (obj.iconType === 'svg') {
            if (prop === 'fill') {
                // Set fill on all paths inside the group
                if (obj._objects) {
                    obj._objects.forEach(child => {
                        child.set('fill', value);
                        if (child.stroke && child.stroke !== 'none' && child.stroke !== 'transparent') {
                            child.set('stroke', value);
                        }
                    });
                } else {
                    obj.set('fill', value);
                    if (obj.stroke) obj.set('stroke', value);
                }
                obj.iconColor = value;
            } else if (prop === 'fontSize') {
                const scale = value / 64;
                obj.set({ scaleX: scale, scaleY: scale });
            }
        } else {
            obj.set(prop, value);
        }
        this.canvas.renderAll();
        this.saveHistory();
        if (prop === 'fill') document.getElementById('propIconColor').value = this.colorToHex(value);
        if (prop === 'fontSize') document.getElementById('propIconSize').value = value;
    },

    svgIconMap: {
        cricket_bat: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><path fill="__COLOR__" d="M14 50l-4 4a2 2 0 002.83 2.83l4-4L14 50zm4-4l22-22c2-2 6-3 8-1s1 6-1 8L25 53l-7-7zm26-26l6-6a4 4 0 00-5.66-5.66l-6 6 5.66 5.66z"/></svg>',
        cricket_ball: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle cx="32" cy="32" r="20" fill="none" stroke="__COLOR__" stroke-width="5"/><path d="M22 16c4 8 4 24 0 32M42 16c-4 8-4 24 0 32" fill="none" stroke="__COLOR__" stroke-width="3.5" stroke-linecap="round"/></svg>',
        stumps: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect x="22" y="16" width="4" height="36" rx="1" fill="__COLOR__"/><rect x="30" y="16" width="4" height="36" rx="1" fill="__COLOR__"/><rect x="38" y="16" width="4" height="36" rx="1" fill="__COLOR__"/><rect x="20" y="18" width="24" height="3" rx="1" fill="__COLOR__"/><rect x="20" y="26" width="24" height="3" rx="1" fill="__COLOR__"/></svg>',
        bat_ball: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><path fill="__COLOR__" d="M10 52l-3 3a1.5 1.5 0 002.12 2.12l3-3L10 52zm3-3l18-18c1.5-1.5 5-2.5 6.5-1s.5 5-1 6.5L18.5 54.5l-5.5-5.5zM35 31l5-5a3 3 0 00-4.24-4.24l-5 5L35 31z"/><circle cx="48" cy="16" r="8" fill="none" stroke="__COLOR__" stroke-width="3.5"/><path d="M44 10c1.5 3 1.5 9 0 12M52 10c-1.5 3-1.5 9 0 12" fill="none" stroke="__COLOR__" stroke-width="2" stroke-linecap="round"/></svg>',
        wicket: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect x="18" y="14" width="4" height="40" rx="1" fill="__COLOR__"/><rect x="30" y="14" width="4" height="40" rx="1" fill="__COLOR__"/><rect x="42" y="14" width="4" height="40" rx="1" fill="__COLOR__"/><path d="M20 16 L26 10 L32 16" fill="none" stroke="__COLOR__" stroke-width="3" stroke-linecap="round"/><path d="M32 16 L38 10 L44 16" fill="none" stroke="__COLOR__" stroke-width="3" stroke-linecap="round"/></svg>',
    },

    addSvgIcon(name) {
        const svgTemplate = this.svgIconMap[name];
        if (!svgTemplate) return;
        const color = '#ffffff';
        const svgString = svgTemplate.replace(/__COLOR__/g, color);
        const cx = this.canvasWidth / 2;
        const cy = this.canvasHeight / 2;

        fabric.loadSVGFromString(svgString, (objects, options) => {
            const group = fabric.util.groupSVGElements(objects, options);
            group.set({
                left: cx, top: cy,
                originX: 'center', originY: 'center',
                scaleX: 1.5, scaleY: 1.5,
            });
            group.elementType = 'icon';
            group.iconType = 'svg';
            group.iconName = name;
            group.iconColor = color;
            group.placeholder = '';
            this.canvas.add(group);
            this.canvas.setActiveObject(group);
            this.canvas.renderAll();
            this.saveHistory();
        });
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
        const label = new fabric.Text(placeholder.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()), {
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
        const props = { left: x, top: y, fill: '#6366f1', stroke: null, strokeWidth: 0, originX: 'center', originY: 'center' };
        if (type === 'rect') shape = new fabric.Rect({ ...props, width: 150, height: 100, rx: 0, ry: 0 });
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

    addTableArea(x, y) {
        // Prevent adding multiple table areas
        const existing = this.canvas.getObjects().find(o => o.elementType === 'tableArea');
        if (existing) { alert('Only one table area allowed per template.'); this.canvas.setActiveObject(existing); this.canvas.renderAll(); return; }

        const w = 900, h = 500;
        const border = new fabric.Rect({
            width: w, height: h,
            fill: 'rgba(30, 64, 175, 0.15)',
            stroke: '#3b82f6', strokeWidth: 3, strokeDashArray: [10, 5],
            rx: 8, ry: 8,
            originX: 'center', originY: 'center',
        });
        const title = new fabric.Text('POINT TABLE AREA', {
            fontSize: 20, fill: '#60a5fa', fontFamily: 'Arial', fontWeight: '700',
            originX: 'center', originY: 'center', top: -h/2 + 30,
        });
        const sample = new fabric.Text('#  Team              P  W  L  T   NRR    Pts\n1  Team Alpha         5  4  1  0  +1.250   8\n2  Team Beta          5  3  2  0  +0.450   6\n3  Team Gamma         5  2  3  0  -0.320   4\n4  Team Delta         5  1  4  0  -1.100   2', {
            fontSize: 13, fill: '#94a3b8', fontFamily: 'Courier New',
            originX: 'center', originY: 'center', top: 20, lineHeight: 1.6,
        });
        const group = new fabric.Group([border, title, sample], {
            left: x, top: y, originX: 'center', originY: 'center',
        });
        group.elementType = 'tableArea';
        group.placeholder = 'table_data';
        group.tableConfig = {
            headerBg: '#1e40af', headerText: '#ffffff',
            evenRowBg: '#1e293b', oddRowBg: '#334155',
            qualifiedBg: '#064e3b', textColor: '#ffffff',
            pointsColor: '#FFD700', fontSize: 16, rowHeight: 80,
            showTeamLogo: true, showNRR: true, showLegend: true,
        };
        this.canvas.add(group);
        this.canvas.setActiveObject(group);
        this.saveHistory();
    },

    updateTableConfig(key, value) {
        const obj = this.canvas.getActiveObject();
        if (!obj || obj.elementType !== 'tableArea') return;
        obj.tableConfig = obj.tableConfig || {};
        obj.tableConfig[key] = value;
        // Update the border fill color to reflect header color
        if (key === 'headerBg') {
            const r = parseInt(value.slice(1,3),16), g = parseInt(value.slice(3,5),16), b = parseInt(value.slice(5,7),16);
            obj.item(0).set('fill', `rgba(${r},${g},${b},0.15)`);
            obj.item(0).set('stroke', value);
            this.canvas.renderAll();
        }
        this.saveHistory();
    },

    applyTablePreset(preset) {
        const presets = {
            dark: { headerBg:'#1e40af', headerText:'#ffffff', evenRowBg:'#1e293b', oddRowBg:'#334155', qualifiedBg:'#064e3b', textColor:'#ffffff', pointsColor:'#FFD700' },
            light: { headerBg:'#6366f1', headerText:'#ffffff', evenRowBg:'#f8fafc', oddRowBg:'#f1f5f9', qualifiedBg:'#dcfce7', textColor:'#1e293b', pointsColor:'#7c3aed' },
            ipl: { headerBg:'#7c3aed', headerText:'#ffffff', evenRowBg:'#1a0533', oddRowBg:'#2d0a4e', qualifiedBg:'#065f46', textColor:'#e2e8f0', pointsColor:'#fbbf24' },
            minimal: { headerBg:'#3f3f46', headerText:'#fafafa', evenRowBg:'#18181b', oddRowBg:'#27272a', qualifiedBg:'#1c4532', textColor:'#a1a1aa', pointsColor:'#f59e0b' },
        };
        const cfg = presets[preset];
        if (!cfg) return;
        const obj = this.canvas.getActiveObject();
        if (!obj || obj.elementType !== 'tableArea') return;
        obj.tableConfig = { ...obj.tableConfig, ...cfg };
        this.updateTablePropertiesPanel(obj);
        // Update visual
        const r = parseInt(cfg.headerBg.slice(1,3),16), g = parseInt(cfg.headerBg.slice(3,5),16), b = parseInt(cfg.headerBg.slice(5,7),16);
        obj.item(0).set('fill', `rgba(${r},${g},${b},0.15)`);
        obj.item(0).set('stroke', cfg.headerBg);
        this.canvas.renderAll();
        this.saveHistory();
    },

    updateTablePropertiesPanel(obj) {
        const cfg = obj.tableConfig || {};
        document.getElementById('propTableHeaderBg').value = cfg.headerBg || '#1e40af';
        document.getElementById('propTableHeaderText').value = cfg.headerText || '#ffffff';
        document.getElementById('propTableEvenRowBg').value = cfg.evenRowBg || '#1e293b';
        document.getElementById('propTableOddRowBg').value = cfg.oddRowBg || '#334155';
        document.getElementById('propTableQualifiedBg').value = cfg.qualifiedBg || '#064e3b';
        document.getElementById('propTableTextColor').value = cfg.textColor || '#ffffff';
        document.getElementById('propTablePointsColor').value = cfg.pointsColor || '#FFD700';
        document.getElementById('propTableFontSize').value = cfg.fontSize || 16;
        document.getElementById('propTableRowHeight').value = cfg.rowHeight || 80;
        document.getElementById('propTableShowLogo').checked = cfg.showTeamLogo !== false;
        document.getElementById('propTableShowNRR').checked = cfg.showNRR !== false;
        document.getElementById('propTableShowLegend').checked = cfg.showLegend !== false;
    },

    addScorecardTable(placeholder, scorecardType, team, x, y) {
        x = x || this.canvasWidth / 2;
        y = y || this.canvasHeight / 2;
        const isBatting = scorecardType === 'batting';
        const teamLabel = team === 'a' ? 'TEAM A' : 'TEAM B';
        const typeLabel = isBatting ? 'BATTING' : 'BOWLING';
        const w = 400, h = isBatting ? 180 : 160;

        const border = new fabric.Rect({
            width: w, height: h,
            fill: 'rgba(30, 64, 175, 0.12)',
            stroke: isBatting ? '#3b82f6' : '#10b981',
            strokeWidth: 2, strokeDashArray: [8, 4],
            rx: 6, ry: 6,
            originX: 'center', originY: 'center',
        });
        const title = new fabric.Text(teamLabel + ' - ' + typeLabel, {
            fontSize: 14, fill: isBatting ? '#60a5fa' : '#34d399',
            fontFamily: 'Arial', fontWeight: '700',
            originX: 'center', originY: 'center', top: -h/2 + 18,
        });
        const sampleLines = isBatting
            ? 'Name          R    B   4s   6s\nVirat K.     72   45    8    3\nRohit S.     56   38    6    2\nKL Rahul     41   30    4    1'
            : 'Name          O    R    W  Econ\nJasprit B.  4.0   24    3  6.00\nMohd S.     4.0   32    2  8.00\nRavindra J. 3.0   22    1  7.33';
        const sample = new fabric.Text(sampleLines, {
            fontSize: 11, fill: '#94a3b8', fontFamily: 'Courier New',
            originX: 'center', originY: 'center', top: 15, lineHeight: 1.5,
        });
        const group = new fabric.Group([border, title, sample], {
            left: x, top: y, originX: 'center', originY: 'center',
        });
        group.elementType = 'scorecardTable';
        group.placeholder = placeholder;
        group.scorecardConfig = {
            scorecardType: scorecardType, team: team, maxRows: 3,
            headerBg: '#1e40af', headerText: '#ffffff',
            rowBg: '#1e293b', altRowBg: '#334155',
            textColor: '#ffffff', accentColor: '#FFD700',
            fontSize: 14, rowHeight: 40,
        };
        this.canvas.add(group);
        this.canvas.setActiveObject(group);
        this.saveHistory();
    },

    updateScorecardConfig(key, value) {
        const obj = this.canvas.getActiveObject();
        if (!obj || obj.elementType !== 'scorecardTable') return;
        obj.scorecardConfig = obj.scorecardConfig || {};
        obj.scorecardConfig[key] = value;
        if (key === 'headerBg') {
            const r = parseInt(value.slice(1,3),16), g = parseInt(value.slice(3,5),16), b = parseInt(value.slice(5,7),16);
            obj.item(0).set('fill', `rgba(${r},${g},${b},0.12)`);
            obj.item(0).set('stroke', value);
            this.canvas.renderAll();
        }
        this.saveHistory();
    },

    applyScorecardPreset(preset) {
        const presets = {
            dark: { headerBg:'#1e40af', headerText:'#ffffff', rowBg:'#1e293b', altRowBg:'#334155', textColor:'#ffffff', accentColor:'#FFD700' },
            light: { headerBg:'#6366f1', headerText:'#ffffff', rowBg:'#f8fafc', altRowBg:'#f1f5f9', textColor:'#1e293b', accentColor:'#7c3aed' },
            ipl: { headerBg:'#7c3aed', headerText:'#ffffff', rowBg:'#1a0533', altRowBg:'#2d0a4e', textColor:'#e2e8f0', accentColor:'#fbbf24' },
        };
        const cfg = presets[preset];
        if (!cfg) return;
        const obj = this.canvas.getActiveObject();
        if (!obj || obj.elementType !== 'scorecardTable') return;
        obj.scorecardConfig = { ...obj.scorecardConfig, ...cfg };
        this.updateScorecardPropertiesPanel(obj);
        const r = parseInt(cfg.headerBg.slice(1,3),16), g = parseInt(cfg.headerBg.slice(3,5),16), b = parseInt(cfg.headerBg.slice(5,7),16);
        obj.item(0).set('fill', `rgba(${r},${g},${b},0.12)`);
        obj.item(0).set('stroke', cfg.headerBg);
        this.canvas.renderAll();
        this.saveHistory();
    },

    updateScorecardPropertiesPanel(obj) {
        const cfg = obj.scorecardConfig || {};
        document.getElementById('propScHeaderBg').value = cfg.headerBg || '#1e40af';
        document.getElementById('propScHeaderText').value = cfg.headerText || '#ffffff';
        document.getElementById('propScRowBg').value = cfg.rowBg || '#1e293b';
        document.getElementById('propScAltRowBg').value = cfg.altRowBg || '#334155';
        document.getElementById('propScTextColor').value = cfg.textColor || '#ffffff';
        document.getElementById('propScAccentColor').value = cfg.accentColor || '#FFD700';
        document.getElementById('propScFontSize').value = cfg.fontSize || 14;
        document.getElementById('propScRowHeight').value = cfg.rowHeight || 40;
        document.getElementById('propScMaxRows').value = cfg.maxRows || 3;
        document.getElementById('propScTransparentBg').checked = cfg.transparentBg || false;
    },

    addFixtureArea(x, y) {
        x = x || this.canvasWidth / 2;
        y = y || this.canvasHeight / 2;
        const w = 900, h = 500;

        const border = new fabric.Rect({
            width: w, height: h,
            fill: 'rgba(20, 184, 166, 0.12)',
            stroke: '#14b8a6',
            strokeWidth: 2, strokeDashArray: [8, 4],
            rx: 6, ry: 6,
            originX: 'center', originY: 'center',
        });
        const title = new fabric.Text('UPCOMING FIXTURES', {
            fontSize: 14, fill: '#14b8a6',
            fontFamily: 'Arial', fontWeight: '700',
            originX: 'center', originY: 'center', top: -h/2 + 18,
        });
        const sampleLines = 'Royal Strikers       VS       Thunder Kings\n  Jun 15  |  06:00 PM  |  City Stadium\n\nMountrich CC         VS       Canadian CC\n  Jun 16  |  06:00 PM  |  City Stadium';
        const sample = new fabric.Text(sampleLines, {
            fontSize: 11, fill: '#94a3b8', fontFamily: 'Courier New',
            originX: 'center', originY: 'center', top: 20, lineHeight: 1.4,
        });
        const group = new fabric.Group([border, title, sample], {
            left: x, top: y, originX: 'center', originY: 'center',
        });
        group.elementType = 'fixtureArea';
        group.placeholder = 'fixture_area';
        group.fixtureConfig = {
            layout: 'row',
            transparentBg: true,
            maxRows: 5,
            headerBg: '#1e40af', headerText: '#ffffff',
            rowBg: '#0a1628', altRowBg: '#0f1d33',
            textColor: '#ffffff', accentColor: '#d4a843',
            mutedColor: '#8899aa', dividerColor: '#d4a843',
            fontSize: 16, rowHeight: 100,
            cardColumns: 2, cardStyle: 'flat',
            showTeamLogo: true,
            useShortName: false,
            showMatchNum: false,
            showVenue: true,
            showDateTime: true,
            showBorder: false,
            rowGap: 4,
            rowPadding: 16,
        };
        this.canvas.add(group);
        this.canvas.setActiveObject(group);
        this.saveHistory();
    },

    updateFixtureConfig(key, value) {
        const obj = this.canvas.getActiveObject();
        if (!obj || obj.elementType !== 'fixtureArea') return;
        obj.fixtureConfig = obj.fixtureConfig || {};
        obj.fixtureConfig[key] = value;
        if (key === 'headerBg') {
            const r = parseInt(value.slice(1,3),16), g = parseInt(value.slice(3,5),16), b = parseInt(value.slice(5,7),16);
            obj.item(0).set('fill', `rgba(${r},${g},${b},0.12)`);
            obj.item(0).set('stroke', value);
            this.canvas.renderAll();
        }
        if (key === 'layout') {
            this._syncFixtureLayoutUI(value);
        }
        if (key === 'cardStyle') {
            this._syncCardStyleUI(value);
        }
        this.saveHistory();
    },

    _syncFixtureLayoutUI(layout) {
        // Toggle active state on layout buttons
        document.querySelectorAll('#propFxLayoutSelector button').forEach(btn => {
            if (btn.dataset.layout === layout) {
                btn.style.background = '#4f46e5'; btn.style.color = '#fff'; btn.style.borderColor = '#6366f1';
            } else {
                btn.style.background = ''; btn.style.color = ''; btn.style.borderColor = '';
            }
        });
        // Toggle card-specific options
        const isCard = layout === 'card';
        const cardColGroup = document.getElementById('propFxCardColumnsGroup');
        const cardColorGroup = document.getElementById('propFxCardColorGroup');
        const rowHeightGroup = document.getElementById('propFxRowHeightGroup');
        if (cardColGroup) cardColGroup.style.display = isCard ? '' : 'none';
        if (cardColorGroup) cardColorGroup.style.display = isCard ? '' : 'none';
        if (rowHeightGroup) {
            rowHeightGroup.querySelector('.prop-label').textContent = isCard ? 'Card Height' : 'Row Height';
        }
    },

    _syncCardStyleUI(style) {
        document.querySelectorAll('#propFxCardStyleSelector button').forEach(btn => {
            if (btn.dataset.style === style) {
                btn.style.background = '#4f46e5'; btn.style.color = '#fff'; btn.style.borderColor = '#6366f1';
            } else {
                btn.style.background = ''; btn.style.color = ''; btn.style.borderColor = '';
            }
        });
    },

    applyFixturePreset(preset) {
        const presets = {
            classic: { layout:'row', cardStyle:'flat', showBorder:false, transparentBg:true, showTeamLogo:true, showMatchNum:false, showVenue:true, showDateTime:true, useShortName:false, rowGap:4, rowPadding:16, headerBg:'#1e40af', headerText:'#ffffff', rowBg:'#0a1628', altRowBg:'#0f1d33', textColor:'#ffffff', accentColor:'#d4a843', mutedColor:'#8899aa', dividerColor:'#d4a843' },
            modern:  { layout:'row', cardStyle:'gradient', showBorder:true, transparentBg:false, showTeamLogo:true, showMatchNum:false, showVenue:true, showDateTime:true, useShortName:false, rowGap:6, rowPadding:18, headerBg:'#3b82f6', headerText:'#ffffff', rowBg:'#1e293b', altRowBg:'#273548', textColor:'#f1f5f9', accentColor:'#60a5fa', mutedColor:'#94a3b8', dividerColor:'#3b82f6' },
            minimal: { layout:'row', cardStyle:'flat', showBorder:false, transparentBg:true, showTeamLogo:false, showMatchNum:false, showVenue:false, showDateTime:true, useShortName:true, rowGap:2, rowPadding:12, headerBg:'#27272a', headerText:'#fafafa', rowBg:'#18181b', altRowBg:'#27272a', textColor:'#fafafa', accentColor:'#a1a1aa', mutedColor:'#71717a', dividerColor:'#3f3f46' },
            ipl:     { layout:'row', cardStyle:'glow', showBorder:false, transparentBg:false, showTeamLogo:true, showMatchNum:true, showVenue:true, showDateTime:true, useShortName:false, rowGap:6, rowPadding:16, headerBg:'#7c3aed', headerText:'#ffffff', rowBg:'#1a0533', altRowBg:'#2d1050', textColor:'#ffffff', accentColor:'#f59e0b', mutedColor:'#c4b5fd', dividerColor:'#7c3aed' },
            cardDark:{ layout:'card', cardStyle:'flat', showBorder:true, transparentBg:false, showTeamLogo:true, showMatchNum:true, showVenue:true, showDateTime:true, useShortName:false, rowGap:8, rowPadding:12, cardColumns:2, headerBg:'#1e40af', headerText:'#ffffff', rowBg:'#0a1628', altRowBg:'#0f1d33', textColor:'#ffffff', accentColor:'#d4a843', mutedColor:'#8899aa', dividerColor:'#d4a843' },
            cardLight:{ layout:'card', cardStyle:'flat', showBorder:false, transparentBg:false, showTeamLogo:true, showMatchNum:true, showVenue:true, showDateTime:true, useShortName:false, rowGap:8, rowPadding:12, cardColumns:2, headerBg:'#4f46e5', headerText:'#ffffff', rowBg:'#ffffff', altRowBg:'#f1f5f9', textColor:'#1e293b', accentColor:'#4f46e5', mutedColor:'#64748b', dividerColor:'#e2e8f0' },
            neonGlow:{ layout:'row', cardStyle:'glow', showBorder:false, transparentBg:false, showTeamLogo:true, showMatchNum:false, showVenue:true, showDateTime:true, useShortName:false, rowGap:6, rowPadding:16, headerBg:'#06b6d4', headerText:'#000000', rowBg:'#0a0a0a', altRowBg:'#171717', textColor:'#22d3ee', accentColor:'#f43f5e', mutedColor:'#67e8f9', dividerColor:'#06b6d4' },
            royal:   { layout:'row', cardStyle:'gradient', showBorder:true, transparentBg:false, showTeamLogo:true, showMatchNum:false, showVenue:true, showDateTime:true, useShortName:false, rowGap:4, rowPadding:16, headerBg:'#4338ca', headerText:'#fbbf24', rowBg:'#1e1b4b', altRowBg:'#312e81', textColor:'#e0e7ff', accentColor:'#fbbf24', mutedColor:'#a5b4fc', dividerColor:'#fbbf24' },
            tournament:{ layout:'row', cardStyle:'stripe', showBorder:false, transparentBg:false, showTeamLogo:true, showMatchNum:true, showVenue:true, showDateTime:true, useShortName:false, rowGap:4, rowPadding:16, headerBg:'#047857', headerText:'#ffffff', rowBg:'#064e3b', altRowBg:'#065f46', textColor:'#ffffff', accentColor:'#FFD700', mutedColor:'#a7f3d0', dividerColor:'#FFD700' },
        };
        const cfg = presets[preset];
        if (!cfg) return;
        const obj = this.canvas.getActiveObject();
        if (!obj || obj.elementType !== 'fixtureArea') return;
        obj.fixtureConfig = { ...obj.fixtureConfig, ...cfg };
        this.updateFixturePropertiesPanel(obj);
        const r = parseInt(cfg.headerBg.slice(1,3),16), g = parseInt(cfg.headerBg.slice(3,5),16), b = parseInt(cfg.headerBg.slice(5,7),16);
        obj.item(0).set('fill', `rgba(${r},${g},${b},0.12)`);
        obj.item(0).set('stroke', cfg.headerBg);
        this.canvas.renderAll();
        this.saveHistory();
    },

    updateFixturePropertiesPanel(obj) {
        const cfg = obj.fixtureConfig || {};
        document.getElementById('propFxHeaderBg').value = cfg.headerBg || '#1e40af';
        document.getElementById('propFxHeaderText').value = cfg.headerText || '#ffffff';
        document.getElementById('propFxRowBg').value = cfg.rowBg || '#1e293b';
        document.getElementById('propFxAltRowBg').value = cfg.altRowBg || '#293548';
        document.getElementById('propFxTextColor').value = cfg.textColor || '#ffffff';
        document.getElementById('propFxAccentColor').value = cfg.accentColor || '#d4a843';
        document.getElementById('propFxMutedColor').value = cfg.mutedColor || '#94a3b8';
        document.getElementById('propFxDividerColor').value = cfg.dividerColor || '#d4a843';
        document.getElementById('propFxFontSize').value = cfg.fontSize || 16;
        document.getElementById('propFxRowHeight').value = cfg.rowHeight || 100;
        document.getElementById('propFxMaxRows').value = cfg.maxRows || 5;
        document.getElementById('propFxTransparentBg').checked = cfg.transparentBg ?? true;
        document.getElementById('propFxShowTeamLogo').checked = cfg.showTeamLogo ?? true;
        document.getElementById('propFxUseShortName').checked = cfg.useShortName ?? false;
        document.getElementById('propFxShowMatchNum').checked = cfg.showMatchNum ?? false;
        document.getElementById('propFxShowVenue').checked = cfg.showVenue ?? true;
        document.getElementById('propFxShowDateTime').checked = cfg.showDateTime ?? true;
        document.getElementById('propFxShowBorder').checked = cfg.showBorder ?? false;
        document.getElementById('propFxRowGap').value = cfg.rowGap ?? 4;
        document.getElementById('propFxRowPadding').value = cfg.rowPadding ?? 16;
        const cardColSel = document.getElementById('propFxCardColumns');
        if (cardColSel) cardColSel.value = cfg.cardColumns || 2;
        this._syncFixtureLayoutUI(cfg.layout || 'row');
        this._syncCardStyleUI(cfg.cardStyle || 'flat');
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

        const isIcon = obj.elementType === 'icon';
        const isText = !isIcon && (obj.elementType === 'text' || obj.type === 'i-text');
        const isShape = !isIcon && obj.elementType === 'shape';
        const isTable = obj.elementType === 'tableArea';
        const isScorecard = obj.elementType === 'scorecardTable';
        const isFixture = obj.elementType === 'fixtureArea';
        document.getElementById('iconPropertiesPanel').classList.toggle('hidden', !isIcon);
        document.getElementById('textPropertiesPanel').classList.toggle('hidden', !isText);
        document.getElementById('shapePropertiesPanel').classList.toggle('hidden', !isShape);
        document.getElementById('tablePropertiesPanel').classList.toggle('hidden', !isTable);
        document.getElementById('scorecardPropertiesPanel').classList.toggle('hidden', !isScorecard);
        document.getElementById('fixturePropertiesPanel').classList.toggle('hidden', !isFixture);

        if (isIcon) {
            const iconColor = obj.iconType === 'svg' ? (obj.iconColor || '#ffffff') : (obj.fill || '#ffffff');
            const iconSize = obj.iconType === 'svg' ? Math.round((obj.width || 64) * (obj.scaleX || 1)) : Math.round(obj.fontSize || 64);
            document.getElementById('propIconColor').value = this.colorToHex(iconColor);
            document.getElementById('propIconSize').value = iconSize;
        }
        if (isText) {
            const placeholderInfoEl = document.getElementById('propPlaceholderInfo');
            const placeholderNameEl = document.getElementById('propPlaceholderName');
            if (obj.placeholder) {
                placeholderInfoEl.classList.remove('hidden');
                placeholderNameEl.textContent = obj.placeholder.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) + '  (' + obj.placeholder + ')';
            } else {
                placeholderInfoEl.classList.add('hidden');
            }
            document.getElementById('propFontFamily').value = obj.fontFamily || 'Arial';
            document.getElementById('propFontSize').value = Math.round(obj.fontSize || 24);
            document.getElementById('propFontWeight').value = obj.fontWeight || '400';
            document.getElementById('propTextColor').value = obj.fill || '#ffffff';
            document.getElementById('propTextTransform').value = obj.textTransform || 'none';
            const skewVal = Math.round(obj.skewX || 0);
            document.getElementById('propSkewX').value = skewVal;
            document.getElementById('skewValue').textContent = skewVal + '°';
            // Update style toggle buttons
            const toggleState = (btnId, active) => {
                const btn = document.getElementById(btnId);
                if (active) { btn.classList.remove('prop-btn-secondary'); btn.classList.add('prop-btn-primary'); }
                else { btn.classList.remove('prop-btn-primary'); btn.classList.add('prop-btn-secondary'); }
            };
            toggleState('propBoldBtn', parseInt(obj.fontWeight || '400') >= 700);
            toggleState('propItalicBtn', obj.fontStyle === 'italic');
            toggleState('propUnderlineBtn', obj.underline === true);
            toggleState('propLinethroughBtn', obj.linethrough === true);
            document.getElementById('propShadowX').value = obj.shadow ? Math.round(obj.shadow.offsetX || 0) : 0;
            document.getElementById('propShadowY').value = obj.shadow ? Math.round(obj.shadow.offsetY || 0) : 0;
            document.getElementById('propShadowBlur').value = obj.shadow ? Math.round(obj.shadow.blur || 0) : 0;
            document.getElementById('propShadowColor').value = obj.shadow ? this.colorToHex(obj.shadow.color || '#000000') : '#000000';
            document.getElementById('propTextStroke').value = this.colorToHex(obj.stroke || '#000000');
            document.getElementById('propTextStrokeWidth').value = obj.strokeWidth || 0;
        }
        if (isShape) {
            this.updateShapePropertiesPanel(obj);
        }
        if (isTable) {
            this.updateTablePropertiesPanel(obj);
        }
        if (isScorecard) {
            this.updateScorecardPropertiesPanel(obj);
        }
        if (isFixture) {
            this.updateFixturePropertiesPanel(obj);
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
            const radii = obj.borderRadii || { tl: obj.rx || 0, tr: obj.rx || 0, br: obj.rx || 0, bl: obj.rx || 0 };
            const allSame = radii.tl === radii.tr && radii.tr === radii.br && radii.br === radii.bl;
            this.borderRadiusLinked = allSame;
            document.getElementById('borderRadiusLinked').classList.toggle('hidden', !allSame);
            document.getElementById('borderRadiusUnlinked').classList.toggle('hidden', allSame);
            document.getElementById('propShapeBorderRadius').value = obj.rx || 0;
            document.getElementById('propBorderRadiusTL').value = radii.tl;
            document.getElementById('propBorderRadiusTR').value = radii.tr;
            document.getElementById('propBorderRadiusBR').value = radii.br;
            document.getElementById('propBorderRadiusBL').value = radii.bl;
        }
        // Shape shadow
        document.getElementById('propShapeShadowX').value = obj.shadow ? Math.round(obj.shadow.offsetX || 0) : 0;
        document.getElementById('propShapeShadowY').value = obj.shadow ? Math.round(obj.shadow.offsetY || 0) : 0;
        document.getElementById('propShapeShadowBlur').value = obj.shadow ? Math.round(obj.shadow.blur || 0) : 0;
        document.getElementById('propShapeShadowColor').value = obj.shadow ? this.colorToHex(obj.shadow.color || '#000000') : '#000000';
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
        document.getElementById('tablePropertiesPanel').classList.add('hidden');
        document.getElementById('scorecardPropertiesPanel').classList.add('hidden');
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

    setTextTransform(value) {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        obj.textTransform = value;
        this.saveHistory();
    },

    toggleBold() {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        const current = parseInt(obj.fontWeight || '400');
        const newWeight = current >= 700 ? '400' : '700';
        obj.set('fontWeight', newWeight);
        document.getElementById('propFontWeight').value = newWeight;
        const btn = document.getElementById('propBoldBtn');
        if (newWeight === '700') { btn.classList.remove('prop-btn-secondary'); btn.classList.add('prop-btn-primary'); }
        else { btn.classList.remove('prop-btn-primary'); btn.classList.add('prop-btn-secondary'); }
        this.canvas.renderAll();
        this.saveHistory();
    },

    toggleItalic() {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        const newStyle = obj.fontStyle === 'italic' ? 'normal' : 'italic';
        obj.set('fontStyle', newStyle);
        const btn = document.getElementById('propItalicBtn');
        if (newStyle === 'italic') { btn.classList.remove('prop-btn-secondary'); btn.classList.add('prop-btn-primary'); }
        else { btn.classList.remove('prop-btn-primary'); btn.classList.add('prop-btn-secondary'); }
        this.canvas.renderAll();
        this.saveHistory();
    },

    toggleUnderline() {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        const newVal = !obj.underline;
        obj.set('underline', newVal);
        const btn = document.getElementById('propUnderlineBtn');
        if (newVal) { btn.classList.remove('prop-btn-secondary'); btn.classList.add('prop-btn-primary'); }
        else { btn.classList.remove('prop-btn-primary'); btn.classList.add('prop-btn-secondary'); }
        this.canvas.renderAll();
        this.saveHistory();
    },

    toggleLinethrough() {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        const newVal = !obj.linethrough;
        obj.set('linethrough', newVal);
        const btn = document.getElementById('propLinethroughBtn');
        if (newVal) { btn.classList.remove('prop-btn-secondary'); btn.classList.add('prop-btn-primary'); }
        else { btn.classList.remove('prop-btn-primary'); btn.classList.add('prop-btn-secondary'); }
        this.canvas.renderAll();
        this.saveHistory();
    },

    updateSkew(value) {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        obj.set('skewX', value);
        document.getElementById('propSkewX').value = value;
        document.getElementById('skewValue').textContent = value + '°';
        this.canvas.renderAll();
        this.saveHistory();
    },

    updateShadow() {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        const x = parseInt(document.getElementById('propShadowX').value) || 0;
        const y = parseInt(document.getElementById('propShadowY').value) || 0;
        const blur = parseInt(document.getElementById('propShadowBlur').value) || 0;
        const shadowColor = document.getElementById('propShadowColor').value || '#000000';
        if (x === 0 && y === 0 && blur === 0) {
            obj.set('shadow', null);
        } else {
            obj.set('shadow', new fabric.Shadow({ color: shadowColor, blur: blur, offsetX: x, offsetY: y }));
        }
        this.canvas.renderAll();
        this.saveHistory();
    },

    updateTextStroke(color) {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        obj.set('stroke', color);
        document.getElementById('propTextStroke').value = this.colorToHex(color);
        this.canvas.renderAll();
        this.saveHistory();
    },

    updateTextStrokeWidth(val) {
        const obj = this.canvas.getActiveObject();
        if (!obj) return;
        obj.set('strokeWidth', parseInt(val) || 0);
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
        this.saveHistory();
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
        obj.borderRadii = { tl: val, tr: val, br: val, bl: val };
        this.canvas.renderAll();
        this.saveHistory();
    },

    borderRadiusLinked: true,

    toggleBorderRadiusLink() {
        this.borderRadiusLinked = !this.borderRadiusLinked;
        document.getElementById('borderRadiusLinked').classList.toggle('hidden', !this.borderRadiusLinked);
        document.getElementById('borderRadiusUnlinked').classList.toggle('hidden', this.borderRadiusLinked);
        if (this.borderRadiusLinked) {
            // Sync all corners to the max value
            const tl = parseInt(document.getElementById('propBorderRadiusTL').value) || 0;
            const tr = parseInt(document.getElementById('propBorderRadiusTR').value) || 0;
            const br = parseInt(document.getElementById('propBorderRadiusBR').value) || 0;
            const bl = parseInt(document.getElementById('propBorderRadiusBL').value) || 0;
            const maxVal = Math.max(tl, tr, br, bl);
            document.getElementById('propShapeBorderRadius').value = maxVal;
            this.updateShapeBorderRadius(maxVal);
        } else {
            // Copy uniform value to all corners
            const val = parseInt(document.getElementById('propShapeBorderRadius').value) || 0;
            document.getElementById('propBorderRadiusTL').value = val;
            document.getElementById('propBorderRadiusTR').value = val;
            document.getElementById('propBorderRadiusBL').value = val;
            document.getElementById('propBorderRadiusBR').value = val;
        }
    },

    updateShapeBorderRadiusCorners() {
        const obj = this.canvas.getActiveObject();
        if (!obj || obj.shapeType !== 'rect') return;
        const tl = parseInt(document.getElementById('propBorderRadiusTL').value) || 0;
        const tr = parseInt(document.getElementById('propBorderRadiusTR').value) || 0;
        const br = parseInt(document.getElementById('propBorderRadiusBR').value) || 0;
        const bl = parseInt(document.getElementById('propBorderRadiusBL').value) || 0;
        obj.borderRadii = { tl, tr, br, bl };
        // Fabric.js only supports uniform rx/ry, use average as visual hint
        const avg = Math.round((tl + tr + br + bl) / 4);
        obj.set({ rx: avg, ry: avg });
        this.canvas.renderAll();
        this.saveHistory();
    },

    updateShapeShadow() {
        const obj = this.canvas.getActiveObject();
        if (!obj || obj.elementType !== 'shape') return;
        const x = parseInt(document.getElementById('propShapeShadowX').value) || 0;
        const y = parseInt(document.getElementById('propShapeShadowY').value) || 0;
        const blur = parseInt(document.getElementById('propShapeShadowBlur').value) || 0;
        const color = document.getElementById('propShapeShadowColor').value || '#000000';
        if (x === 0 && y === 0 && blur === 0) {
            obj.set('shadow', null);
        } else {
            obj.set('shadow', new fabric.Shadow({ color: color, blur: blur, offsetX: x, offsetY: y }));
        }
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
            cloned.textTransform = obj.textTransform;
            if (obj.borderRadii) cloned.borderRadii = { ...obj.borderRadii };
            if (obj.tableConfig) cloned.tableConfig = JSON.parse(JSON.stringify(obj.tableConfig));
            this.canvas.add(cloned);
            this.canvas.setActiveObject(cloned);
            this.saveHistory();
        });
    },

    bringToFront() { const obj = this.canvas.getActiveObject(); if (obj) { this.canvas.bringToFront(obj); this.saveHistory(); this.updateLayers(); } },
    bringForward() { const obj = this.canvas.getActiveObject(); if (obj) { this.canvas.bringForward(obj); this.saveHistory(); this.updateLayers(); } },
    sendBackward() { const obj = this.canvas.getActiveObject(); if (obj) { this.canvas.sendBackwards(obj); this.saveHistory(); this.updateLayers(); } },
    sendToBack() { const obj = this.canvas.getActiveObject(); if (obj) { this.canvas.sendToBack(obj); this.saveHistory(); this.updateLayers(); } },

    // Grid
    toggleGrid() {
        this.showGrid = !this.showGrid;
        document.getElementById('gridToggleBtn').classList.toggle('active', this.showGrid);
        this.canvas.renderAll();
    },

    // Alignment
    alignObjects(direction) {
        const active = this.canvas.getActiveObject();
        if (!active) return;

        // Multi-select (ActiveSelection)
        if (active.type === 'activeSelection') {
            const objects = active.getObjects();
            const bound = active.getBoundingRect(true);
            objects.forEach(obj => {
                // Positions are relative to the selection center
                const objBound = obj.getBoundingRect(true);
                switch (direction) {
                    case 'left':
                        obj.set('left', obj.left - (objBound.left - bound.left));
                        break;
                    case 'right':
                        obj.set('left', obj.left + (bound.left + bound.width - objBound.left - objBound.width));
                        break;
                    case 'centerH':
                        obj.set('left', obj.left - (objBound.left + objBound.width / 2 - bound.left - bound.width / 2));
                        break;
                    case 'top':
                        obj.set('top', obj.top - (objBound.top - bound.top));
                        break;
                    case 'bottom':
                        obj.set('top', obj.top + (bound.top + bound.height - objBound.top - objBound.height));
                        break;
                    case 'centerV':
                        obj.set('top', obj.top - (objBound.top + objBound.height / 2 - bound.top - bound.height / 2));
                        break;
                }
                obj.setCoords();
            });
        } else {
            // Single object - align to canvas
            const objBound = active.getBoundingRect(true);
            switch (direction) {
                case 'left':
                    active.set('left', active.left - objBound.left);
                    break;
                case 'right':
                    active.set('left', active.left + (this.canvasWidth - objBound.left - objBound.width));
                    break;
                case 'centerH':
                    active.set('left', active.left + (this.canvasWidth / 2 - objBound.left - objBound.width / 2));
                    break;
                case 'top':
                    active.set('top', active.top - objBound.top);
                    break;
                case 'bottom':
                    active.set('top', active.top + (this.canvasHeight - objBound.top - objBound.height));
                    break;
                case 'centerV':
                    active.set('top', active.top + (this.canvasHeight / 2 - objBound.top - objBound.height / 2));
                    break;
            }
            active.setCoords();
        }
        this.canvas.renderAll();
        this.saveHistory();
    },

    // History
    saveHistory() {
        const json = this.canvas.toJSON(['placeholder', 'elementType', 'shapeType', 'placeholderWidth', 'placeholderHeight', 'imagePath', 'gradientAngle', 'gradientFillConfig', 'tableConfig', 'textTransform', 'iconName', 'iconUnicode', 'iconType', 'iconColor']);
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
            const rawName = obj.layerName || obj.placeholder || obj.shapeType || obj.type || 'Element';
            const name = rawName.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            const selected = obj === this.canvas.getActiveObject();
            const idx = objects.length - 1 - i;
            const isHidden = obj.visible === false;
            const isLocked = obj.locked === true;
            const classes = ['layer-item', selected ? 'selected' : '', isHidden ? 'hidden-layer' : '', isLocked ? 'locked-layer' : ''].filter(Boolean).join(' ');
            const eyeIcon = isHidden
                ? '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M3 3l18 18"/></svg>'
                : '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>';
            const lockIcon = isLocked
                ? '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>'
                : '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>';
            return `<div class="${classes}" draggable="true" data-layer-idx="${idx}"
                        ondragstart="editor.layerDragStart(event, ${idx})"
                        ondragover="editor.layerDragOver(event, ${idx})"
                        ondragleave="event.currentTarget.classList.remove('drag-over')"
                        ondrop="editor.layerDrop(event, ${idx})"
                        onclick="editor.selectLayer(${idx})">
                <div class="layer-drag-handle"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg></div>
                <div class="layer-info" style="flex:1;min-width:0;">
                    <div class="layer-name" ondblclick="event.stopPropagation(); editor.startLayerRename(${idx}, this)">${name}</div>
                    <div class="layer-type">${obj.elementType || obj.type}</div>
                </div>
                <div class="layer-actions">
                    <button class="layer-action-btn ${isHidden ? '' : 'active'}" onclick="event.stopPropagation(); editor.toggleLayerVisibility(${idx})" title="${isHidden ? 'Show' : 'Hide'}">${eyeIcon}</button>
                    <button class="layer-action-btn ${isLocked ? 'active' : ''}" onclick="event.stopPropagation(); editor.toggleLayerLock(${idx})" title="${isLocked ? 'Unlock' : 'Lock'}">${lockIcon}</button>
                </div>
            </div>`;
        }).join('');
    },

    selectLayer(idx) {
        const obj = this.canvas.getObjects()[idx];
        if (!obj) return;
        if (obj.locked) { this.showToast('Layer is locked', 'error'); return; }
        this.canvas.setActiveObject(obj); this.canvas.renderAll();
    },

    toggleLayerVisibility(idx) {
        const obj = this.canvas.getObjects()[idx];
        if (!obj) return;
        obj.visible = !obj.visible;
        if (!obj.visible && obj === this.canvas.getActiveObject()) this.canvas.discardActiveObject();
        this.canvas.renderAll(); this.updateLayers(); this.saveHistory();
    },

    toggleLayerLock(idx) {
        const obj = this.canvas.getObjects()[idx];
        if (!obj) return;
        obj.locked = !obj.locked;
        obj.selectable = !obj.locked;
        obj.evented = !obj.locked;
        if (obj.locked && obj === this.canvas.getActiveObject()) this.canvas.discardActiveObject();
        this.canvas.renderAll(); this.updateLayers(); this.saveHistory();
    },

    startLayerRename(idx, el) {
        const obj = this.canvas.getObjects()[idx];
        if (!obj) return;
        const currentName = obj.layerName || obj.placeholder || obj.shapeType || obj.type || 'Element';
        const displayName = currentName.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        el.innerHTML = `<input class="layer-rename-input" value="${displayName.replace(/"/g, '&quot;')}" />`;
        const input = el.querySelector('input');
        input.focus();
        input.select();
        const finish = (save) => {
            if (save && input.value.trim()) { obj.layerName = input.value.trim(); }
            this.updateLayers(); if (save) this.saveHistory();
        };
        input.addEventListener('keydown', (e) => {
            e.stopPropagation();
            if (e.key === 'Enter') { e.preventDefault(); finish(true); }
            else if (e.key === 'Escape') { e.preventDefault(); finish(false); }
        });
        input.addEventListener('blur', () => finish(true));
    },

    _layerDragIdx: null,
    layerDragStart(e, idx) {
        this._layerDragIdx = idx;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', idx);
        e.currentTarget.style.opacity = '0.5';
        setTimeout(() => { if (e.currentTarget) e.currentTarget.style.opacity = ''; }, 0);
    },

    layerDragOver(e, idx) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        document.querySelectorAll('.layer-item').forEach(el => el.classList.remove('drag-over'));
        e.currentTarget.classList.add('drag-over');
    },

    layerDrop(e, targetIdx) {
        e.preventDefault();
        e.currentTarget.classList.remove('drag-over');
        const fromIdx = this._layerDragIdx;
        if (fromIdx === null || fromIdx === targetIdx) return;
        const obj = this.canvas.getObjects()[fromIdx];
        if (!obj) return;
        this.canvas.moveTo(obj, targetIdx);
        this.canvas.renderAll(); this.updateLayers(); this.saveHistory();
        this._layerDragIdx = null;
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
        let pendingAsync = 0;
        const totalItems = layout.length;

        layout.forEach((item, layoutIndex) => {
            const x = (item.x / 100) * this.canvasWidth;
            const y = (item.y / 100) * this.canvasHeight;

            if (item.type === 'text' || item.type === 'i-text') {
                const text = new fabric.IText(item.placeholder ? getExampleText(item.placeholder) : (item.text || 'Text'), {
                    left: x, top: y,
                    fontSize: item.fontSize || 24,
                    fontFamily: item.fontFamily || 'Arial',
                    fontWeight: item.fontWeight || '400',
                    fontStyle: item.fontStyle || 'normal',
                    underline: item.underline || false,
                    linethrough: item.linethrough || false,
                    skewX: item.skewX || 0,
                    fill: item.color || '#ffffff',
                    angle: item.rotation || 0,
                    opacity: (item.opacity ?? 100) / 100,
                    textAlign: item.textAlign || 'center',
                    originX: 'center', originY: 'center',
                });
                if (item.shadow) text.shadow = new fabric.Shadow({ color: item.shadow.color || '#000000', blur: item.shadow.blur || 5, offsetX: item.shadow.offsetX || 2, offsetY: item.shadow.offsetY || 2 });
                if (item.stroke) text.set('stroke', item.stroke);
                if (item.strokeWidth) text.set('strokeWidth', item.strokeWidth);
                text.placeholder = item.placeholder;
                text.elementType = 'text';
                text.textTransform = item.textTransform || 'none';
                text._layoutIndex = layoutIndex;
                this.canvas.add(text);
            } else if (item.type === 'image') {
                const w = item.width || 150, h = item.height || 150;
                const rect = new fabric.Rect({ width: w, height: h, fill: 'rgba(99, 102, 241, 0.3)', stroke: '#6366f1', strokeWidth: 2, strokeDashArray: [5, 5], rx: 8, ry: 8, originX: 'center', originY: 'center' });
                const label = new fabric.Text((item.placeholder || 'image').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()), { fontSize: 14, fill: '#fff', fontFamily: 'Arial', originX: 'center', originY: 'center', textAlign: 'center' });
                const group = new fabric.Group([rect, label], { left: x, top: y, angle: item.rotation || 0, opacity: (item.opacity ?? 100) / 100, originX: 'center', originY: 'center' });
                group.placeholder = item.placeholder;
                group.elementType = 'image';
                group.placeholderWidth = w;
                group.placeholderHeight = h;
                group._layoutIndex = layoutIndex;
                this.canvas.add(group);
            } else if (item.type === 'icon' && item.iconType === 'svg') {
                // Restore SVG-based icon (cricket icons)
                pendingAsync++;
                ((savedItem, lx, ly, idx) => {
                    const svgTemplate = this.svgIconMap[savedItem.iconName];
                    if (!svgTemplate) { pendingAsync--; if (pendingAsync === 0) this._reorderByLayoutIndex(); return; }
                    const color = savedItem.color || '#ffffff';
                    const svgString = svgTemplate.replace(/__COLOR__/g, color);
                    fabric.loadSVGFromString(svgString, (objects, options) => {
                        const group = fabric.util.groupSVGElements(objects, options);
                        const scaleX = (savedItem.width || 64) / (group.width || 64);
                        const scaleY = (savedItem.height || 64) / (group.height || 64);
                        group.set({ left: lx, top: ly, originX: 'center', originY: 'center', scaleX, scaleY, angle: savedItem.rotation || 0, opacity: (savedItem.opacity ?? 100) / 100 });
                        group.elementType = 'icon';
                        group.iconType = 'svg';
                        group.iconName = savedItem.iconName;
                        group.iconColor = color;
                        group.placeholder = '';
                        group._layoutIndex = idx;
                        if (savedItem.layerName) group.layerName = savedItem.layerName;
                        if (savedItem.hidden) { group.visible = false; }
                        if (savedItem.locked) { group.selectable = false; group.evented = false; group.locked = true; }
                        this.canvas.add(group);
                        pendingAsync--;
                        if (pendingAsync === 0) this._reorderByLayoutIndex();
                        this.canvas.renderAll();
                    });
                })(item, x, y, layoutIndex);
            } else if (item.type === 'icon') {
                const icon = new fabric.Text(item.iconUnicode || '\uf005', {
                    left: x, top: y,
                    fontSize: item.fontSize || 64,
                    fontFamily: 'Font Awesome 6 Free',
                    fontWeight: '900',
                    fill: item.color || '#ffffff',
                    angle: item.rotation || 0,
                    opacity: (item.opacity ?? 100) / 100,
                    originX: 'center', originY: 'center',
                    textAlign: 'center',
                });
                icon.elementType = 'icon';
                icon.iconName = item.iconName || 'icon';
                icon.iconUnicode = item.iconUnicode || icon.text;
                icon.placeholder = '';
                icon._layoutIndex = layoutIndex;
                this.canvas.add(icon);
            } else if (item.type === 'shape') {
                let shape;
                const solidFill = (typeof item.fill === 'string') ? item.fill : '#6366f1';
                const props = { left: x, top: y, fill: solidFill, stroke: item.stroke ?? null, strokeWidth: item.strokeWidth ?? 0, angle: item.rotation || 0, opacity: (item.opacity ?? 100) / 100, originX: 'center', originY: 'center' };
                if (item.shapeType === 'rect') shape = new fabric.Rect({ ...props, width: item.width || 150, height: item.height || 100, rx: item.rx ?? 0, ry: item.ry ?? 0 });
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
                    // Restore shadow
                    if (item.shadow) {
                        shape.set('shadow', new fabric.Shadow({ color: item.shadow.color || '#000000', blur: item.shadow.blur || 0, offsetX: item.shadow.offsetX || 0, offsetY: item.shadow.offsetY || 0 }));
                    }
                    // Restore per-corner border radii
                    if (item.borderRadii) {
                        shape.borderRadii = item.borderRadii;
                    }
                    this.canvas.add(shape);
                }
            } else if (item.type === 'tableArea') {
                const tw = item.width || 900, th = item.height || 500;
                const border = new fabric.Rect({ width: tw, height: th, fill: 'rgba(30, 64, 175, 0.15)', stroke: '#3b82f6', strokeWidth: 3, strokeDashArray: [10, 5], rx: 8, ry: 8, originX: 'center', originY: 'center' });
                const title = new fabric.Text('POINT TABLE AREA', { fontSize: 20, fill: '#60a5fa', fontFamily: 'Arial', fontWeight: '700', originX: 'center', originY: 'center', top: -th/2 + 30 });
                const sample = new fabric.Text('#  Team              P  W  L  T   NRR    Pts\n1  Team Alpha         5  4  1  0  +1.250   8\n2  Team Beta          5  3  2  0  +0.450   6\n3  Team Gamma         5  2  3  0  -0.320   4\n4  Team Delta         5  1  4  0  -1.100   2', { fontSize: 13, fill: '#94a3b8', fontFamily: 'Courier New', originX: 'center', originY: 'center', top: 20, lineHeight: 1.6 });
                const group = new fabric.Group([border, title, sample], { left: x, top: y, originX: 'center', originY: 'center', angle: item.rotation || 0, opacity: (item.opacity ?? 100) / 100 });
                group.elementType = 'tableArea';
                group.placeholder = 'table_data';
                group.tableConfig = item.tableConfig || {};
                group._layoutIndex = layoutIndex;
                // Apply saved header color to visual
                if (group.tableConfig.headerBg) {
                    const hc = group.tableConfig.headerBg;
                    const r = parseInt(hc.slice(1,3),16), g = parseInt(hc.slice(3,5),16), b = parseInt(hc.slice(5,7),16);
                    border.set('fill', `rgba(${r},${g},${b},0.15)`);
                    border.set('stroke', hc);
                }
                this.canvas.add(group);
            } else if (item.type === 'scorecardTable') {
                const scCfg = item.scorecardConfig || {};
                const sw = item.width || 400, sh = item.height || 180;
                const isBat = scCfg.scorecardType === 'batting';
                const teamLbl = (scCfg.team === 'b') ? 'TEAM B' : 'TEAM A';
                const typeLbl = isBat ? 'BATTING' : 'BOWLING';
                const hdrCol = scCfg.headerBg || '#1e40af';
                const hr = parseInt(hdrCol.slice(1,3),16), hg = parseInt(hdrCol.slice(3,5),16), hb = parseInt(hdrCol.slice(5,7),16);
                const scBorder = new fabric.Rect({ width: sw, height: sh, fill: `rgba(${hr},${hg},${hb},0.12)`, stroke: hdrCol, strokeWidth: 2, strokeDashArray: [8, 4], rx: 6, ry: 6, originX: 'center', originY: 'center' });
                const scTitle = new fabric.Text(teamLbl + ' - ' + typeLbl, { fontSize: 14, fill: isBat ? '#60a5fa' : '#34d399', fontFamily: 'Arial', fontWeight: '700', originX: 'center', originY: 'center', top: -sh/2 + 18 });
                const scSample = isBat
                    ? new fabric.Text('Name          R    B   4s   6s\nVirat K.     72   45    8    3\nRohit S.     56   38    6    2\nKL Rahul     41   30    4    1', { fontSize: 11, fill: '#94a3b8', fontFamily: 'Courier New', originX: 'center', originY: 'center', top: 15, lineHeight: 1.5 })
                    : new fabric.Text('Name          O    R    W  Econ\nJasprit B.  4.0   24    3  6.00\nMohd S.     4.0   32    2  8.00\nRavindra J. 3.0   22    1  7.33', { fontSize: 11, fill: '#94a3b8', fontFamily: 'Courier New', originX: 'center', originY: 'center', top: 15, lineHeight: 1.5 });
                const scGroup = new fabric.Group([scBorder, scTitle, scSample], { left: x, top: y, originX: 'center', originY: 'center', angle: item.rotation || 0, opacity: (item.opacity ?? 100) / 100 });
                scGroup.elementType = 'scorecardTable';
                scGroup.placeholder = item.placeholder || ('batting_table_' + (scCfg.team || 'a'));
                scGroup.scorecardConfig = scCfg;
                scGroup._layoutIndex = layoutIndex;
                this.canvas.add(scGroup);
            } else if (item.type === 'fixtureArea') {
                const fxCfg = item.fixtureConfig || {};
                const fw = item.width || 900, fh = item.height || 500;
                const fxHdrCol = fxCfg.headerBg || '#14b8a6';
                const fr = parseInt(fxHdrCol.slice(1,3),16), fg = parseInt(fxHdrCol.slice(3,5),16), fb = parseInt(fxHdrCol.slice(5,7),16);
                const fxBorder = new fabric.Rect({ width: fw, height: fh, fill: `rgba(${fr},${fg},${fb},0.12)`, stroke: fxHdrCol, strokeWidth: 2, strokeDashArray: [8, 4], rx: 6, ry: 6, originX: 'center', originY: 'center' });
                const fxTitle = new fabric.Text('UPCOMING FIXTURES', { fontSize: 14, fill: '#14b8a6', fontFamily: 'Arial', fontWeight: '700', originX: 'center', originY: 'center', top: -fh/2 + 18 });
                const fxSample = new fabric.Text('Royal Strikers       VS       Thunder Kings\n  Jun 15  |  06:00 PM  |  City Stadium\n\nMountrich CC         VS       Canadian CC\n  Jun 16  |  06:00 PM  |  City Stadium', { fontSize: 11, fill: '#94a3b8', fontFamily: 'Courier New', originX: 'center', originY: 'center', top: 20, lineHeight: 1.4 });
                const fxGroup = new fabric.Group([fxBorder, fxTitle, fxSample], { left: x, top: y, originX: 'center', originY: 'center', angle: item.rotation || 0, opacity: (item.opacity ?? 100) / 100 });
                fxGroup.elementType = 'fixtureArea';
                fxGroup.placeholder = 'fixture_area';
                fxGroup.fixtureConfig = fxCfg;
                fxGroup._layoutIndex = layoutIndex;
                this.canvas.add(fxGroup);
            } else if (item.type === 'uploadedImage') {
                pendingAsync++;
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
                        if (item.layerName) img.layerName = item.layerName;
                        if (item.hidden) { img.visible = false; }
                        if (item.locked) { img.selectable = false; img.evented = false; img.locked = true; }
                        this.canvas.add(img);
                        pendingAsync--;
                        if (pendingAsync === 0) {
                            this._reorderByLayoutIndex();
                        }
                        this.canvas.renderAll();
                    }, { crossOrigin: 'anonymous' });
                } else {
                    pendingAsync--;
                    if (pendingAsync === 0) this._reorderByLayoutIndex();
                }
            }
        });

        // Restore layer states (name, visibility, lock) for all non-image elements
        this.canvas.getObjects().forEach(obj => {
            if (obj._layoutIndex == null) return;
            const item = layout[obj._layoutIndex];
            if (!item) return;
            if (item.layerName) obj.layerName = item.layerName;
            if (item.hidden) { obj.visible = false; }
            if (item.locked) { obj.selectable = false; obj.evented = false; obj.locked = true; }
        });
        // Reorder synchronous elements immediately; async items will reorder again when they complete
        if (pendingAsync === 0) {
            this._reorderByLayoutIndex();
        }
        this.canvas.renderAll();
    },

    _reorderByLayoutIndex() {
        const objects = this.canvas.getObjects().slice();
        objects.sort((a, b) => (a._layoutIndex ?? 0) - (b._layoutIndex ?? 0));
        this.canvas._objects.length = 0;
        objects.forEach(obj => this.canvas._objects.push(obj));
        this.canvas.renderAll();
        this.updateLayers();
    },

    // Save
    save() {
        const btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.innerHTML = '<svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Saving...';

        const objects = this.canvas.getObjects();
        const layout = objects.map((obj, i) => {
            const center = obj.getCenterPoint();
            const base = {
                type: obj.elementType || obj.type,
                placeholder: obj.placeholder || null,
                x: (center.x / this.canvasWidth) * 100,
                y: (center.y / this.canvasHeight) * 100,
                rotation: obj.angle || 0,
                opacity: (obj.opacity ?? 1) * 100,
                zIndex: i,
                layerName: obj.layerName || null,
                hidden: obj.visible === false,
                locked: obj.locked || false,
            };

            if (obj.elementType === 'icon' && obj.iconType === 'svg') {
                return { ...base, type: 'icon', iconType: 'svg', iconName: obj.iconName, color: obj.iconColor || '#ffffff', width: (obj.width || 64) * (obj.scaleX || 1), height: (obj.height || 64) * (obj.scaleY || 1) };
            } else if (obj.elementType === 'icon') {
                return { ...base, type: 'icon', iconType: 'fa', iconUnicode: obj.iconUnicode || obj.text, iconName: obj.iconName || 'icon', fontSize: obj.fontSize, color: obj.fill };
            } else if (obj.elementType === 'text' || obj.type === 'i-text') {
                // Save the actual text content — needed for custom/static text that has no placeholder
                const textContent = obj.text || '';
                return { ...base, text: textContent, fontSize: obj.fontSize, fontFamily: obj.fontFamily, fontWeight: obj.fontWeight, fontStyle: obj.fontStyle || 'normal', underline: obj.underline || false, linethrough: obj.linethrough || false, skewX: obj.skewX || 0, color: obj.fill, textAlign: obj.textAlign, textTransform: obj.textTransform || 'none', shadow: obj.shadow ? { blur: obj.shadow.blur, offsetX: obj.shadow.offsetX, offsetY: obj.shadow.offsetY, color: obj.shadow.color || '#000000' } : null, stroke: obj.stroke || null, strokeWidth: obj.strokeWidth || 0 };
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
                return { ...base, shapeType: obj.shapeType, iconName: obj.iconName || null, fill: fillData, stroke: obj.stroke, strokeWidth: obj.strokeWidth, width: (obj.width || 150) * (obj.scaleX || 1), height: (obj.height || 100) * (obj.scaleY || 1), rx: obj.rx || 0, ry: obj.ry || 0, shadow: obj.shadow ? { blur: obj.shadow.blur, offsetX: obj.shadow.offsetX, offsetY: obj.shadow.offsetY, color: obj.shadow.color || '#000000' } : null, borderRadii: obj.borderRadii || null };
            } else if (obj.elementType === 'uploadedImage') {
                return { ...base, type: 'uploadedImage', imagePath: obj.imagePath, width: (obj.width || 150) * (obj.scaleX || 1), height: (obj.height || 150) * (obj.scaleY || 1) };
            } else if (obj.elementType === 'tableArea') {
                return { ...base, type: 'tableArea', tableConfig: obj.tableConfig || {}, width: (obj.width || 900) * (obj.scaleX || 1), height: (obj.height || 500) * (obj.scaleY || 1) };
            } else if (obj.elementType === 'scorecardTable') {
                return { ...base, type: 'scorecardTable', scorecardConfig: obj.scorecardConfig || {}, width: (obj.width || 400) * (obj.scaleX || 1), height: (obj.height || 180) * (obj.scaleY || 1) };
            } else if (obj.elementType === 'fixtureArea') {
                return { ...base, type: 'fixtureArea', fixtureConfig: obj.fixtureConfig || {}, width: (obj.width || 900) * (obj.scaleX || 1), height: (obj.height || 500) * (obj.scaleY || 1) };
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

        const form = document.getElementById('saveForm');
        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                this.showToast(data.message || 'Saved successfully!', 'success');
                // For new templates, update URL to edit route so subsequent saves use PUT
                if (data.redirect && !form.querySelector('input[name="_method"]')) {
                    window.history.replaceState({}, '', data.redirect);
                    // Add _method PUT for subsequent saves
                    const methodInput = document.createElement('input');
                    methodInput.type = 'hidden';
                    methodInput.name = '_method';
                    methodInput.value = 'PUT';
                    form.appendChild(methodInput);
                    form.action = data.redirect.replace('/edit', '');
                }
            } else {
                this.showToast(data.message || 'Save failed.', 'error');
            }
        })
        .catch(err => {
            console.error('Save failed:', err);
            this.showToast('Save failed. Please try again.', 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Save';
        });
    },

    showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.style.cssText = `position:fixed;top:20px;right:20px;z-index:99999;padding:12px 20px;border-radius:8px;color:#fff;font-size:13px;font-weight:500;box-shadow:0 4px 12px rgba(0,0,0,0.3);transition:opacity 0.3s,transform 0.3s;transform:translateY(-10px);opacity:0;`;
        toast.style.background = type === 'success' ? '#10b981' : '#ef4444';
        toast.textContent = message;
        document.body.appendChild(toast);
        requestAnimationFrame(() => { toast.style.opacity = '1'; toast.style.transform = 'translateY(0)'; });
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    },
};

function switchTab(tab) {
    document.querySelectorAll('.sidebar-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
    document.getElementById(`tab-${tab}`).classList.remove('hidden');
}

function filterIcons(query) {
    const q = query.toLowerCase().trim();
    document.querySelectorAll('#iconGrid .icon-item').forEach(item => {
        const title = (item.getAttribute('title') || '').toLowerCase();
        item.style.display = !q || title.includes(q) ? '' : 'none';
    });
    document.querySelectorAll('#iconGrid .icon-category').forEach(cat => {
        const visibleItems = cat.querySelectorAll('.icon-item:not([style*="display: none"])');
        cat.style.display = visibleItems.length > 0 ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', () => editor.init());
</script>
@endpush
