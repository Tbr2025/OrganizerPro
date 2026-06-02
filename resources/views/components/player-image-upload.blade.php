@props([
    'name' => 'image_path',
    'existingImage' => null,
    'isVerified' => false,
    'required' => false,
    'fieldConfig' => null,
])

@php
    $visible = $fieldConfig ? ($fieldConfig['image']['visible'] ?? true) : true;
    $isRequired = $fieldConfig ? ($fieldConfig['image']['required'] ?? false) : $required;
    $uniqueId = 'piu_' . Str::random(6);
@endphp

@if($visible)
<div x-data="playerImageUpload_{{ $uniqueId }}()" x-init="init()">
    {{-- Photo Guidelines --}}
    <div class="mb-3 flex items-start gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
        <div class="flex-shrink-0 w-16 h-20 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
            <svg class="w-8 h-10 text-gray-400" fill="currentColor" viewBox="0 0 24 32">
                <ellipse cx="12" cy="8" rx="5" ry="6"/>
                <path d="M2 28c0-6 4-10 10-10s10 4 10 10"/>
            </svg>
        </div>
        <div class="text-xs text-gray-600 dark:text-gray-400">
            <p class="font-semibold text-gray-700 dark:text-gray-300 mb-1">Photo Guidelines</p>
            <ul class="space-y-0.5 list-disc list-inside">
                <li>Clear, front-facing photo</li>
                <li>Plain background preferred (auto-removed)</li>
                <li>Minimum 400×533px</li>
                <li>PNG or JPG, max 6MB</li>
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

    {{-- Upload Area --}}
    <div x-show="!processedPath && !processing"
         @drop.prevent="handleDrop($event)" @dragover.prevent
         class="border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-blue-500 bg-gray-50 dark:bg-gray-800/50 p-6 rounded-lg text-center transition-colors"
         :class="{ 'cursor-pointer': !isVerified, 'cursor-not-allowed opacity-50': isVerified }"
         @click="!isVerified && $refs.fileInput_{{ $uniqueId }}.click()">
        <input type="file" accept="image/png,image/jpeg" class="hidden"
               x-ref="fileInput_{{ $uniqueId }}" @change="handleFileSelect($event)"
               {{ $isVerified ? 'disabled' : '' }}>

        <div class="text-gray-500 dark:text-gray-400">
            <svg class="w-10 h-10 mx-auto mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <p class="text-sm font-medium">Drag & drop or click to upload</p>
            <p class="text-xs mt-1">PNG or JPG (max 6MB)</p>
        </div>
    </div>

    {{-- Processing Spinner --}}
    <div x-show="processing" x-cloak class="border-2 border-dashed border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20 p-8 rounded-lg text-center">
        <svg class="animate-spin h-10 w-10 mx-auto mb-3 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <p class="text-sm text-blue-600 dark:text-blue-400 font-medium">Processing image...</p>
        <p class="text-xs text-gray-500 mt-1">Cropping & optimizing</p>
    </div>

    {{-- Processed Preview --}}
    <div x-show="processedPath" x-cloak class="border border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center bg-white dark:bg-gray-800">
        <img :src="previewUrl" class="mx-auto mb-3 h-48 object-contain rounded-lg" x-show="previewUrl" />
        <div x-show="bgProcessing" x-cloak class="mt-2 inline-flex items-center gap-1.5 text-xs text-blue-600 dark:text-blue-400">
            <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Removing background...
        </div>
        <button type="button" @click="resetUpload()" x-show="!isVerified"
                class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
            Change Photo
        </button>
    </div>

    {{-- Existing Image (edit mode, no new upload yet) --}}
    @if($existingImage)
    <div x-show="!processedPath && !processing && showExisting" x-cloak class="mt-3 border border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center bg-white dark:bg-gray-800">
        <img src="{{ Storage::url($existingImage) }}" class="mx-auto mb-3 h-48 object-contain rounded-lg" />
        <p class="text-xs text-gray-500">Current image</p>
    </div>
    @endif

    {{-- Error message --}}
    <template x-if="errorMsg">
        <p class="text-sm text-red-500 mt-1" x-text="errorMsg"></p>
    </template>

    @error($name)
        <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
    @enderror

    {{-- Crop Modal --}}
    <div x-show="showCropModal" x-cloak
         class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70"
         @keydown.escape.window="closeCropModal()">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl mx-4 overflow-hidden" @click.outside="closeCropModal()">
            {{-- Modal Header --}}
            <div class="flex items-center justify-between p-4 border-b dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Crop Image</h3>
                <button type="button" @click="closeCropModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Crop Mode Buttons --}}
            <div class="flex gap-2 px-4 pt-3">
                <button type="button" @click="setCropMode('portrait')"
                        :class="cropMode === 'portrait' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                        class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors">
                    Portrait (3:4)
                </button>
                <button type="button" @click="setCropMode('free')"
                        :class="cropMode === 'free' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                        class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors">
                    Free Crop
                </button>
            </div>

            {{-- Cropper Area --}}
            <div class="p-4">
                <div class="max-h-[60vh] overflow-hidden">
                    <img x-ref="cropImage_{{ $uniqueId }}" class="max-w-full" />
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="flex justify-end gap-3 p-4 border-t dark:border-gray-700">
                <button type="button" @click="closeCropModal()"
                        class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600">
                    Cancel
                </button>
                <button type="button" @click="cropAndProcess()"
                        class="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                    Crop & Process
                </button>
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
        errorMsg: '',
        showCropModal: false,
        cropMode: 'portrait',
        cropper: null,
        rawFile: null,
        isVerified: {{ $isVerified ? 'true' : 'false' }},
        showExisting: true,

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
            const file = event.dataTransfer.files[0];
            if (file) this.openCropper(file);
        },

        openCropper(file) {
            if (!file.type.startsWith('image/')) {
                this.errorMsg = 'Please select a valid image file (PNG or JPG).';
                return;
            }
            if (file.size > 6 * 1024 * 1024) {
                this.errorMsg = 'Image must be less than 6MB.';
                return;
            }
            this.errorMsg = '';
            this.rawFile = file;

            const reader = new FileReader();
            reader.onload = (e) => {
                this.showCropModal = true;
                this.$nextTick(() => {
                    const img = this.$refs.cropImage_{{ $uniqueId }};
                    img.src = e.target.result;
                    if (this.cropper) this.cropper.destroy();
                    this.cropper = new Cropper(img, {
                        aspectRatio: 3 / 4,
                        viewMode: 1,
                        autoCropArea: 0.9,
                        responsive: true,
                    });
                });
            };
            reader.readAsDataURL(file);
        },

        setCropMode(mode) {
            this.cropMode = mode;
            if (this.cropper) {
                this.cropper.setAspectRatio(mode === 'portrait' ? 3 / 4 : NaN);
            }
        },

        closeCropModal() {
            this.showCropModal = false;
            if (this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }
            // Reset file input
            const input = this.$refs.fileInput_{{ $uniqueId }};
            if (input) input.value = '';
        },

        async cropAndProcess() {
            if (!this.cropper) return;

            const canvas = this.cropper.getCroppedCanvas({
                minWidth: 400,
                minHeight: 533,
                maxWidth: 1600,
                maxHeight: 2133,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });

            this.closeCropModal();
            this.processing = true;
            this.showExisting = false;

            const dataUrl = canvas.toDataURL('image/png');

            try {
                const response = await fetch('{{ route("player-image.process") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ image: dataUrl }),
                });

                const data = await response.json();

                if (data.success) {
                    this.processedPath = data.path;
                    this.previewUrl = data.url;
                    if (data.bgProcessing) {
                        this.bgProcessing = true;
                        this.pollBgStatus();
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
                    const res = await fetch(`{{ route("player-image.status") }}?path=${encodeURIComponent(this.processedPath)}`, {
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
            // Safety: stop polling after 2 minutes
            setTimeout(() => { clearInterval(interval); this.bgProcessing = false; }, 120000);
        },

        resetUpload() {
            this.processedPath = '';
            this.previewUrl = '';
            this.bgProcessing = false;
            this.errorMsg = '';
            this.showExisting = false;
            const input = this.$refs.fileInput_{{ $uniqueId }};
            if (input) input.value = '';
        },
    };
}
</script>
@endif
