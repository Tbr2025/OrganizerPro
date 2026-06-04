@props([
    'name' => 'logo',
    'existingImage' => null,
])

@php
    $uniqueId = 'lc_' . Str::random(6);
@endphp

<div x-data="logoCropper_{{ $uniqueId }}()" x-init="init()">
    {{-- Preview --}}
    <div class="mb-3">
        <template x-if="croppedPreview">
            <div class="flex items-center gap-3">
                <img :src="croppedPreview" class="w-20 h-20 rounded-full object-cover border-2 border-gray-200 dark:border-gray-600" alt="Logo preview">
                <button type="button" @click="removeCrop()" class="text-xs text-red-500 hover:underline">Remove</button>
            </div>
        </template>
        @if($existingImage)
            <div x-show="!croppedPreview" class="flex items-center gap-3">
                <img src="{{ asset('storage/' . $existingImage) }}" class="w-20 h-20 rounded-full object-cover border-2 border-gray-200 dark:border-gray-600" alt="Current logo">
                <span class="text-xs text-gray-500">Current logo</span>
            </div>
        @endif
    </div>

    {{-- File Input --}}
    <input type="file" accept="image/*" @change="onFileSelected($event)" class="form-control text-sm"
        x-ref="fileInput">

    {{-- Hidden input for cropped data --}}
    <input type="hidden" name="{{ $name }}_cropped" x-ref="croppedData">

    {{-- Crop Modal --}}
    <div x-show="showModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md mx-4 p-5" @click.outside="cancelCrop()">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-3">Crop Logo</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Drag to position. The logo will be cropped to a circle.</p>
            <div class="relative bg-gray-100 dark:bg-gray-900 rounded-lg overflow-hidden" style="max-height: 350px;">
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
    return {
        cropper: null,
        showModal: false,
        croppedPreview: null,

        init() {},

        onFileSelected(event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (e) => {
                this.$refs.cropImage.src = e.target.result;
                this.showModal = true;

                this.$nextTick(() => {
                    if (this.cropper) this.cropper.destroy();
                    this.cropper = new Cropper(this.$refs.cropImage, {
                        aspectRatio: 1,
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

            const canvas = this.cropper.getCroppedCanvas({
                width: 400,
                height: 400,
                imageSmoothingQuality: 'high',
            });

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
        },

        removeCrop() {
            this.croppedPreview = null;
            this.$refs.croppedData.value = '';
            this.$refs.fileInput.value = '';
        }
    };
}
</script>
