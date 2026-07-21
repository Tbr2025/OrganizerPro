@extends('backend.layouts.app')

@section('title', 'Banners / Ads - ' . $tournament->name)

@section('admin-content')
<x-breadcrumbs :breadcrumbs="$breadcrumbs" />

<div class="p-4 mx-auto max-w-6xl md:p-6">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Banners / Ads</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage banner images shown on public pages for {{ $tournament->name }}</p>
        </div>
        <a href="{{ route('tournaments.show', $tournament) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back
        </a>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Page Sections --}}
    @foreach($organized as $pageKey => $pageData)
        <div x-data="{ open: true }" class="mb-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            {{-- Page Header --}}
            <button @click="open = !open" class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center">
                        <svg class="w-4 h-4 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $pageData['label'] }}</h2>
                </div>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="open" x-collapse>
                @foreach($pageData['positions'] as $posKey => $posData)
                    <div class="px-6 pb-6 {{ !$loop->first ? 'pt-4 border-t border-gray-100 dark:border-gray-700' : '' }}"
                         x-data="{
                            displayType: '{{ $posData['display_type'] }}',
                            showAddForm: false
                         }">

                        {{-- Position Header --}}
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">{{ $posData['label'] }} Position</h3>
                            <div class="flex items-center gap-3">
                                {{-- Display Type Toggle --}}
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="text-gray-500 dark:text-gray-400">Display:</span>
                                    @foreach($displayTypes as $dtKey => $dtLabel)
                                        <button @click="displayType = '{{ $dtKey }}'"
                                                :class="displayType === '{{ $dtKey }}' ? 'bg-teal-100 dark:bg-teal-900/40 text-teal-700 dark:text-teal-300 border-teal-300 dark:border-teal-700' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-600'"
                                                class="px-2.5 py-1 rounded-md border text-xs font-medium transition">
                                            {{ $dtKey === 'static' ? 'Static' : 'Slider' }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- Warning for static mode with multiple active banners --}}
                        @if($posData['banners']->where('is_active', true)->count() > 1)
                            <template x-if="displayType === 'static'">
                                <div class="mb-3 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300 text-xs flex items-center gap-2">
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                    Multiple active banners in static mode — only the first one will be shown. Switch to Slider or deactivate extras.
                                </div>
                            </template>
                        @endif

                        {{-- Existing Banners Grid --}}
                        @if($posData['banners']->count())
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4"
                                 x-data="bannerReorder('{{ $pageKey }}', '{{ $posKey }}')"
                                 @dragover.prevent @drop="onDrop($event)">
                                @foreach($posData['banners'] as $banner)
                                    <div class="relative bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden group"
                                         draggable="true"
                                         data-banner-id="{{ $banner->id }}"
                                         @dragstart="onDragStart($event, {{ $banner->id }})"
                                         @dragend="onDragEnd($event)">
                                        {{-- Image --}}
                                        <div class="relative aspect-video bg-gray-100 dark:bg-gray-800">
                                            <img src="{{ $banner->image_url }}" alt="{{ $banner->alt_text ?? 'Banner' }}"
                                                 class="w-full h-full object-contain">
                                            {{-- Drag Handle --}}
                                            <div class="absolute top-2 left-2 opacity-0 group-hover:opacity-100 transition cursor-grab">
                                                <div class="w-7 h-7 rounded bg-black/50 backdrop-blur flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                                                </div>
                                            </div>
                                            {{-- Active Badge --}}
                                            <div class="absolute top-2 right-2">
                                                <button onclick="toggleBanner({{ $banner->id }}, this)"
                                                        class="w-7 h-7 rounded-full flex items-center justify-center transition {{ $banner->is_active ? 'bg-green-500 text-white' : 'bg-gray-400 text-white' }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $banner->is_active ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12' }}"/></svg>
                                                </button>
                                            </div>
                                        </div>

                                        {{-- Info & Actions --}}
                                        <div class="p-3">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $banner->is_active ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300' : 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }}">
                                                    {{ $banner->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                                <span class="text-xs text-gray-400">{{ \App\Models\TournamentBanner::ASPECT_RATIOS[$banner->aspect_ratio] ?? $banner->aspect_ratio }}</span>
                                            </div>
                                            @if($banner->link_url)
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate mb-2" title="{{ $banner->link_url }}">
                                                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                                    {{ $banner->link_url }}
                                                </p>
                                            @endif

                                            {{-- Edit / Delete --}}
                                            <div x-data="{ editing: false }" class="mt-2">
                                                <div class="flex items-center gap-2">
                                                    <button @click="editing = !editing" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Edit</button>
                                                    <form method="POST" action="{{ route('admin.tournaments.banners.destroy', [$tournament, $banner]) }}" onsubmit="return confirm('Delete this banner?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="text-xs text-red-600 dark:text-red-400 hover:underline">Delete</button>
                                                    </form>
                                                </div>

                                                {{-- Edit Form --}}
                                                <div x-show="editing" x-collapse class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                                    <form method="POST" action="{{ route('admin.tournaments.banners.update', [$tournament, $banner]) }}" enctype="multipart/form-data" class="space-y-3">
                                                        @csrf @method('PUT')
                                                        <input type="hidden" name="display_type" :value="displayType">
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Replace Image</label>
                                                            <input type="file" name="image" accept="image/*" class="w-full text-xs file:mr-2 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-teal-50 dark:file:bg-teal-900/30 file:text-teal-700 dark:file:text-teal-300">
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Aspect Ratio</label>
                                                            <select name="aspect_ratio" class="w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                                                @foreach($aspectRatios as $rKey => $rLabel)
                                                                    <option value="{{ $rKey }}" {{ $banner->aspect_ratio === $rKey ? 'selected' : '' }}>{{ $rLabel }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Link URL (optional)</label>
                                                            <input type="url" name="link_url" value="{{ $banner->link_url }}" placeholder="https://..." class="w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 px-3 py-1.5">
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Alt Text (optional)</label>
                                                            <input type="text" name="alt_text" value="{{ $banner->alt_text }}" placeholder="Banner description" class="w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 px-3 py-1.5">
                                                        </div>
                                                        <button type="submit" class="w-full text-xs font-medium px-3 py-1.5 rounded-md bg-teal-600 hover:bg-teal-700 text-white transition">Save Changes</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="mb-4 p-4 rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-700 text-center text-sm text-gray-400 dark:text-gray-500">
                                No banners for this slot yet. Add one below.
                            </div>
                        @endif

                        {{-- Add Banner Form --}}
                        <div>
                            <button @click="showAddForm = !showAddForm"
                                    class="inline-flex items-center gap-2 text-sm font-medium text-teal-600 dark:text-teal-400 hover:text-teal-700 dark:hover:text-teal-300 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <span x-text="showAddForm ? 'Cancel' : 'Add Banner'"></span>
                            </button>

                            <div x-show="showAddForm" x-collapse class="mt-3">
                                <form method="POST" action="{{ route('admin.tournaments.banners.store', $tournament) }}" enctype="multipart/form-data"
                                      class="bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                                    @csrf
                                    <input type="hidden" name="page" value="{{ $pageKey }}">
                                    <input type="hidden" name="position" value="{{ $posKey }}">
                                    <input type="hidden" name="display_type" :value="displayType">

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Banner Image <span class="text-red-500">*</span></label>
                                            <input type="file" name="image" accept="image/*" required class="w-full text-sm file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-teal-50 dark:file:bg-teal-900/30 file:text-teal-700 dark:file:text-teal-300 file:cursor-pointer">
                                            <p class="text-xs text-gray-400 mt-1">PNG, JPG, GIF, WebP. Max 5MB.</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Aspect Ratio</label>
                                            <select name="aspect_ratio" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                                @foreach($aspectRatios as $rKey => $rLabel)
                                                    <option value="{{ $rKey }}">{{ $rLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Link URL (optional)</label>
                                            <input type="url" name="link_url" placeholder="https://..." class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 px-3 py-2">
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Alt Text (optional)</label>
                                            <input type="text" name="alt_text" placeholder="Brief description of the banner" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 px-3 py-2">
                                        </div>
                                    </div>

                                    <div class="mt-4 flex justify-end">
                                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-teal-600 hover:bg-teal-700 text-white transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                            Upload Banner
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

@push('scripts')
<script>
    // Toggle banner active/inactive
    function toggleBanner(bannerId, btn) {
        fetch(`{{ url('/') }}/admin/tournaments/{{ $tournament->id }}/banners/${bannerId}/toggle`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    // Drag-and-drop reorder
    function bannerReorder(page, position) {
        return {
            dragId: null,
            onDragStart(e, id) {
                this.dragId = id;
                e.dataTransfer.effectAllowed = 'move';
                e.target.classList.add('opacity-50');
            },
            onDragEnd(e) {
                e.target.classList.remove('opacity-50');
            },
            onDrop(e) {
                const container = e.currentTarget;
                const cards = [...container.querySelectorAll('[data-banner-id]')];
                const order = cards.map(c => parseInt(c.dataset.bannerId));

                fetch(`{{ route('admin.tournaments.banners.reorder', $tournament) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ order }),
                });
            }
        };
    }
</script>
@endpush
@endsection
