@php
    $visible = $fieldConfig['image']['visible'] ?? true;
    $isRequired = $fieldConfig['image']['required'] ?? false;
    $piuId = 'pub_piu_' . Str::random(6);
@endphp

@if($visible)
<div class="border-b border-gray-700 pb-4 mb-4" x-data="publicPlayerImageUpload_{{ $piuId }}()" x-init="init()">
    <h3 class="text-lg font-semibold text-yellow-500 mb-4">
        Player Photo @if($isRequired)<span class="text-red-500">*</span>@endif
    </h3>

    {{-- Photo Guidelines --}}
    <div class="mb-3 flex items-start gap-3 p-3 bg-gray-700/50 border border-gray-600 rounded-lg">
        <div class="flex-shrink-0 w-14 h-18 bg-gray-600 rounded flex items-center justify-center">
            <svg class="w-7 h-9 text-gray-400" fill="currentColor" viewBox="0 0 24 32">
                <ellipse cx="12" cy="8" rx="5" ry="6"/>
                <path d="M2 28c0-6 4-10 10-10s10 4 10 10"/>
            </svg>
        </div>
        <div class="text-xs text-gray-400">
            <p class="font-semibold text-gray-300 mb-1">Photo Guidelines</p>
            <ul class="space-y-0.5 list-disc list-inside">
                <li>Clear, front-facing photo</li>
                <li>Plain background preferred (auto-removed)</li>
                <li>Minimum 400×533px</li>
                <li>PNG or JPG, max 6MB</li>
            </ul>
        </div>
    </div>

    {{-- Hidden input for processed path --}}
    <input type="hidden" name="processed_image_path" x-model="processedPath" />

    {{-- Upload Area --}}
    <div x-show="!processedPath && !processing"
         class="border-2 border-dashed border-gray-600 rounded-lg p-6 text-center hover:border-yellow-500 transition cursor-pointer"
         @click="$refs.pubFileInput_{{ $piuId }}.click()"
         @drop.prevent="handleDrop($event)" @dragover.prevent>
        <input type="file" accept="image/png,image/jpeg" class="hidden"
               x-ref="pubFileInput_{{ $piuId }}" @change="handleFileSelect($event)">

        <div class="text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <p class="text-sm">Click or drag & drop to upload your photo</p>
            <p class="text-xs mt-1">PNG or JPG (max 6MB)</p>
        </div>
    </div>

    {{-- Processing Spinner --}}
    <div x-show="processing" x-cloak class="border-2 border-dashed border-yellow-600 bg-yellow-900/20 p-8 rounded-lg text-center">
        <svg class="animate-spin h-10 w-10 mx-auto mb-3 text-yellow-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <p class="text-sm text-yellow-400 font-medium">Processing image...</p>
        <p class="text-xs text-gray-500 mt-1">Removing background & optimizing</p>
    </div>

    {{-- Processed Preview --}}
    <div x-show="processedPath" x-cloak class="border border-gray-600 rounded-lg p-4 text-center bg-gray-800">
        <img :src="previewUrl" class="mx-auto mb-3 h-48 object-contain rounded-lg" x-show="previewUrl" />
        <button type="button" @click="resetUpload()"
                class="text-sm text-yellow-500 hover:text-yellow-400 hover:underline">
            Change Photo
        </button>
    </div>

    {{-- Error --}}
    <template x-if="errorMsg">
        <p class="text-sm text-red-500 mt-1" x-text="errorMsg"></p>
    </template>
    @error('image')
        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
    @enderror
    @error('processed_image_path')
        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
    @enderror

    {{-- Crop Modal --}}
    <div x-show="showCropModal" x-cloak
         class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80"
         @keydown.escape.window="closeCropModal()">
        <div class="bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl mx-4 overflow-hidden border border-gray-700" @click.outside="closeCropModal()">
            {{-- Header --}}
            <div class="flex items-center justify-between p-4 border-b border-gray-700">
                <h3 class="text-lg font-semibold text-white">Crop Image</h3>
                <button type="button" @click="closeCropModal()" class="text-gray-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Crop Mode Buttons --}}
            <div class="flex gap-2 px-4 pt-3">
                <button type="button" @click="setCropMode('portrait')"
                        :class="cropMode === 'portrait' ? 'bg-yellow-500 text-gray-900' : 'bg-gray-700 text-gray-300'"
                        class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors">
                    Portrait (3:4)
                </button>
                <button type="button" @click="setCropMode('free')"
                        :class="cropMode === 'free' ? 'bg-yellow-500 text-gray-900' : 'bg-gray-700 text-gray-300'"
                        class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors">
                    Free Crop
                </button>
            </div>

            {{-- Cropper Area --}}
            <div class="p-4">
                <div class="max-h-[60vh] overflow-hidden">
                    <img x-ref="pubCropImage_{{ $piuId }}" class="max-w-full" />
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex justify-end gap-3 p-4 border-t border-gray-700">
                <button type="button" @click="closeCropModal()"
                        class="px-4 py-2 text-sm text-gray-300 bg-gray-700 rounded-lg hover:bg-gray-600">
                    Cancel
                </button>
                <button type="button" @click="cropAndProcess()"
                        class="px-4 py-2 text-sm text-gray-900 bg-yellow-500 rounded-lg hover:bg-yellow-400 font-medium">
                    Crop & Process
                </button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css" />
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script>
function publicPlayerImageUpload_{{ $piuId }}() {
    return {
        processedPath: '',
        previewUrl: '',
        processing: false,
        errorMsg: '',
        showCropModal: false,
        cropMode: 'portrait',
        cropper: null,

        init() {},

        handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) this.openCropper(file);
        },

        handleDrop(event) {
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

            const reader = new FileReader();
            reader.onload = (e) => {
                this.showCropModal = true;
                this.$nextTick(() => {
                    const img = this.$refs.pubCropImage_{{ $piuId }};
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
            const input = this.$refs.pubFileInput_{{ $piuId }};
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

            const dataUrl = canvas.toDataURL('image/png');

            // Get CSRF token from the form
            const csrfToken = document.querySelector('input[name="_token"]')?.value
                || document.querySelector('meta[name="csrf-token"]')?.content;

            try {
                const response = await fetch('{{ route("public.player-image.process") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ image: dataUrl }),
                });

                const data = await response.json();

                if (data.success) {
                    this.processedPath = data.path;
                    this.previewUrl = data.url;
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

        resetUpload() {
            this.processedPath = '';
            this.previewUrl = '';
            this.errorMsg = '';
            const input = this.$refs.pubFileInput_{{ $piuId }};
            if (input) input.value = '';
        },
    };
}
</script>
@endpush
@endif
