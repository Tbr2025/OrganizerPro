@props([
    'name' => 'logo',
    'existingImage' => null,
    'circular' => true,
    'ratios' => null,
    'outputSize' => 400,
])

@php
    $uniqueId = 'lc_' . Str::random(6);
    $defaultRatios = $ratios ?? ($circular ? [['label' => '1:1', 'value' => 1]] : [['label' => '1:1', 'value' => 1]]);
    $previewClass = $circular ? 'rounded-full' : 'rounded-lg';
    $existingUrl = $existingImage ? asset('storage/' . $existingImage) : '';
@endphp

<div x-data="logoCropper_{{ $uniqueId }}()" x-init="init()" class="w-full">
    {{-- Dropzone Area --}}
    <div
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="handleDrop($event)"
        @click="$refs.fileInput.click()"
        :class="dragging
            ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
            : ((croppedPreview || existingUrl)
                ? 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800'
                : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-700/50'
            )"
        class="relative border-2 border-dashed rounded-xl cursor-pointer transition-all duration-200 overflow-hidden group"
    >
        {{-- Preview State --}}
        <template x-if="croppedPreview || existingUrl">
            <div class="relative">
                <div class="h-40 w-full flex items-center justify-center bg-gray-50 dark:bg-gray-900/50 p-4">
                    <img
                        :src="croppedPreview || existingUrl"
                        alt="Logo preview"
                        class="max-h-full max-w-full object-contain {{ $previewClass }}"
                    >
                </div>
                {{-- Overlay on hover --}}
                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-3">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white/90 text-gray-800 rounded-lg text-sm font-medium shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Change
                    </span>
                    <template x-if="croppedPreview">
                        <button
                            type="button"
                            @click.stop="removeCrop()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-500/90 text-white rounded-lg text-sm font-medium shadow-sm hover:bg-red-600"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Remove
                        </button>
                    </template>
                </div>
                {{-- File name bar --}}
                <div x-show="fileName" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600 flex items-center justify-between">
                    <span class="text-xs text-gray-600 dark:text-gray-300 truncate" x-text="fileName"></span>
                    <span class="text-xs text-gray-400 dark:text-gray-500 flex-shrink-0 ml-2" x-text="fileSize"></span>
                </div>
            </div>
        </template>

        {{-- Empty State --}}
        <template x-if="!croppedPreview && !existingUrl">
            <div class="flex flex-col items-center justify-center py-8 px-4">
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
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">PNG, JPG, GIF, WebP</p>
            </div>
        </template>
    </div>

    {{-- Hidden File Input --}}
    <input type="file" accept="image/*" @change="onFileSelected($event)" class="sr-only" x-ref="fileInput">

    {{-- Hidden input for cropped data --}}
    <input type="hidden" name="{{ $name }}_cropped" x-ref="croppedData">

    {{-- Crop Modal --}}
    <div x-show="showModal" x-cloak
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white dark:bg-gray-800 rounded-t-xl sm:rounded-xl shadow-2xl w-full max-w-md sm:mx-4 p-5" @click.outside="cancelCrop()">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-3">Crop Image</h3>

            @if(count($defaultRatios) > 1)
            {{-- Aspect Ratio Buttons --}}
            <div class="flex flex-wrap gap-2 mb-3">
                @foreach($defaultRatios as $i => $ratio)
                @php $ratioKey = $ratio['value'] === 'free' ? 'free' : (string)$ratio['value']; @endphp
                <button type="button"
                    @click="setAspectRatio('{{ $ratioKey }}')"
                    :class="currentRatioKey === '{{ $ratioKey }}' ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors">
                    {{ $ratio['label'] }}
                </button>
                @endforeach
            </div>
            @endif

            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Drag to position and resize the crop area.</p>
            <div class="relative bg-gray-100 dark:bg-gray-900 rounded-lg overflow-hidden" style="max-height: min(350px, 50vh);">
                <img x-ref="cropImage" class="max-w-full" style="display:block;">
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" @click="cancelCrop()" class="btn btn-sm btn-secondary">Cancel</button>
                <button type="button" @click="applyCrop()" class="btn btn-sm btn-primary">Apply</button>
            </div>
        </div>
    </div>
</div>

@once
@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css" />
@endpush
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
@endpush
@endonce

<script>
function logoCropper_{{ $uniqueId }}() {
    const ratioMap = {
        @foreach($defaultRatios as $ratio)
        '{{ $ratio['value'] === 'free' ? 'free' : (string)$ratio['value'] }}': {{ $ratio['value'] === 'free' ? 'NaN' : $ratio['value'] }},
        @endforeach
    };

    const firstKey = '{{ $defaultRatios[0]['value'] === 'free' ? 'free' : (string)$defaultRatios[0]['value'] }}';

    return {
        cropper: null,
        showModal: false,
        croppedPreview: null,
        existingUrl: '{{ $existingUrl }}',
        currentRatioKey: firstKey,
        isCircular: {{ $circular ? 'true' : 'false' }},
        outputSize: {{ $outputSize }},
        dragging: false,
        fileName: '',
        fileSize: '',

        init() {},

        handleDrop(e) {
            this.dragging = false;
            const files = e.dataTransfer.files;
            if (!files || !files.length) return;
            const file = files[0];
            if (!file.type.startsWith('image/')) return;
            // Transfer to hidden input and trigger crop
            const dt = new DataTransfer();
            dt.items.add(file);
            this.$refs.fileInput.files = dt.files;
            this.processFile(file);
        },

        getNumericRatio() {
            const val = ratioMap[this.currentRatioKey];
            return isNaN(val) ? NaN : val;
        },

        setAspectRatio(key) {
            this.currentRatioKey = key;
            if (this.cropper) {
                const numeric = this.getNumericRatio();
                this.cropper.setAspectRatio(isNaN(numeric) ? NaN : numeric);
            }
        },

        onFileSelected(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.processFile(file);
        },

        processFile(file) {
            this.fileName = file.name;
            this.fileSize = (file.size / 1024).toFixed(1) + ' KB';

            const reader = new FileReader();
            reader.onload = (e) => {
                this.$refs.cropImage.src = e.target.result;
                this.showModal = true;

                this.$nextTick(() => {
                    if (this.cropper) this.cropper.destroy();
                    const numeric = this.getNumericRatio();
                    this.cropper = new Cropper(this.$refs.cropImage, {
                        aspectRatio: isNaN(numeric) ? NaN : numeric,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.9,
                        responsive: true,
                        restore: false,
                        guides: false,
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

        applyCrop() {
            if (!this.cropper) return;

            const cropData = this.cropper.getData();
            const outputOpts = { imageSmoothingQuality: 'high' };
            const numeric = this.getNumericRatio();

            if (!isNaN(numeric) && numeric === 1) {
                outputOpts.width = this.outputSize;
                outputOpts.height = this.outputSize;
            } else if (!isNaN(numeric)) {
                outputOpts.width = this.outputSize;
                outputOpts.height = Math.round(this.outputSize / numeric);
            } else {
                // Free crop — cap the largest side
                const ratio = cropData.width / cropData.height;
                if (ratio >= 1) {
                    outputOpts.width = this.outputSize;
                    outputOpts.height = Math.round(this.outputSize / ratio);
                } else {
                    outputOpts.height = this.outputSize;
                    outputOpts.width = Math.round(this.outputSize * ratio);
                }
            }

            const canvas = this.cropper.getCroppedCanvas(outputOpts);

            this.croppedPreview = canvas.toDataURL('image/png');
            this.$refs.croppedData.value = this.croppedPreview;
            this.showModal = false;
            this.cropper.destroy();
            this.cropper = null;
        },

        cancelCrop() {
            this.showModal = false;
            if (this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }
            this.$refs.fileInput.value = '';
            this.fileName = '';
            this.fileSize = '';
        },

        removeCrop() {
            this.croppedPreview = null;
            this.$refs.croppedData.value = '';
            this.$refs.fileInput.value = '';
            this.fileName = '';
            this.fileSize = '';
        }
    };
}
</script>
