@props([
    'name' => 'image_path',
    'existingImage' => null,
    'isVerified' => false,
    'required' => false,
    'fieldConfig' => null,
    'mode' => 'player',
    'processRoute' => null,
    'statusRoute' => null,
])

@php
    $visible = $fieldConfig ? ($fieldConfig['image']['visible'] ?? true) : true;
    $isRequired = $fieldConfig ? ($fieldConfig['image']['required'] ?? false) : $required;
    $uniqueId = 'piu_' . Str::random(6);
    $processUrl = $processRoute ?? route('player-image.process');
    $statusUrl = $statusRoute ?? route('player-image.status');

    $modeConfig = match($mode) {
        'captain' => [
            'title' => 'Captain / Featured Player Image',
            'ratios' => [
                ['label' => '3:4', 'value' => '0.75', 'icon' => 'fa-portrait'],
                ['label' => '4:3', 'value' => '1.333', 'icon' => 'fa-image'],
                ['label' => '16:9', 'value' => '1.778', 'icon' => 'fa-tv'],
                ['label' => '1:1', 'value' => '1', 'icon' => 'fa-square'],
                ['label' => 'Free', 'value' => 'free', 'icon' => 'fa-crop'],
            ],
            'guidelines' => [
                'Portrait orientation recommended',
                'Plain background preferred (auto-removed)',
                'Minimum 400x500px',
                'PNG, JPG, WebP — max 6MB',
            ],
            'minW' => 400, 'minH' => 500, 'maxW' => 1600, 'maxH' => 2000,
        ],
        default => [
            'title' => 'Player Photo',
            'ratios' => [
                ['label' => '3:4', 'value' => '0.75', 'icon' => 'fa-portrait'],
                ['label' => '1:1', 'value' => '1', 'icon' => 'fa-square'],
                ['label' => 'Free', 'value' => 'free', 'icon' => 'fa-crop'],
            ],
            'guidelines' => [
                'Clear, front-facing photo',
                'Plain background preferred (auto-removed)',
                'Minimum 400x533px',
                'PNG or JPG, max 6MB',
            ],
            'minW' => 400, 'minH' => 533, 'maxW' => 1600, 'maxH' => 2133,
        ],
    };
@endphp

@if($visible)
<div x-data="playerImageUpload_{{ $uniqueId }}()" x-init="init()">
    {{-- Photo Guidelines --}}
    <div class="mb-3 flex items-start gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
        <div class="flex-shrink-0 w-12 h-14 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
            <svg class="w-6 h-8 text-gray-400" fill="currentColor" viewBox="0 0 24 32">
                <ellipse cx="12" cy="8" rx="5" ry="6"/>
                <path d="M2 28c0-6 4-10 10-10s10 4 10 10"/>
            </svg>
        </div>
        <div class="text-xs text-gray-600 dark:text-gray-400">
            <p class="font-semibold text-gray-700 dark:text-gray-300 mb-1">Photo Guidelines</p>
            <ul class="space-y-0.5 list-disc list-inside">
                @foreach($modeConfig['guidelines'] as $guideline)
                    <li>{{ $guideline }}</li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Verified Lock --}}
    @if($isVerified)
    <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-600 dark:text-red-400 font-semibold mb-3">
        Image is verified and cannot be changed.
    </div>
    @endif

    {{-- Hidden input for processed path --}}
    <input type="hidden" name="{{ $name }}" x-model="processedPath" />

    {{-- Upload Dropzone --}}
    <div x-show="!processedPath && !processing"
         @drop.prevent="handleDrop($event)"
         @dragover.prevent="dragging = true"
         @dragleave.prevent="dragging = false"
         :class="{
            'border-blue-500 bg-blue-50 dark:bg-blue-900/20': dragging,
            'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-700/50': !dragging,
            'cursor-pointer': !isVerified,
            'cursor-not-allowed opacity-50': isVerified
         }"
         class="border-2 border-dashed rounded-xl p-6 text-center transition-all duration-200"
         @click="!isVerified && $refs.fileInput_{{ $uniqueId }}.click()">
        <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only"
               x-ref="fileInput_{{ $uniqueId }}" @change="handleFileSelect($event)"
               {{ $isVerified ? 'disabled' : '' }}>

        <div class="flex flex-col items-center">
            <div class="w-12 h-12 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center mb-3"
                 :class="dragging ? 'bg-blue-100 dark:bg-blue-800' : ''">
                <svg class="w-6 h-6" :class="dragging ? 'text-blue-500' : 'text-gray-400 dark:text-gray-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                <span class="text-blue-600 dark:text-blue-400">Click to upload</span>
                <span class="text-gray-500 dark:text-gray-400"> or drag and drop</span>
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">PNG, JPG, WebP (max 6MB)</p>
        </div>
    </div>

    {{-- Processing Spinner --}}
    <div x-show="processing" x-cloak class="border-2 border-dashed border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20 p-8 rounded-xl text-center">
        <svg class="animate-spin h-10 w-10 mx-auto mb-3 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <p class="text-sm text-blue-600 dark:text-blue-400 font-medium">Processing image...</p>
        <p class="text-xs text-gray-500 mt-1">Cropping & optimizing</p>
    </div>

    {{-- Processed Preview --}}
    <div x-show="processedPath" x-cloak
         class="border border-gray-200 dark:border-gray-600 rounded-xl overflow-hidden bg-white dark:bg-gray-800 group relative">
        <div class="h-52 flex items-center justify-center bg-gray-50 dark:bg-gray-900/50 p-4 relative"
             style="background-image: url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22><rect width=%228%22 height=%228%22 fill=%22%23f0f0f0%22/><rect x=%228%22 y=%228%22 width=%228%22 height=%228%22 fill=%22%23f0f0f0%22/></svg>'); background-size: 16px 16px;">
            <img :src="previewUrl" class="max-h-full max-w-full object-contain rounded-lg" x-show="previewUrl" />
            {{-- Hover overlay --}}
            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center"
                 x-show="!isVerified">
                <button type="button" @click="resetUpload()"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-white/90 text-gray-800 rounded-lg text-sm font-medium shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Change Photo
                </button>
            </div>
        </div>
        {{-- Status bar --}}
        <div class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <template x-if="bgProcessing">
                    <span class="inline-flex items-center gap-1.5 text-xs text-blue-600 dark:text-blue-400">
                        <svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Removing background...
                    </span>
                </template>
                <template x-if="bgSkipped">
                    <span class="inline-flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400">
                        <i class="fas fa-check-circle"></i>
                        Transparent — no removal needed
                    </span>
                </template>
                <template x-if="!bgProcessing && !bgSkipped && processedPath">
                    <span class="inline-flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400">
                        <i class="fas fa-check-circle"></i>
                        Ready
                    </span>
                </template>
            </div>
            <span class="text-xs text-gray-400" x-text="fileName" x-show="fileName"></span>
        </div>
    </div>

    {{-- Existing Image (edit mode, no new upload yet) --}}
    @if($existingImage)
    <div x-show="!processedPath && !processing && showExisting" x-cloak
         class="border border-gray-200 dark:border-gray-600 rounded-xl overflow-hidden bg-white dark:bg-gray-800 group relative">
        <div class="h-52 flex items-center justify-center bg-gray-50 dark:bg-gray-900/50 p-4"
             style="background-image: url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22><rect width=%228%22 height=%228%22 fill=%22%23f0f0f0%22/><rect x=%228%22 y=%228%22 width=%228%22 height=%228%22 fill=%22%23f0f0f0%22/></svg>'); background-size: 16px 16px;">
            <img src="{{ Storage::url($existingImage) }}" class="max-h-full max-w-full object-contain rounded-lg" />
            {{-- Hover overlay --}}
            @unless($isVerified)
            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                <span @click="$refs.fileInput_{{ $uniqueId }}.click()"
                      class="inline-flex items-center gap-1.5 px-4 py-2 bg-white/90 text-gray-800 rounded-lg text-sm font-medium shadow-sm cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Change Photo
                </span>
            </div>
            @endunless
        </div>
        <div class="px-4 py-2 bg-gray-100 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
            <span class="text-xs text-gray-500">Current image</span>
        </div>
    </div>
    @endif

    {{-- Error message --}}
    <template x-if="errorMsg">
        <p class="text-sm text-red-500 mt-2" x-text="errorMsg"></p>
    </template>

    @error($name)
        <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
    @enderror

    {{-- Crop Modal --}}
    <div x-show="showCropModal" x-cloak
         class="fixed inset-0 z-[9999] flex items-end sm:items-center justify-center bg-black/70 backdrop-blur-sm"
         @keydown.escape.window="closeCropModal()">
        <div class="bg-white dark:bg-gray-800 rounded-t-2xl sm:rounded-2xl shadow-2xl w-full max-w-2xl sm:mx-4 overflow-hidden"
             @click.outside="closeCropModal()">
            {{-- Modal Header --}}
            <div class="flex items-center justify-between p-4 border-b dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                    <i class="fas fa-crop-alt text-blue-500"></i>
                    Crop Image
                </h3>
                <button type="button" @click="closeCropModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Crop Mode Buttons --}}
            <div class="flex flex-wrap gap-2 px-4 pt-3">
                @foreach($modeConfig['ratios'] as $ratio)
                <button type="button" @click="setCropMode('{{ $ratio['value'] }}')"
                        :class="cropMode === '{{ $ratio['value'] }}'
                            ? 'bg-blue-600 text-white shadow-md'
                            : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                        class="px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-1.5">
                    <i class="fas {{ $ratio['icon'] }} text-xs"></i>
                    {{ $ratio['label'] }}
                </button>
                @endforeach
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400 px-4 mt-2">Drag to position. Resize the crop area to select the portion you need.</p>

            {{-- Cropper Area --}}
            <div class="p-4">
                <div class="bg-gray-100 dark:bg-gray-900 rounded-lg overflow-hidden" style="max-height: min(400px, 55vh);">
                    <img x-ref="cropImage_{{ $uniqueId }}" class="max-w-full" style="display:block;" />
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="flex items-center justify-between p-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer select-none"
                           x-show="!fileIsTransparent">
                        <input type="checkbox" x-model="removeBg"
                               class="w-4 h-4 text-blue-600 rounded border-gray-300 dark:border-gray-600 focus:ring-blue-500">
                        Remove background
                    </label>
                    <span x-show="fileIsTransparent" class="text-xs text-green-600 dark:text-green-400 flex items-center gap-1">
                        <i class="fas fa-check-circle"></i> Transparent image detected
                    </span>
                </div>
                <div class="flex gap-3">
                    <button type="button" @click="closeCropModal()"
                            class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                        Cancel
                    </button>
                    <button type="button" @click="cropAndProcess()"
                            class="px-5 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition font-medium flex items-center gap-2">
                        <i class="fas fa-check"></i>
                        Crop & Apply
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@pushOnce('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css" />
@endPushOnce

@pushOnce('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
@endPushOnce

<script>
function playerImageUpload_{{ $uniqueId }}() {
    return {
        processedPath: '',
        previewUrl: '',
        processing: false,
        bgProcessing: false,
        bgSkipped: false,
        errorMsg: '',
        showCropModal: false,
        cropMode: '{{ $modeConfig['ratios'][0]['value'] }}',
        cropper: null,
        rawFile: null,
        isVerified: {{ $isVerified ? 'true' : 'false' }},
        showExisting: true,
        dragging: false,
        fileName: '',
        removeBg: true,
        fileIsTransparent: false,

        init() {
            @if($existingImage)
                this.processedPath = '{{ $existingImage }}';
                this.previewUrl = '{{ Storage::url($existingImage) }}';
                this.showExisting = true;
            @endif
        },

        handleFileSelect(event) {
            if (this.isVerified) return;
            const file = event.target.files[0];
            if (file) this.openCropper(file);
        },

        handleDrop(event) {
            if (this.isVerified) return;
            this.dragging = false;
            const file = event.dataTransfer.files[0];
            if (file) this.openCropper(file);
        },

        openCropper(file) {
            if (!file.type.startsWith('image/')) {
                this.errorMsg = 'Please select a valid image file (PNG, JPG, or WebP).';
                return;
            }
            if (file.size > 6 * 1024 * 1024) {
                this.errorMsg = 'Image must be less than 6MB.';
                return;
            }
            this.errorMsg = '';
            this.rawFile = file;
            this.fileName = file.name;

            // Detect transparency for PNG/WebP
            this.fileIsTransparent = false;
            this.removeBg = true;

            if (file.type === 'image/png' || file.type === 'image/webp') {
                this.detectTransparency(file);
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                this.showCropModal = true;
                this.$nextTick(() => {
                    const img = this.$refs.cropImage_{{ $uniqueId }};
                    img.src = e.target.result;
                    if (this.cropper) this.cropper.destroy();

                    const ratio = this.cropMode === 'free' ? NaN : parseFloat(this.cropMode);
                    this.cropper = new Cropper(img, {
                        aspectRatio: isNaN(ratio) ? NaN : ratio,
                        viewMode: 1,
                        autoCropArea: 0.9,
                        responsive: true,
                        dragMode: 'move',
                        restore: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false,
                    });
                });
            };
            reader.readAsDataURL(file);
        },

        detectTransparency(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const size = 10;
                    canvas.width = img.width;
                    canvas.height = img.height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);

                    let transparentCount = 0;
                    const corners = [
                        [0, 0], [img.width - size, 0],
                        [0, img.height - size], [img.width - size, img.height - size]
                    ];

                    for (const [sx, sy] of corners) {
                        const data = ctx.getImageData(Math.max(0, sx), Math.max(0, sy), Math.min(size, img.width), Math.min(size, img.height)).data;
                        for (let i = 3; i < data.length; i += 4) {
                            if (data[i] < 128) transparentCount++;
                        }
                    }

                    const totalSamples = corners.length * size * size;
                    if (transparentCount / totalSamples > 0.2) {
                        this.fileIsTransparent = true;
                        this.removeBg = false;
                    }
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        },

        setCropMode(mode) {
            this.cropMode = mode;
            if (this.cropper) {
                const ratio = mode === 'free' ? NaN : parseFloat(mode);
                this.cropper.setAspectRatio(isNaN(ratio) ? NaN : ratio);
            }
        },

        closeCropModal() {
            this.showCropModal = false;
            if (this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }
            const input = this.$refs.fileInput_{{ $uniqueId }};
            if (input) input.value = '';
        },

        async cropAndProcess() {
            if (!this.cropper) return;

            const canvas = this.cropper.getCroppedCanvas({
                minWidth: {{ $modeConfig['minW'] }},
                minHeight: {{ $modeConfig['minH'] }},
                maxWidth: {{ $modeConfig['maxW'] }},
                maxHeight: {{ $modeConfig['maxH'] }},
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });

            this.closeCropModal();
            this.processing = true;
            this.showExisting = false;
            this.bgSkipped = false;

            const dataUrl = canvas.toDataURL('image/png');

            try {
                const response = await fetch('{{ $processUrl }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        image: dataUrl,
                        skip_bg_removal: !this.removeBg,
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    this.processedPath = data.path;
                    this.previewUrl = data.url;
                    if (data.bgProcessing) {
                        this.bgProcessing = true;
                        this.pollBgStatus();
                    } else if (!this.removeBg || this.fileIsTransparent) {
                        this.bgSkipped = true;
                    }
                } else {
                    this.errorMsg = data.message || 'Failed to process image.';
                }
            } catch (err) {
                this.errorMsg = 'Network error. Please try again.';
                console.error('Image process error:', err);
            } finally {
                this.processing = false;
            }
        },

        pollBgStatus() {
            const interval = setInterval(async () => {
                try {
                    const res = await fetch(`{{ $statusUrl }}?path=${encodeURIComponent(this.processedPath)}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        }
                    });
                    const data = await res.json();
                    if (data.done) {
                        clearInterval(interval);
                        this.bgProcessing = false;
                        this.previewUrl = data.url;
                    }
                } catch (e) {
                    clearInterval(interval);
                    this.bgProcessing = false;
                }
            }, 3000);
            setTimeout(() => { clearInterval(interval); this.bgProcessing = false; }, 120000);
        },

        resetUpload() {
            this.processedPath = '';
            this.previewUrl = '';
            this.bgProcessing = false;
            this.bgSkipped = false;
            this.errorMsg = '';
            this.showExisting = false;
            this.fileName = '';
            this.fileIsTransparent = false;
            this.removeBg = true;
            const input = this.$refs.fileInput_{{ $uniqueId }};
            if (input) input.value = '';
        },
    };
}
</script>
@endif
