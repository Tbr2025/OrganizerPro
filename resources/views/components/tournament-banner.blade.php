@props(['tournament' => null, 'page' => '', 'position' => ''])

@if($tournament)
    @php
        $banners = \App\Models\TournamentBanner::forSlot($tournament->id, $page, $position)->get();
    @endphp

    @if($banners->count())
        @php
            $displayType = $banners->first()->display_type;
            $uniqueId = 'banner-' . $page . '-' . $position . '-' . $tournament->id;
        @endphp

        @if($displayType === 'slider' && $banners->count() > 1)
            {{-- Slider Mode --}}
            <div x-data="{
                    current: 0,
                    total: {{ $banners->count() }},
                    autoplay: null,
                    init() {
                        this.autoplay = setInterval(() => {
                            this.current = (this.current + 1) % this.total;
                        }, 5000);
                    },
                    goTo(i) {
                        this.current = i;
                        clearInterval(this.autoplay);
                        this.autoplay = setInterval(() => {
                            this.current = (this.current + 1) % this.total;
                        }, 5000);
                    }
                 }"
                 class="tournament-banner-slot w-full overflow-hidden my-4" id="{{ $uniqueId }}">
                <div class="relative w-full">
                    @foreach($banners as $index => $banner)
                        <div x-show="current === {{ $index }}"
                             x-transition:enter="transition ease-out duration-500"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-300 absolute inset-0"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="w-full">
                            @if($banner->link_url)
                                <a href="{{ $banner->link_url }}" target="_blank" rel="noopener noreferrer" class="block">
                            @endif
                            <img src="{{ $banner->image_url }}"
                                 alt="{{ $banner->alt_text ?? 'Banner' }}"
                                 class="w-full object-contain mx-auto rounded-lg
                                    @if($banner->aspect_ratio === 'wide') max-h-24 @endif
                                    @if($banner->aspect_ratio === 'landscape') max-h-64 @endif
                                    @if($banner->aspect_ratio === 'portrait') max-h-96 max-w-xs @endif
                                    @if($banner->aspect_ratio === 'square') max-h-72 max-w-xs @endif
                                 ">
                            @if($banner->link_url)
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Dot Indicators --}}
                <div class="flex justify-center gap-2 mt-2">
                    @foreach($banners as $index => $banner)
                        <button @click="goTo({{ $index }})"
                                :class="current === {{ $index }} ? 'bg-white scale-110' : 'bg-white/40 hover:bg-white/70'"
                                class="w-2 h-2 rounded-full transition-all duration-300"></button>
                    @endforeach
                </div>
            </div>
        @else
            {{-- Static Mode (single image) --}}
            @php $banner = $banners->first(); @endphp
            <div class="tournament-banner-slot w-full my-4" id="{{ $uniqueId }}">
                @if($banner->link_url)
                    <a href="{{ $banner->link_url }}" target="_blank" rel="noopener noreferrer" class="block">
                @endif
                <img src="{{ $banner->image_url }}"
                     alt="{{ $banner->alt_text ?? 'Banner' }}"
                     class="w-full object-contain mx-auto rounded-lg
                        @if($banner->aspect_ratio === 'wide') max-h-24 @endif
                        @if($banner->aspect_ratio === 'landscape') max-h-64 @endif
                        @if($banner->aspect_ratio === 'portrait') max-h-96 max-w-xs @endif
                        @if($banner->aspect_ratio === 'square') max-h-72 max-w-xs @endif
                     ">
                @if($banner->link_url)
                    </a>
                @endif
            </div>
        @endif
    @endif
@endif
