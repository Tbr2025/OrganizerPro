@php
    $visible = $fieldConfig['image']['visible'] ?? true;
    $isRequired = $fieldConfig['image']['required'] ?? false;
    $piuId = 'pub_piu_' . Str::random(6);
    $embedded = $embedded ?? false; // when true, the parent section card provides the wrapper/header
@endphp

@if($visible)
<div class="{{ $embedded ? '' : 'reg-section glass reveal' }}" x-data="publicPlayerImageUpload_{{ $piuId }}()" x-init="init()">
    @unless($embedded)
    <div class="reg-section-head">
        <div class="reg-section-icon"><i class="fas fa-camera"></i></div>
        <div>
            <div class="reg-section-title">{{ $sectionTitle ?? ($fieldLabel ?? 'Player Photo') }} @if($isRequired)<span class="reg-req">*</span>@endif</div>
            <div class="reg-section-sub">A clear, front-facing headshot</div>
        </div>
    </div>
    @endunless

    {{-- Photo Guidelines (admin-editable text + sample image; falls back to defaults) --}}
    @php
        $tcSettings = $settings ?? null;
        $guidelineText = trim((string) ($tcSettings->photo_guidelines ?? ''));
        $guidelineLines = $guidelineText !== ''
            ? preg_split('/\r\n|\r|\n/', $guidelineText)
            : [
                'Clear, front-facing headshot (face centered)',
                'Plain background preferred (auto-removed)',
                'Good lighting, no filters or sunglasses',
                'Minimum 400×533px, portrait (3:4)',
                'PNG or JPG, max 6MB',
            ];
        $sampleUrl = $tcSettings?->photo_sample_url;
    @endphp
    <div class="mb-3 flex items-start gap-3 p-3 rounded-lg" style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.18);">
        <div class="flex-shrink-0 w-16 h-20 rounded overflow-hidden flex items-center justify-center" style="background:rgba(255,255,255,0.12);">
            @if($sampleUrl)
                <img src="{{ $sampleUrl }}" alt="Sample photo" class="w-full h-full object-cover">
            @else
                <svg class="w-8 h-10 text-white/80" fill="currentColor" viewBox="0 0 24 32">
                    <ellipse cx="12" cy="8" rx="5" ry="6"/>
                    <path d="M2 28c0-6 4-10 10-10s10 4 10 10"/>
                </svg>
            @endif
        </div>
        <div class="text-xs text-white/90">
            <p class="font-semibold text-white mb-1">Photo Guidelines</p>
            <ul class="space-y-0.5 list-disc list-inside text-white/80">
                @foreach($guidelineLines as $line)
                    @if(trim($line) !== '')<li>{{ trim($line) }}</li>@endif
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Hidden input for processed path --}}
    <input type="hidden" name="processed_image_path" x-model="processedPath"
           @if($isRequired) x-ref="processedImageInput" @endif />
    @if($isRequired)
    <input type="hidden" x-ref="imageRequiredCheck"
           :value="processedPath ? '1' : ''"
           required
           style="display:none;">
    @endif

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
        <p class="text-xs text-gray-500 mt-1">Cropping & optimizing</p>
    </div>

    {{-- Processed Preview --}}
    <div x-show="processedPath" x-cloak class="border border-gray-600 rounded-lg p-4 text-center bg-gray-800">
        <img :src="previewUrl" class="mx-auto mb-3 h-48 object-contain rounded-lg" x-show="previewUrl" />
        <div x-show="bgProcessing" x-cloak class="mt-2 inline-flex items-center gap-1.5 text-xs text-yellow-400">
            <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Removing background...
        </div>
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

    {{-- Crop Modal (teleported to body so it escapes the section's transform/stacking context) --}}
    <template x-teleport="body">
    <div x-show="showCropModal" x-cloak
         class="fixed inset-0 flex items-center justify-center"
         style="z-index: 99999; background: rgba(0,0,0,0.85);"
         @keydown.escape.window="closeCropModal()">
        <div class="bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl mx-4 overflow-hidden border border-gray-700" @click.outside="closeCropModal()">
            {{-- Header --}}
            <div class="flex items-center justify-between p-4 border-b border-gray-700">
                <h3 class="text-lg font-semibold text-white">Crop Image <span class="text-xs font-normal text-gray-400">(Portrait 3:4)</span></h3>
                <button type="button" @click="closeCropModal()" class="text-gray-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Cropper Area --}}
            <div class="p-4">
                <div style="max-height: 60vh; overflow: hidden;">
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
    </template>
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
        bgProcessing: false,
        errorMsg: '',
        showCropModal: false,
        cropMode: 'portrait',
        cropper: null,

        init() {
            // Restore an already-uploaded photo after a validation error so the
            // user doesn't have to re-upload (it's already stored on the server).
            this.processedPath = @js(old('processed_image_path', ''));
            @if(old('processed_image_path'))
                this.previewUrl = @js(\Illuminate\Support\Facades\Storage::disk('public')->url(old('processed_image_path')));
            @endif
        },

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
                        autoCropArea: 1,
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
            const csrfToken = document.querySelector('input[name="_token"]')?.value
                || document.querySelector('meta[name="csrf-token"]')?.content;
            const interval = setInterval(async () => {
                try {
                    const res = await fetch(`{{ route("public.player-image.status") }}?path=${encodeURIComponent(this.processedPath)}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
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
            const input = this.$refs.pubFileInput_{{ $piuId }};
            if (input) input.value = '';
        },
    };
}
</script>
@endpush
@endif
