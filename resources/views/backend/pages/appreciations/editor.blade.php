    @extends('backend.layouts.app')

    @section('content')
    <div class="p-4">
        <h2 class="text-2xl font-bold mb-4">Customize Appreciation</h2>

        <div class="flex flex-col md:flex-row gap-6">
            <div>
                <canvas id="appreciationCanvas" width="1080" height="1080" class="border shadow"></canvas>

                <button id="saveBtn" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded">Save Appreciation</button>
            </div>

            <div class="flex-1">
                <label class="block mb-2 font-semibold">Title Line 1</label>
                <input type="text" id="titleLine1" class="w-full border p-2 mb-4" placeholder="STAR OF THE">

                <label class="block mb-2 font-semibold">Title Line 2</label>
                <input type="text" id="titleLine2" class="w-full border p-2 mb-4" placeholder="MATCH">

                <label class="block mb-2 font-semibold">Overlay Player Name</label>
                <input type="text" id="overlayName" class="w-full border p-2 mb-4">

                <label class="block mb-2 font-semibold">Font</label>
                <select id="fontFamily" class="w-full border p-2 mb-4">
                    <option value="Oswald">Oswald</option>
                    <option value="Arial">Arial</option>
                    <option value="Impact">Impact</option>
                </select>
            </div>
        </div>
    </div>
    @endsection

    @push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.2.4/fabric.min.js"></script>
    <script>
        const canvas = new fabric.Canvas('appreciationCanvas');

        // Background
        fabric.Image.fromURL('/images/themes/Layer 0.png', (img) => {
            img.selectable = false;
            canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas));
        });

        // Foreground layers (you can add Layer 1, Layer 3 if needed)
        fabric.Image.fromURL('/images/themes/Layer 1.png', (img) => {
            img.set({ left: 0, top: 960, selectable: false });
            canvas.add(img);
            canvas.sendToBack(img);
        });

        // Add text
        const titleLine1 = new fabric.Text('STAR OF THE', {
            left: 200, top: 700, fontSize: 60, fill: 'white', angle: 25, fontFamily: 'Oswald'
        });

        const titleLine2 = new fabric.Text('MATCH', {
            left: 250, top: 770, fontSize: 70, fill: 'white', angle: 25, fontFamily: 'Oswald'
        });

        const overlayText = new fabric.Text('PLAYER NAME', {
            left: 300, top: 880, fontSize: 40, fill: 'yellow', angle: 10, fontFamily: 'Oswald'
        });

        canvas.add(titleLine1, titleLine2, overlayText);

        // Bind input
        document.getElementById('titleLine1').oninput = e => titleLine1.set({ text: e.target.value });
        document.getElementById('titleLine2').oninput = e => titleLine2.set({ text: e.target.value });
        document.getElementById('overlayName').oninput = e => overlayText.set({ text: e.target.value });
        document.getElementById('fontFamily').onchange = e => {
            const font = e.target.value;
            titleLine1.set({ fontFamily: font });
            titleLine2.set({ fontFamily: font });
            overlayText.set({ fontFamily: font });
            canvas.renderAll();
        };

        // Save button
        document.getElementById('saveBtn').onclick = function () {
            const jsonData = JSON.stringify(canvas.toJSON());
            const imageData = canvas.toDataURL({
                format: 'jpeg',
                quality: 0.9
            });

            fetch('{{ route("admin.appreciations.save", [$tournament->id, $match->id, $player->id]) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({ canvas_data: jsonData, image_data: imageData })
            }).then(res => res.json()).then(res => {
                alert('Saved successfully');
            });
        };
    </script>
    @endpush
