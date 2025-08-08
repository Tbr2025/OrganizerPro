@extends('backend.layouts.app')

@section('title', 'Create Welcome Image Template')

@section('admin-content')
    <div class="max-w-6xl mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Welcome Image Template Editor</h1>

        <div class="grid md:grid-cols-4 gap-4">
            <!-- Controls -->
            <div class="col-span-1 space-y-4">

                <!-- Upload Background Image -->
                <div>
                    <label class="block font-semibold mb-1">Upload Background Image</label>
                    <input type="file" id="bg-image" accept="image/*" class="w-full">
                    <button type="button" id="add-bg-image" class="mt-2 bg-blue-700 text-white px-3 py-1 rounded w-full">
                        Set as Background
                    </button>
                </div>

                <!-- Upload Placeholder Player Image -->
                <div>
                    <label class="block font-semibold mb-1">Upload Placeholder Player Image</label>
                    <input type="file" id="player-image" accept="image/*" class="w-full">
                    <button type="button" id="add-player-image"
                        class="mt-2 bg-blue-600 text-white px-3 py-1 rounded w-full">
                        Add Placeholder
                    </button>
                </div>

                <!-- Upload Static Overlay (e.g., frame/logo over player image) -->
                <div>
                    <label class="block font-semibold mb-1">Upload Static Overlay</label>
                    <input type="file" id="overlay-image" accept="image/*" class="w-full">
                    <button type="button" id="add-overlay-image"
                        class="mt-2 bg-purple-600 text-white px-3 py-1 rounded w-full">
                        Add Overlay
                    </button>
                </div>

                <!-- Add Placeholder Text -->
                <div class="space-y-1">
                    <label class="block font-semibold">Placeholder Text</label>
                    <input type="text" id="player-name" class="w-full border rounded px-2 py-1"
                        placeholder="e.g. <<PLAYER_NAME>>">
                    <label class="block font-semibold">Font</label>
                    <select id="font" class="w-full border rounded px-2 py-1">
                        <option value="Arial">Arial</option>
                        <option value="Georgia">Georgia</option>
                        <option value="Impact">Impact</option>
                    </select>
                    <label class="block font-semibold">Font Size</label>
                    <input type="number" id="font-size" class="w-full border rounded px-2 py-1" value="48">
                    <label class="block font-semibold">Color</label>
                    <input type="color" id="color" class="w-full">
                    <button type="button" id="add-name-text"
                        class="mt-2 bg-green-600 text-white px-3 py-1 rounded w-full">Add Text</button>
                </div>

                <!-- Template Name -->
                <div class="mt-4">
                    <label class="block font-semibold mb-1">Template Name</label>
                    <input type="text" id="template-name" class="w-full border rounded px-2 py-1"
                        placeholder="e.g. Welcome Template">
                </div>
                <!-- Template Category -->
                <div class="mt-4">
                    <label class="block font-semibold mb-1">Template Category</label>
                    <select id="template-category" name="category_id" class="w-full border rounded px-2 py-1">
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <!-- Save Button -->
                <div class="pt-4 border-t mt-4">
                    <button type="button" id="save-template" class="w-full bg-indigo-600 text-white py-2 rounded">
                        Save Template
                    </button>
                </div>
            </div>

            <!-- Canvas Area -->
            <div class="col-span-3">
                <div class="border relative">
                    <canvas id="canvas" width="500" height="500" class="w-full h-auto"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Fabric.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.2.4/fabric.min.js"></script>

    <script>
        const canvas = new fabric.Canvas('canvas', {
            preserveObjectStacking: true,
            selection: true
        });

        // Upload and set background image
        document.getElementById('add-bg-image').addEventListener('click', () => {
            const file = document.getElementById('bg-image').files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                fabric.Image.fromURL(e.target.result, function(img) {
                    canvas.setWidth(img.width);
                    canvas.setHeight(img.height);
                    img.selectable = false;
                    img.evented = false;
                    canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas));
                });
            };
            reader.readAsDataURL(file);
        });

        // Add or replace player image placeholder
        document.getElementById('add-player-image').addEventListener('click', () => {
            const file = document.getElementById('player-image').files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                fabric.Image.fromURL(e.target.result, function(img) {
                    img.set({
                        left: 200,
                        top: 300,
                        scaleX: 0.4,
                        scaleY: 0.4,
                        type: 'playerImage',
                        placeholder: true
                    });

                    // Remove existing player image
                    const existing = canvas.getObjects().find(o => o.type === 'playerImage');
                    if (existing) {
                        canvas.remove(existing);
                    }

                    canvas.add(img).bringToFront().setActiveObject(img);
                });
            };
            reader.readAsDataURL(file);
        });

        // Add static overlay (e.g., frame) above player image
        document.getElementById('add-overlay-image').addEventListener('click', () => {
            const file = document.getElementById('overlay-image').files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                fabric.Image.fromURL(e.target.result, function(img) {
                    img.set({
                        left: 200,
                        top: 300,
                        scaleX: 0.4,
                        scaleY: 0.4,
                        type: 'staticOverlay',
                        placeholder: false
                    });

                    canvas.add(img).bringToFront().setActiveObject(img);
                });
            };
            reader.readAsDataURL(file);
        });

        // Add player name text
        document.getElementById('add-name-text').addEventListener('click', () => {
            const text = document.getElementById('player-name').value || '<<PLAYER_NAME>>';
            const font = document.getElementById('font').value;
            const fontSize = parseInt(document.getElementById('font-size').value) || 48;
            const color = document.getElementById('color').value;

            const textbox = new fabric.Textbox(text, {
                left: 100,
                top: 850,
                fontFamily: font,
                fontSize: fontSize,
                fill: color,
                editable: true,
                type: 'playerName'
            });

            canvas.add(textbox).bringToFront().setActiveObject(textbox);
        });

        // Save template to backend
        document.getElementById('save-template').addEventListener('click', () => {
            const name = document.getElementById('template-name').value.trim();
            const fileInput = document.getElementById('bg-image');
            const overlayFile = document.getElementById('overlay-image').files[0];
            const categoryId = document.getElementById('template-category').value;

            const backgroundImage = fileInput.files[0];

            if (!name || !backgroundImage) {
                alert('Please enter a template name and upload a background image');
                return;
            }

            const layoutJson = JSON.stringify(canvas.toJSON(['type', 'placeholder']));

            const formData = new FormData();
            formData.append('name', name);
            formData.append('layout_json', layoutJson);
            formData.append('background_image', backgroundImage);
            formData.append('overlay_image', overlayFile); // Add this line
            formData.append('category_id', categoryId);

            fetch('{{ route('admin.image-templates.store') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(res => res.json())
                .then(data => {
                    alert('Template saved successfully!');
                    window.location.href = '{{ route('admin.image-templates.index') }}';
                })
                .catch(err => {
                    console.error('Save error', err);
                    alert('Failed to save template.');
                });
        });
    </script>
@endsection
