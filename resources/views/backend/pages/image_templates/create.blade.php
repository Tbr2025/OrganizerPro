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


             // Temporarily remove background image from canvas
        const originalBg = canvas.backgroundImage;
        canvas.setBackgroundImage(null, canvas.renderAll.bind(canvas));

        const layoutJson = JSON.stringify(canvas.toJSON(['type', 'placeholder']));

        // Restore background image after JSON export
        canvas.setBackgroundImage(originalBg, canvas.renderAll.bind(canvas));

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