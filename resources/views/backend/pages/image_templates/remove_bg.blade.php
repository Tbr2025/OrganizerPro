<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Remove Background Locally</title>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/selfie_segmentation/selfie_segmentation.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js"></script>
    <style>
        canvas {
            border: 1px solid #ccc;
            max-width: 100%;
        }
    </style>
</head>

<body>
    <h2>Upload an Image</h2>
    <input type="file" id="fileInput" accept="image/*">
    <br><br>
    <canvas id="canvas"></canvas>

    <script>
        const fileInput = document.getElementById('fileInput');
        const canvas = document.getElementById('canvas');
        const ctx = canvas.getContext('2d');

        const selfieSegmentation = new SelfieSegmentation.SelfieSegmentation({
            locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/selfie_segmentation/${file}`,
        });

        selfieSegmentation.setOptions({
            modelSelection: 1, // 0 = general, 1 = landscape
        });

        selfieSegmentation.onResults(onResults);

        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const img = new Image();
            const reader = new FileReader();
            reader.onload = () => {
                img.src = reader.result;
            };
            reader.readAsDataURL(file);

            img.onload = () => {
                canvas.width = img.width;
                canvas.height = img.height;
                ctx.drawImage(img, 0, 0, img.width, img.height);
                selfieSegmentation.send({
                    image: img
                });
            };
        });

        function onResults(results) {
            const width = canvas.width;
            const height = canvas.height;

            const imageData = ctx.getImageData(0, 0, width, height);
            const data = imageData.data;
            const mask = results.segmentationMask;

            const maskCanvas = document.createElement('canvas');
            maskCanvas.width = width;
            maskCanvas.height = height;
            const maskCtx = maskCanvas.getContext('2d');
            maskCtx.drawImage(mask, 0, 0, width, height);
            const maskData = maskCtx.getImageData(0, 0, width, height).data;

            for (let i = 0; i < data.length; i += 4) {
                const alpha = maskData[i]; // 0 = background, 255 = person
                if (alpha < 128) {
                    // background pixel
                    data[i + 3] = 0; // make transparent
                }
            }

            ctx.putImageData(imageData, 0, 0);
        }
    </script>
</body>

</html>
