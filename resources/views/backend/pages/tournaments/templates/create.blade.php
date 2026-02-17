@extends('backend.layouts.app')

@section('title', 'Create Template | ' . $tournament->name)

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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Panel: Settings --}}
        <div class="lg:col-span-1 space-y-4">
            {{-- Basic Info Card --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Template Settings</h3>

                <div class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Template Name *</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                               placeholder="e.g., Default Welcome Card">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Template Type *</label>
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
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Background Image</label>
                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center hover:border-indigo-400 transition cursor-pointer"
                             onclick="document.getElementById('background_image').click()">
                            <input type="file" name="background_image" id="background_image" accept="image/*" class="hidden" onchange="loadBackgroundImage(this)">
                            <svg class="w-8 h-8 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p class="text-sm text-gray-500">Click to upload image</p>
                            <p class="text-xs text-gray-400">1080x1080 or 1080x1350 recommended</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="is_default" id="is_default" value="1" {{ old('is_default') ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="is_default" class="text-sm text-gray-700 dark:text-gray-300">Set as default template</label>
                    </div>
                </div>
            </div>

            {{-- Placeholders Panel --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Placeholders</h3>
                    <span class="text-xs text-gray-500">Drag to canvas</span>
                </div>

                <p class="text-xs text-gray-500 mb-3">Drag placeholders onto the canvas to position them on your template.</p>

                <div id="placeholdersList" class="space-y-2 max-h-[400px] overflow-y-auto">
                    @php
                        $placeholderDescriptions = [
                            'player_name' => 'Full name of the player',
                            'jersey_name' => 'Name on jersey',
                            'jersey_number' => 'Jersey number',
                            'team_name' => 'Team name',
                            'team_logo' => 'Team logo (image)',
                            'tournament_name' => 'Tournament name',
                            'tournament_logo' => 'Tournament logo (image)',
                            'player_image' => 'Player photo (image)',
                            'player_type' => 'Player role',
                            'batting_style' => 'Batting style',
                            'bowling_style' => 'Bowling style',
                            'team_a_name' => 'Team A name',
                            'team_a_logo' => 'Team A logo (image)',
                            'team_b_name' => 'Team B name',
                            'team_b_logo' => 'Team B logo (image)',
                            'match_date' => 'Match date',
                            'match_time' => 'Match time',
                            'venue' => 'Venue name',
                        ];
                    @endphp
                    @foreach($placeholders as $placeholder)
                        <div class="placeholder-item flex items-center gap-2 p-2 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-grab hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition"
                             draggable="true"
                             data-placeholder="{{ $placeholder }}"
                             data-type="{{ in_array($placeholder, ['player_image', 'team_logo', 'tournament_logo', 'team_a_logo', 'team_b_logo', 'man_of_the_match_image']) ? 'image' : 'text' }}">
                            <div class="w-8 h-8 rounded bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center flex-shrink-0">
                                @if(in_array($placeholder, ['player_image', 'team_logo', 'tournament_logo', 'team_a_logo', 'team_b_logo', 'man_of_the_match_image']))
                                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                @else
                                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                                    </svg>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-gray-900 dark:text-white truncate">{!! '&#123;&#123;' . e($placeholder) . '&#125;&#125;' !!}</p>
                                <p class="text-xs text-gray-500 truncate">{{ $placeholderDescriptions[$placeholder] ?? 'Dynamic content' }}</p>
                            </div>
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                            </svg>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Right Panel: Canvas --}}
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Template Canvas</h3>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="clearCanvas()" class="text-xs text-red-600 hover:text-red-800">Clear All</button>
                        <span class="text-gray-300">|</span>
                        <span class="text-xs text-gray-500">Click element to edit</span>
                    </div>
                </div>

                {{-- Canvas Container --}}
                <div id="canvasContainer" class="relative bg-gray-100 dark:bg-gray-900 rounded-lg overflow-hidden mx-auto"
                     style="width: 100%; max-width: 540px; aspect-ratio: 1/1;"
                     ondragover="handleDragOver(event)"
                     ondrop="handleDrop(event)">
                    {{-- Background Image --}}
                    <img id="canvasBackground" src="" alt="Background" class="absolute inset-0 w-full h-full object-cover hidden">

                    {{-- Placeholder for empty canvas --}}
                    <div id="canvasPlaceholder" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400">
                        <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-sm font-medium">Upload a background image</p>
                        <p class="text-xs">Then drag placeholders here to position them</p>
                    </div>

                    {{-- Dropped elements will be added here --}}
                    <div id="droppedElements" class="absolute inset-0"></div>
                </div>

                {{-- Element Editor --}}
                <div id="elementEditor" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hidden">
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Edit Element: <span id="editingElementName" class="text-indigo-600"></span></h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Font Size</label>
                            <input type="number" id="elementFontSize" min="8" max="72" value="16"
                                   class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm"
                                   onchange="updateSelectedElement()">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Font Color</label>
                            <input type="color" id="elementColor" value="#ffffff"
                                   class="w-full h-9 rounded border-gray-300 cursor-pointer"
                                   onchange="updateSelectedElement()">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Font Weight</label>
                            <select id="elementFontWeight" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm"
                                    onchange="updateSelectedElement()">
                                <option value="normal">Normal</option>
                                <option value="bold">Bold</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Width (for images)</label>
                            <input type="number" id="elementWidth" min="20" max="500" value="100"
                                   class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm"
                                   onchange="updateSelectedElement()">
                        </div>
                    </div>
                    <div class="flex justify-end mt-3 gap-2">
                        <button type="button" onclick="deleteSelectedElement()" class="px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 rounded">Delete</button>
                        <button type="button" onclick="closeElementEditor()" class="px-3 py-1.5 text-xs bg-gray-200 hover:bg-gray-300 rounded">Close</button>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ route('admin.tournaments.templates.index', $tournament) }}"
                       class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">
                        Create Template
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
let elements = [];
let selectedElement = null;
let dragOffset = { x: 0, y: 0 };

// Load background image
function loadBackgroundImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('canvasBackground').src = e.target.result;
            document.getElementById('canvasBackground').classList.remove('hidden');
            document.getElementById('canvasPlaceholder').classList.add('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Drag and Drop handlers
document.querySelectorAll('.placeholder-item').forEach(item => {
    item.addEventListener('dragstart', function(e) {
        e.dataTransfer.setData('placeholder', this.dataset.placeholder);
        e.dataTransfer.setData('type', this.dataset.type);
    });
});

function handleDragOver(e) {
    e.preventDefault();
}

function handleDrop(e) {
    e.preventDefault();
    const placeholder = e.dataTransfer.getData('placeholder');
    const type = e.dataTransfer.getData('type');

    if (!placeholder) return;

    const canvas = document.getElementById('canvasContainer');
    const rect = canvas.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;

    addElement(placeholder, type, x, y);
}

function addElement(placeholder, type, x, y) {
    const id = 'element_' + Date.now();
    const element = {
        id: id,
        placeholder: placeholder,
        type: type,
        x: Math.min(Math.max(x, 5), 95),
        y: Math.min(Math.max(y, 5), 95),
        fontSize: type === 'image' ? 0 : 16,
        color: '#ffffff',
        fontWeight: 'bold',
        width: type === 'image' ? 80 : 0
    };

    elements.push(element);
    renderElement(element);
    updateLayoutJson();
}

function renderElement(element) {
    const container = document.getElementById('droppedElements');
    const div = document.createElement('div');
    div.id = element.id;
    div.className = 'absolute cursor-move select-none';
    div.style.left = element.x + '%';
    div.style.top = element.y + '%';
    div.style.transform = 'translate(-50%, -50%)';

    if (element.type === 'image') {
        div.innerHTML = `
            <div class="bg-white/20 border-2 border-dashed border-white/50 rounded-lg p-2 text-center"
                 style="width: ${element.width}px;">
                <svg class="w-8 h-8 mx-auto text-white/70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="text-xs text-white/70 mt-1">${'{{' + element.placeholder + '}}'}</p>
            </div>
        `;
    } else {
        div.innerHTML = `<span style="font-size: ${element.fontSize}px; color: ${element.color}; font-weight: ${element.fontWeight}; text-shadow: 1px 1px 2px rgba(0,0,0,0.8);">${'{{' + element.placeholder + '}}'}</span>`;
    }

    // Make draggable
    div.addEventListener('mousedown', startDrag);
    div.addEventListener('click', (e) => {
        e.stopPropagation();
        selectElement(element.id);
    });

    container.appendChild(div);
}

function startDrag(e) {
    const elementDiv = e.currentTarget;
    const elementId = elementDiv.id;
    const element = elements.find(el => el.id === elementId);
    if (!element) return;

    selectedElement = element;
    selectElement(elementId);

    const canvas = document.getElementById('canvasContainer');

    function onMouseMove(e) {
        const rect = canvas.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width) * 100;
        const y = ((e.clientY - rect.top) / rect.height) * 100;

        element.x = Math.min(Math.max(x, 5), 95);
        element.y = Math.min(Math.max(y, 5), 95);

        elementDiv.style.left = element.x + '%';
        elementDiv.style.top = element.y + '%';
    }

    function onMouseUp() {
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
        updateLayoutJson();
    }

    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
}

function selectElement(elementId) {
    // Remove selection from all
    document.querySelectorAll('#droppedElements > div').forEach(el => {
        el.classList.remove('ring-2', 'ring-indigo-500');
    });

    const element = elements.find(el => el.id === elementId);
    if (!element) return;

    selectedElement = element;

    // Add selection styling
    const div = document.getElementById(elementId);
    if (div) {
        div.classList.add('ring-2', 'ring-indigo-500');
    }

    // Show editor
    document.getElementById('elementEditor').classList.remove('hidden');
    document.getElementById('editingElementName').textContent = '{{' + element.placeholder + '}}';
    document.getElementById('elementFontSize').value = element.fontSize || 16;
    document.getElementById('elementColor').value = element.color || '#ffffff';
    document.getElementById('elementFontWeight').value = element.fontWeight || 'normal';
    document.getElementById('elementWidth').value = element.width || 80;
}

function updateSelectedElement() {
    if (!selectedElement) return;

    selectedElement.fontSize = parseInt(document.getElementById('elementFontSize').value) || 16;
    selectedElement.color = document.getElementById('elementColor').value || '#ffffff';
    selectedElement.fontWeight = document.getElementById('elementFontWeight').value || 'normal';
    selectedElement.width = parseInt(document.getElementById('elementWidth').value) || 80;

    // Re-render element
    const div = document.getElementById(selectedElement.id);
    if (div) {
        if (selectedElement.type === 'image') {
            div.innerHTML = `
                <div class="bg-white/20 border-2 border-dashed border-white/50 rounded-lg p-2 text-center"
                     style="width: ${selectedElement.width}px;">
                    <svg class="w-8 h-8 mx-auto text-white/70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-xs text-white/70 mt-1">${'{{' + selectedElement.placeholder + '}}'}</p>
                </div>
            `;
        } else {
            div.innerHTML = `<span style="font-size: ${selectedElement.fontSize}px; color: ${selectedElement.color}; font-weight: ${selectedElement.fontWeight}; text-shadow: 1px 1px 2px rgba(0,0,0,0.8);">${'{{' + selectedElement.placeholder + '}}'}</span>`;
        }
        div.classList.add('ring-2', 'ring-indigo-500');
    }

    updateLayoutJson();
}

function deleteSelectedElement() {
    if (!selectedElement) return;

    const div = document.getElementById(selectedElement.id);
    if (div) div.remove();

    elements = elements.filter(el => el.id !== selectedElement.id);
    selectedElement = null;
    closeElementEditor();
    updateLayoutJson();
}

function closeElementEditor() {
    document.getElementById('elementEditor').classList.add('hidden');
    document.querySelectorAll('#droppedElements > div').forEach(el => {
        el.classList.remove('ring-2', 'ring-indigo-500');
    });
    selectedElement = null;
}

function clearCanvas() {
    if (!confirm('Clear all elements from canvas?')) return;
    document.getElementById('droppedElements').innerHTML = '';
    elements = [];
    closeElementEditor();
    updateLayoutJson();
}

function updateLayoutJson() {
    const layoutData = elements.map(el => ({
        placeholder: el.placeholder,
        type: el.type,
        x: Math.round(el.x * 10) / 10,
        y: Math.round(el.y * 10) / 10,
        fontSize: el.fontSize,
        color: el.color,
        fontWeight: el.fontWeight,
        width: el.width
    }));
    document.getElementById('layoutJsonInput').value = JSON.stringify(layoutData);
}

// Click outside to deselect
document.getElementById('canvasContainer').addEventListener('click', function(e) {
    if (e.target === this || e.target.id === 'canvasBackground' || e.target.id === 'droppedElements') {
        closeElementEditor();
    }
});

// Update placeholders when type changes
function updatePlaceholders() {
    const type = document.getElementById('type').value;
    // In a real app, you'd fetch placeholders via AJAX or have them pre-loaded
    // For now, we just clear existing elements when type changes
    if (elements.length > 0) {
        if (confirm('Changing template type will clear existing elements. Continue?')) {
            clearCanvas();
        }
    }
}

// Form submission validation
document.getElementById('templateForm').addEventListener('submit', function(e) {
    updateLayoutJson();
});
</script>
@endpush
@endsection
