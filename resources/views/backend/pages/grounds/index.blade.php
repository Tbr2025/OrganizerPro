@extends('backend.layouts.app')

@section('title', 'Grounds | ' . config('app.name'))

@php
    $isSuperadmin = auth()->user()?->hasRole('Superadmin');
    // Superadmins have no organization of their own, so a new ground has nothing
    // to inherit — they must pick, unless there is only one to pick from.
    $needsOrgChoice = $isSuperadmin && $organizations->count() > 1;
    $total = $grounds->total();

    $groundsJson = $grounds->mapWithKeys(fn ($g) => [$g->id => [
        'id' => $g->id,
        'name' => $g->name,
        'city' => $g->city,
        'address' => $g->address,
        'google_maps_link' => $g->google_maps_link,
        'is_active' => (bool) $g->is_active,
        'organization_id' => $g->organization_id,
        'map_embed_url' => $g->map_embed_url,
        'map_external_url' => $g->map_external_url,
        'map_label' => trim(collect([$g->address, $g->city])->filter()->implode(', ')) ?: $g->name,
        'image_url' => $g->image && Storage::disk('public')->exists($g->image)
            ? Storage::url($g->image)
            : null,
    ]])->toJson(JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
@endphp

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[['name' => 'Dashboard', 'route' => route('admin.dashboard')], ['name' => 'Grounds']]" />

<div class="p-4 mx-auto max-w-7xl md:p-6"
     x-data="groundsPage({
        needsOrgChoice: {{ $needsOrgChoice ? 'true' : 'false' }},
        defaultOrgId: '{{ $organizations->count() === 1 ? $organizations->first()->id : '' }}',
     })"
     x-init="init()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Grounds &amp; Venues</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Where matches are played. Venues appear in the fixture and poster venue pickers.
            </p>
        </div>
        <button type="button" @click="openCreate()"
                class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white
                       bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700
                       shadow-lg shadow-emerald-500/25 transition">
            <i class="fas fa-plus text-xs"></i>
            Add Ground
        </button>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="mb-4 flex items-start gap-3 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 px-4 py-3">
            <i class="fas fa-circle-check text-emerald-500 mt-0.5"></i>
            <p class="text-sm text-emerald-800 dark:text-emerald-300">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 flex items-start gap-3 rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-3">
            <i class="fas fa-triangle-exclamation text-red-500 mt-0.5"></i>
            <p class="text-sm text-red-800 dark:text-red-300">{{ session('error') }}</p>
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-3">
            <p class="text-sm font-semibold text-red-800 dark:text-red-300 mb-1">Please fix the following:</p>
            <ul class="list-disc list-inside text-sm text-red-700 dark:text-red-400 space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Search + filter. A GET form so the current view stays shareable and
         survives a save redirect. --}}
    <form method="GET" action="{{ route('admin.grounds.index') }}"
          class="mb-6 flex flex-col sm:flex-row gap-2">
        <div class="relative flex-1">
            <i class="fas fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
            <input type="search" name="search" value="{{ $search }}"
                   placeholder="Search by name, city or address…"
                   class="w-full pl-10 pr-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700
                          bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white
                          focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        <select name="status"
                class="sm:w-40 px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm">
            <option value="">All statuses</option>
            <option value="active" @selected($status === 'active')>Active only</option>
            <option value="inactive" @selected($status === 'inactive')>Inactive only</option>
        </select>
        <button type="submit"
                class="px-4 py-2.5 rounded-xl text-sm font-medium bg-gray-900 dark:bg-gray-700 text-white hover:bg-gray-800 transition">
            Search
        </button>
        @if($search || $status)
            <a href="{{ route('admin.grounds.index') }}"
               class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition text-center">
                Clear
            </a>
        @endif
    </form>

    @if($total > 0)
        <p class="text-xs text-gray-400 mb-3">
            {{ $total }} {{ Str::plural('ground', $total) }}
            @if($search || $status) matching your filters @endif
        </p>
    @endif

    {{-- Grounds grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse($grounds as $ground)
            <div class="group bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700
                        overflow-hidden hover:border-emerald-300 dark:hover:border-emerald-700 hover:shadow-lg transition">

                {{-- Image / fallback --}}
                <div class="relative h-36">
                    @if($ground->image && Storage::disk('public')->exists($ground->image))
                        <img src="{{ Storage::url($ground->image) }}" alt="{{ $ground->name }}"
                             class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full bg-gradient-to-br from-emerald-600 via-green-600 to-teal-700 flex items-center justify-center">
                            <i class="fas fa-location-dot text-4xl text-white/30"></i>
                        </div>
                    @endif

                    <span class="absolute top-2.5 right-2.5 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide backdrop-blur
                                 {{ $ground->is_active
                                    ? 'bg-emerald-500/90 text-white'
                                    : 'bg-gray-900/70 text-gray-200' }}">
                        {{ $ground->is_active ? 'Active' : 'Inactive' }}
                    </span>

                    @if($ground->matches_count > 0)
                        <span class="absolute bottom-2.5 left-2.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-black/60 text-white backdrop-blur">
                            {{ $ground->matches_count }} {{ Str::plural('match', $ground->matches_count) }}
                        </span>
                    @endif
                </div>

                {{-- Body --}}
                <div class="p-4">
                    <a href="{{ route('admin.grounds.show', $ground) }}"
                       class="block font-bold text-gray-900 dark:text-white leading-tight truncate hover:text-emerald-600 dark:hover:text-emerald-400 transition"
                       title="{{ $ground->name }}">
                        {{ $ground->name }}
                    </a>

                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">
                        @if($ground->city && $ground->address)
                            {{ $ground->address }}, {{ $ground->city }}
                        @elseif($ground->city)
                            {{ $ground->city }}
                        @elseif($ground->address)
                            {{ $ground->address }}
                        @else
                            <span class="italic text-gray-400">No address added</span>
                        @endif
                    </p>

                    @if($isSuperadmin && $ground->organization)
                        <p class="mt-1.5 inline-flex items-center gap-1 text-[10px] font-medium text-gray-400">
                            <i class="fas fa-building text-[9px]"></i> {{ $ground->organization->name }}
                        </p>
                    @endif

                    {{-- Actions --}}
                    <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center gap-1">
                        @if($ground->map_embed_url)
                            {{-- Opens an inline preview rather than 24 iframes on
                                 one page — the list stays fast and nobody has to
                                 leave the admin to check a pin. --}}
                            <button type="button" @click="openMap({{ $ground->id }})"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium
                                           text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition"
                                    title="Preview location on a map">
                                <i class="fas fa-map-location-dot text-[11px]"></i> Map
                            </button>
                        @endif

                        <button type="button" @click="openEdit({{ $ground->id }})"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium
                                       text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition ml-auto">
                            <i class="fas fa-pen text-[11px]"></i> Edit
                        </button>

                        {{-- Deleting a venue with matches would orphan fixtures, so the
                             button is replaced by an explanation rather than failing on click. --}}
                        @if($ground->matches_count > 0)
                            <span class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs text-gray-300 dark:text-gray-600 cursor-not-allowed"
                                  title="In use by {{ $ground->matches_count }} {{ Str::plural('match', $ground->matches_count) }} — mark it inactive instead">
                                <i class="fas fa-lock text-[11px]"></i>
                            </span>
                        @else
                            <form action="{{ route('admin.grounds.destroy', $ground) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Delete “{{ addslashes($ground->name) }}”? This cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-medium
                                               text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition"
                                        title="Delete">
                                    <i class="fas fa-trash text-[11px]"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 px-8 text-center bg-white dark:bg-gray-800 rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center">
                    <i class="fas fa-location-dot text-2xl text-emerald-500"></i>
                </div>
                @if($search || $status)
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">No grounds match your filters</h3>
                    <p class="mt-1 text-sm text-gray-500">Try a different search, or clear the filters.</p>
                    <a href="{{ route('admin.grounds.index') }}"
                       class="mt-5 inline-flex px-4 py-2 rounded-xl text-sm font-medium border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        Clear filters
                    </a>
                @else
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">No grounds yet</h3>
                    <p class="mt-1 text-sm text-gray-500 max-w-sm mx-auto">
                        Add the venues your matches are played at — they'll then be selectable on every fixture.
                    </p>
                    <button type="button" @click="openCreate()"
                            class="mt-5 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white
                                   bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700 shadow-lg shadow-emerald-500/25 transition">
                        <i class="fas fa-plus text-xs"></i> Add your first ground
                    </button>
                @endif
            </div>
        @endforelse
    </div>

    @if($grounds->hasPages())
        <div class="mt-8">{{ $grounds->links() }}</div>
    @endif

    {{-- ───────────────────────── Map preview ───────────────────────── --}}
    <div x-show="mapOpen" x-cloak
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
         x-transition.opacity>
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="closeMap()"></div>

        <div class="relative w-full sm:max-w-2xl bg-white dark:bg-gray-800 rounded-t-2xl sm:rounded-2xl shadow-2xl overflow-hidden"
             @keydown.escape.window="closeMap()">
            <div class="flex items-center justify-between gap-3 px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                <div class="min-w-0">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white truncate" x-text="mapGround.name"></h3>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate" x-text="mapGround.map_label"></p>
                </div>
                <button type="button" @click="closeMap()"
                        class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>

            {{-- src is bound only while open, so closing stops the frame loading
                 and reopening another ground cannot show the previous one. --}}
            <div class="bg-gray-100 dark:bg-gray-900" style="height: min(60vh, 420px);">
                <template x-if="mapOpen && mapGround.map_embed_url">
                    <iframe :src="mapGround.map_embed_url"
                            class="w-full h-full border-0"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen></iframe>
                </template>
            </div>

            <div class="flex items-center justify-between gap-2 px-5 py-3 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/95">
                <p class="text-[11px] text-gray-400">Preview only — drag and zoom inside the map.</p>
                <a :href="mapGround.map_external_url" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold
                          text-white bg-blue-600 hover:bg-blue-700 transition">
                    <i class="fas fa-arrow-up-right-from-square text-[10px]"></i> Open in Google Maps
                </a>
            </div>
        </div>
    </div>

    {{-- ───────────────────────── Add / Edit modal ───────────────────────── --}}
    <div x-show="open" x-cloak
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
         x-transition.opacity>
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="close()"></div>

        <div class="relative w-full sm:max-w-lg max-h-[92vh] overflow-y-auto bg-white dark:bg-gray-800
                    rounded-t-2xl sm:rounded-2xl shadow-2xl"
             @keydown.escape.window="close()">
            <form method="POST" :action="formAction" enctype="multipart/form-data" @submit="submitting = true">
                @csrf
                <template x-if="editing">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="sticky top-0 flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white"
                        x-text="editing ? 'Edit Ground' : 'Add Ground'"></h3>
                    <button type="button" @click="close()"
                            class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <i class="fas fa-xmark"></i>
                    </button>
                </div>

                <div class="px-5 py-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                            Ground name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" x-model="form.name" required maxlength="255"
                               placeholder="e.g. DCS You Selects Arena 1"
                               class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 dark:bg-gray-700 text-sm
                                      focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">City</label>
                            <input type="text" name="city" x-model="form.city" maxlength="100" placeholder="e.g. Sharjah"
                                   class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 dark:bg-gray-700 text-sm
                                          focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Area / locality</label>
                            <input type="text" name="address" x-model="form.address" maxlength="500" placeholder="e.g. Rahmaniyah"
                                   class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 dark:bg-gray-700 text-sm
                                          focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Google Maps link</label>
                        <input type="text" name="google_maps_link" x-model="form.google_maps_link" maxlength="500"
                               placeholder="Paste a share link — maps.app.goo.gl/…"
                               class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 dark:bg-gray-700 text-sm
                                      focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <p class="mt-1 text-[11px] text-gray-400">Optional. A share link without "https://" is fine.</p>
                    </div>

                    @if($needsOrgChoice)
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                                Organization <span class="text-red-500">*</span>
                            </label>
                            <select name="organization_id" x-model="form.organization_id" required
                                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 dark:bg-gray-700 text-sm
                                           focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">Select an organization…</option>
                                @foreach($organizations as $organization)
                                    <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif($organizations->count() === 1)
                        <input type="hidden" name="organization_id" value="{{ $organizations->first()->id }}">
                    @endif

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Photo</label>

                        <template x-if="form.image_url && !removeImage">
                            <div class="mb-2 flex items-center gap-3">
                                <img :src="form.image_url" alt="" class="w-20 h-14 rounded-lg object-cover border border-gray-200 dark:border-gray-600">
                                <button type="button" @click="removeImage = true"
                                        class="text-[11px] font-medium text-red-500 hover:text-red-700">Remove photo</button>
                            </div>
                        </template>
                        <template x-if="removeImage">
                            <div class="mb-2 flex items-center gap-3">
                                <p class="text-[11px] text-gray-500">Photo will be removed on save.</p>
                                <button type="button" @click="removeImage = false"
                                        class="text-[11px] font-medium text-gray-600 dark:text-gray-300 hover:underline">Keep it</button>
                            </div>
                        </template>
                        <input type="hidden" name="remove_image" :value="removeImage ? 1 : 0">

                        <input type="file" name="image" accept="image/png,image/jpeg,image/webp"
                               class="w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0
                                      file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700
                                      hover:file:bg-emerald-100 dark:file:bg-emerald-900/30 dark:file:text-emerald-300">
                        <p class="mt-1 text-[11px] text-gray-400">PNG, JPG or WebP, up to 4MB.</p>
                    </div>

                    <label class="flex items-start gap-2.5 cursor-pointer select-none pt-1">
                        {{-- Unchecked checkboxes are not posted, so a hidden 0 makes
                             "inactive" actually reach the server on edit. --}}
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" x-model="form.is_active"
                               class="mt-0.5 w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-xs text-gray-700 dark:text-gray-300">
                            <span class="font-semibold">Active</span>
                            <span class="block text-gray-400">Inactive grounds stay on past fixtures but are hidden when creating new ones.</span>
                        </span>
                    </label>
                </div>

                <div class="sticky bottom-0 flex items-center justify-end gap-2 px-5 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/95">
                    <button type="button" @click="close()"
                            class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        Cancel
                    </button>
                    <button type="submit" :disabled="submitting"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white
                                   bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700
                                   disabled:opacity-60 disabled:cursor-not-allowed shadow-lg shadow-emerald-500/25 transition">
                        <i class="fas" :class="submitting ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                        <span x-text="editing ? 'Save changes' : 'Add ground'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Editable fields for every ground on this page, so the cards and the
// ?action=edit deep link share one source of truth.
const GROUNDS = {!! $groundsJson !!};

function groundsPage(config) {
    const blank = () => ({
        name: '', city: '', address: '', google_maps_link: '',
        is_active: true, organization_id: config.defaultOrgId || '', image_url: null,
    });

    return {
        open: false,
        editing: false,
        submitting: false,
        removeImage: false,
        formAction: '{{ route('admin.grounds.store') }}',
        form: blank(),
        mapOpen: false,
        mapGround: { name: '', map_label: '', map_embed_url: '', map_external_url: '' },

        init() {
            // admin/grounds/create and .../{id}/edit have no page of their own —
            // the controller redirects here with these params so a stale link,
            // a typed URL or a failed save still lands on a working editor.
            const params = new URLSearchParams(window.location.search);
            if (params.get('action') === 'create') {
                this.openCreate();
            } else if (params.get('action') === 'edit') {
                const target = GROUNDS[params.get('ground')];
                // The ground may be on another page of the paginated list; falling
                // back to the list beats opening an empty "edit" form.
                if (target) this.openEdit(target.id);
            }

            // A validation failure redirects back; reopen so the typed values
            // are not silently lost behind a closed modal.
            @if($errors->any())
                this.openCreate();
                this.form.name = @json(old('name'));
                this.form.city = @json(old('city'));
                this.form.address = @json(old('address'));
                this.form.google_maps_link = @json(old('google_maps_link'));
                this.form.organization_id = @json(old('organization_id')) || this.form.organization_id;
            @endif
        },

        openCreate() {
            this.editing = false;
            this.removeImage = false;
            this.submitting = false;
            this.form = blank();
            this.formAction = '{{ route('admin.grounds.store') }}';
            this.open = true;
        },

        openEdit(id) {
            const ground = GROUNDS[id];
            if (!ground) return;

            this.editing = true;
            this.removeImage = false;
            this.submitting = false;
            this.form = {
                name: ground.name || '',
                city: ground.city || '',
                address: ground.address || '',
                google_maps_link: ground.google_maps_link || '',
                is_active: !!ground.is_active,
                organization_id: ground.organization_id || '',
                image_url: ground.image_url || null,
            };
            this.formAction = '{{ url('admin/grounds') }}/' + ground.id;
            this.open = true;
        },

        close() {
            this.open = false;
            this.submitting = false;
        },

        openMap(id) {
            const ground = GROUNDS[id];
            if (!ground?.map_embed_url) return;

            this.mapGround = ground;
            this.mapOpen = true;
        },

        closeMap() {
            this.mapOpen = false;
        },
    };
}
</script>
@endpush
@endsection
