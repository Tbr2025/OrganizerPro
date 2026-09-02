@props([
    'saveUrl',                 // POST endpoint that persists the processed path
    'existingImage' => null,   // current stored path, relative to the public disk
    'mode' => 'player',        // 'player' | 'captain' — controls crop ratio + guidelines
    'label' => 'Replace Photo',
    'hint' => null,
])

@php
    // Poster slots are laid out on a 1080x1350 canvas and a full-bleed player
    // fills most of that height, so anything much under ~1000px tall has to be
    // upscaled at render time — which is exactly what makes a generated poster
    // look soft. Surfacing the stored size here is the only place an organiser
    // can see *why* a poster came out blurry.
    $recommendedHeight = \App\Services\PlayerImageService::MAX_HEIGHT;
    $currentDimensions = null;

    if ($existingImage && Storage::disk('public')->exists($existingImage)) {
        $info = @getimagesize(Storage::disk('public')->path($existingImage));
        if ($info) {
            $currentDimensions = ['width' => $info[0], 'height' => $info[1]];
        }
    }

    $isLowRes = $currentDimensions && $currentDimensions['height'] < 1000;
@endphp

<div x-data="{
        saving: false,
        saved: false,
        error: '',
        async save() {
            const input = $el.querySelector('input[name=\'processed_photo_path\']');
            const path = input?.value;

            if (!path) {
                this.error = 'Upload and crop a photo first.';
                return;
            }

            this.saving = true;
            this.error = '';

            try {
                const res = await fetch('{{ $saveUrl }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    body: JSON.stringify({ path }),
                });
                const data = await res.json();

                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Could not save the photo.');
                }

                this.saved = true;
                // Reload so every thumbnail on the page picks up the new file
                // rather than the browser's cached copy of the old one.
                setTimeout(() => window.location.reload(), 700);
            } catch (e) {
                this.error = e.message || 'Could not save the photo.';
            } finally {
                this.saving = false;
            }
        }
     }"
     class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/50 p-4">

    <div class="flex items-start justify-between gap-3 mb-3">
        <div>
            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                <i class="fas fa-camera-retro text-gray-400"></i>
                {{ $label }}
            </h4>
            @if($hint)
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $hint }}</p>
            @endif
        </div>
        @if($currentDimensions)
            <span class="shrink-0 text-[11px] font-mono px-2 py-1 rounded-md
                         {{ $isLowRes
                            ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                            : 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300' }}">
                {{ $currentDimensions['width'] }}&times;{{ $currentDimensions['height'] }}
            </span>
        @endif
    </div>

    @if($isLowRes)
        <div class="mb-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-2.5">
            <p class="text-[11px] leading-relaxed text-amber-800 dark:text-amber-300">
                <strong>Low resolution for posters.</strong>
                This photo is {{ $currentDimensions['height'] }}px tall, so poster generation has to
                enlarge it — that is what makes players look soft. Upload a photo at least
                {{ $recommendedHeight }}px tall for a sharp result.
            </p>
        </div>
    @endif

    <x-player-image-upload
        name="processed_photo_path"
        :existing-image="$existingImage"
        :mode="$mode"
    />

    <div class="flex items-center gap-3 mt-3">
        <button type="button" @click="save()" :disabled="saving"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white
                       bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
            <i class="fas" :class="saving ? 'fa-spinner fa-spin' : (saved ? 'fa-check' : 'fa-save')"></i>
            <span x-text="saving ? 'Saving…' : (saved ? 'Saved' : 'Save Photo')"></span>
        </button>
        <p x-show="error" x-cloak x-text="error" class="text-xs text-red-600 dark:text-red-400"></p>
    </div>
</div>
