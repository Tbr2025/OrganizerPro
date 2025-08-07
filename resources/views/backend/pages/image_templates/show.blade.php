@extends('backend.layouts.app')

@section('title', 'View Template: ' . $template->name)

@section('admin-content')
    <x-backend.card>
        <x-slot name="header">{{ $template->name }} - Template Preview</x-slot>

        <x-slot name="body">
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Canvas Preview -->
                <div class="w-full max-w-4xl">
                    <div class="relative w-full" style="aspect-ratio: 3 / 2;">
                        <canvas id="canvas" class="w-full h-full border rounded shadow"></canvas>
                    </div>
                </div>

                <!-- Template Info -->
                <div class="flex flex-col gap-3 w-full max-w-sm">
                    <p class="text-gray-700"><strong>Template Name:</strong> {{ $template->name }}</p>

                    @if ($template->background_image)
                        <p class="text-gray-700">
                            <strong>Background Image:</strong><br>
                            <img src="{{ asset('storage/' . $template->background_image) }}"
                                 class="max-w-xs border rounded shadow">
                        </p>
                    @endif

                    @if ($template->category)
                        <p class="text-gray-700">
                            <strong>Category:</strong> {{ $template->category->name }}
                        </p>
                    @endif

                    <a href="{{ route('admin.image-templates.index') }}"
                       class="btn btn-sm btn-secondary mt-4">← Back to Templates</a>
                </div>
            </div>
        </x-slot>
    </x-backend.card>
@endsection

@push('after-scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
<script>
    const canvasEl = document.getElementById('canvas');
    const canvas = new fabric.Canvas(canvasEl);

    // Set canvas size based on actual JSON or fixed ratio
    function resizeCanvasToFitContainer() {
        const container = canvasEl.parentNode;
        canvas.setWidth(container.clientWidth);
        canvas.setHeight(container.clientHeight);
    }

    window.addEventListener('resize', resizeCanvasToFitContainer);
    resizeCanvasToFitContainer();

    const templateJson = @json($template->layout_json);

    // Load background image (if any)
    @if ($template->background_image)
    fabric.Image.fromURL('{{ asset("storage/" . $template->background_image) }}', function(img) {
        canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas), {
            scaleX: canvas.getWidth() / img.width,
            scaleY: canvas.getHeight() / img.height
        });
    });
    @endif

    // Load saved layout
    canvas.loadFromJSON(templateJson, () => {
        // Scale all objects proportionally
        const scaleX = canvas.getWidth() / canvas.backgroundImage.width;
        const scaleY = canvas.getHeight() / canvas.backgroundImage.height;
        canvas.getObjects().forEach(obj => {
            obj.scaleX *= scaleX;
            obj.scaleY *= scaleY;
            obj.left *= scaleX;
            obj.top *= scaleY;
            obj.setCoords();
        });
        canvas.renderAll();
    });
</script>
@endpush
