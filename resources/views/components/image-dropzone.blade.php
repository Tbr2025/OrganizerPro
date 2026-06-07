@props([
    'name',
    'existingImage' => null,
    'label' => 'Upload Image',
    'hint' => '',
    'accept' => 'image/*',
    'previewHeight' => 'h-40',
    'previewAspect' => 'contain', // 'contain' or 'cover'
    'removable' => false,
])

@php
    $inputId = 'dropzone_' . str_replace(['.', '[', ']'], '_', $name);
    $existingUrl = $existingImage ? Storage::url($existingImage) : '';
@endphp

<div
    x-data="{
        previewUrl: '{{ $existingUrl }}',
        fileName: '',
        fileSize: '',
        dragging: false,
        inputId: '{{ $inputId }}',
        handleFiles(files) {
            if (!files || !files.length) return;
            const file = files[0];
            if (!file.type.startsWith('image/')) return;
            this.fileName = file.name;
            this.fileSize = (file.size / 1024).toFixed(1) + ' KB';
            this.previewUrl = URL.createObjectURL(file);
            // Transfer file to the hidden input
            const dt = new DataTransfer();
            dt.items.add(file);
            this.$refs.fileInput.files = dt.files;
        },
        handleDrop(e) {
            this.dragging = false;
            this.handleFiles(e.dataTransfer.files);
        },
        removeImage() {
            this.previewUrl = '';
            this.fileName = '';
            this.fileSize = '';
            this.$refs.fileInput.value = '';
        }
    }"
    class="w-full"
>
    {{-- Dropzone Area --}}
    <div
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="handleDrop($event)"
        @click="$refs.fileInput.click()"
        :class="dragging
            ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
            : (previewUrl
                ? 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800'
                : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-700/50'
            )"
        class="relative border-2 border-dashed rounded-xl cursor-pointer transition-all duration-200 overflow-hidden group"
    >
        {{-- Preview State --}}
        <template x-if="previewUrl">
            <div class="relative">
                <div class="{{ $previewHeight }} w-full flex items-center justify-center bg-gray-50 dark:bg-gray-900/50 p-3">
                    <img
                        :src="previewUrl"
                        alt="Preview"
                        class="max-h-full max-w-full rounded-lg {{ $previewAspect === 'cover' ? 'object-cover' : 'object-contain' }}"
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
                    @if($removable)
                    <button
                        type="button"
                        @click.stop="removeImage()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-500/90 text-white rounded-lg text-sm font-medium shadow-sm hover:bg-red-600"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Remove
                    </button>
                    @endif
                </div>
                {{-- File info bar --}}
                <div x-show="fileName" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600 flex items-center justify-between">
                    <span class="text-xs text-gray-600 dark:text-gray-300 truncate" x-text="fileName"></span>
                    <span class="text-xs text-gray-400 dark:text-gray-500 flex-shrink-0 ml-2" x-text="fileSize"></span>
                </div>
            </div>
        </template>

        {{-- Empty State --}}
        <template x-if="!previewUrl">
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
    <input
        x-ref="fileInput"
        type="file"
        name="{{ $name }}"
        id="{{ $inputId }}"
        accept="{{ $accept }}"
        class="sr-only"
        @change="handleFiles($event.target.files)"
    >

    {{-- Hint Text --}}
    @if($hint)
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">{{ $hint }}</p>
    @endif
</div>
