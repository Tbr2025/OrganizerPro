@extends('backend.layouts.app')

@section('title', 'Player Welcome Image Editor')

@section('admin-content')
    <div class="max-w-6xl mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Welcome Image Editor: {{ $player->name }}</h1>

        <div class="grid md:grid-cols-4 gap-4">
            <!-- Controls -->
            <div class="col-span-1 space-y-4">

                <!-- Upload Player Image -->
                <div>
                    <label class="block font-semibold mb-1">Upload Player Image</label>
                    <input type="file" id="player-image" accept="image/*" class="w-full">
                    <button type="button" id="add-player-image"
                        class="mt-2 bg-blue-600 text-white px-3 py-1 rounded w-full">Add Player Image</button>
                </div>

                <!-- Add Player Name -->
                <div class="space-y-1">
                    <label class="block font-semibold">Player Name</label>
                    <input type="text" id="player-name" class="w-full border rounded px-2 py-1"
                        placeholder="Enter Player Name">
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
                        class="mt-2 bg-green-600 text-white px-3 py-1 rounded w-full">Add Name</button>
                </div>

                <!-- Save Button -->
                <div class="pt-4 border-t mt-4">
                    <button type="button" id="save-final" class="w-full bg-indigo-600 text-white py-2 rounded">Save Welcome
                        Image</button>
                </div>
            </div>

            <!-- Canvas Area -->
            <div class="col-span-3">
                <div class="border relative">
                    <canvas id="canvas" width="500" height="500" class="w-24 h-24"></canvas>
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

        // Load Static Background
        fabric.Image.fromURL('{{ asset('storage/backgrounds/welcome-template.png') }}', function(img) {
            const originalWidth = img.width;
            const originalHeight = img.height;

            // Set canvas size to match image size
            canvas.setWidth(originalWidth);
            canvas.setHeight(originalHeight);

            // Optional: make background non-interactive
            img.selectable = false;
            img.evented = false;

            // Set background image and render
            canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas));
        });


        // Add Player Image
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
                        hasRotatingPoint: true,
                        cornerStyle: 'circle',
                        type: 'playerImage', // Custom flag
                        src: '{{ asset('storage/' . $player->image_path) }}', // actual path to use later
                    });
                    canvas.add(img).setActiveObject(img);
                });
            };
            reader.readAsDataURL(file);
        });


        // Add Player Name
        document.getElementById('add-name-text').addEventListener('click', () => {
            const text = document.getElementById('player-name').value || '{{ $player->name }}';
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
            });
            canvas.add(textbox).setActiveObject(textbox);
        });

        // Save Image and Layout
        document.getElementById('save-final').addEventListener('click', () => {
            const imageData = canvas.toDataURL('image/png');
            const layoutJson = JSON.stringify(canvas.toJSON());
            // const layoutJson = JSON.stringify(canvas.toJSON(['type', 'src']));

            fetch('{{ route('admin.players.saveImage') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        player_id: '{{ $player->id }}',
                        image_data: imageData,
                        layout_data: layoutJson
                    })
                })
                .then(res => res.json())
                .then(data => alert("Welcome image saved successfully!"))
                .catch(err => {
                    console.error("Save error", err);
                    alert("Failed to save image.");
                });
        });
    </script>
@endsection
