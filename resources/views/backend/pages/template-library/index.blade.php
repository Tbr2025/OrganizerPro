@extends('backend.layouts.app')

@section('title')
    {{ __('Template presets') }} | {{ config('app.name') }}
@endsection

@section('admin-content')
<div class="p-4 mx-auto sm:p-6 max-w-7xl space-y-6" x-data="{
        panel: null,
        theme: @js(array_key_first($themes)),
        source: '', sourceTemplates: [], sourceLoading: false,
        async loadSource() {
            this.sourceTemplates = [];
            if (!this.source) return;
            this.sourceLoading = true;
            try {
                const r = await fetch(@js(route('admin.template-library.tournament-templates')) + '?tournament=' + encodeURIComponent(this.source), { headers: { Accept: 'application/json' } });
                this.sourceTemplates = r.ok ? (await r.json()).templates : [];
            } finally { this.sourceLoading = false; }
        },
    }">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    {{-- Whose presets --}}
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3">
        <div class="max-w-3xl space-y-1">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ $scope === null ? __('Global presets') : __(':org presets', ['org' => $scopeName]) }}
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                @if($scope === null)
                    {{ __('Offered to every organization when a tournament or season is created. Organizations can copy one into their own presets to change it.') }}
                @else
                    {{ __('Offered to :org\'s tournaments, alongside the global presets below. Whoever creates a tournament ticks the ones it should start with.', ['org' => $scopeName]) }}
                @endif
                {{ __('Ticked by default and Default (one per poster type) are set here; editing a preset only changes tournaments created afterwards.') }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2 items-end">
            @if($isSuper)
                <form method="GET" action="{{ route('admin.template-library.index') }}">
                    <label class="text-xs text-gray-500 block">{{ __('Showing') }}</label>
                    <select name="scope" onchange="this.form.submit()" class="form-control text-sm mt-1">
                        <option value="global" @selected($scope === null)>{{ __('Global presets') }}</option>
                        @foreach($organizations as $org)
                            <option value="{{ $org->id }}" @selected($scope === $org->id)>{{ $org->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <button type="button" @click="panel = panel === 'tournament' ? null : 'tournament'"
                    :class="panel === 'tournament' ? 'bg-indigo-50 border-indigo-400 dark:bg-indigo-900/30' : 'border-gray-300 dark:border-gray-600'"
                    class="px-4 py-2 rounded-lg border text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ __('Start from a previous tournament') }}
            </button>
            <button type="button" @click="panel = panel === 'readyMade' ? null : 'readyMade'"
                    :class="panel === 'readyMade' ? 'bg-indigo-50 border-indigo-400 dark:bg-indigo-900/30' : 'border-gray-300 dark:border-gray-600'"
                    class="px-4 py-2 rounded-lg border text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ __('Start from a ready-made design') }}
            </button>
        </div>
    </div>

    {{-- A previous tournament's template: copied in as a preset, files and all --}}
    <div x-show="panel === 'tournament'" x-cloak class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 space-y-4">
        @if($sourceTournaments->isEmpty())
            <p class="text-sm text-gray-500">{{ __('No tournament you can see has any templates yet.') }}</p>
        @else
            <div class="flex flex-col sm:flex-row sm:items-end gap-3">
                <label class="flex-1 text-sm text-gray-700 dark:text-gray-300">{{ __('Tournament') }}
                    <select x-model="source" @change="loadSource()" class="form-control w-full mt-1">
                        <option value="">{{ __('Choose a tournament…') }}</option>
                        @foreach($sourceTournaments as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}{{ $t->start_date ? ' · ' . \Illuminate\Support\Carbon::parse($t->start_date)->format('M Y') : '' }} ({{ trans_choice(':n template|:n templates', $t->templates_count, ['n' => $t->templates_count]) }})</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <p class="text-xs text-gray-500">{{ __('Click a template to copy it into these presets and open it in the editor. The tournament keeps its own.') }}</p>
            <p x-show="sourceLoading" class="text-sm text-gray-500">{{ __('Loading…') }}</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                <template x-for="t in sourceTemplates" :key="t.id">
                    <form method="POST" :action="@js(route('admin.template-library.import', ['template' => '__ID__', 'scope' => $scopeParam])).replace('__ID__', t.id)">
                        @csrf
                        <button class="w-full text-left rounded-lg border-2 border-gray-200 dark:border-gray-700 overflow-hidden hover:border-indigo-400 transition">
                            <img :src="t.thumb" loading="lazy" alt="" class="w-full bg-gray-100 dark:bg-gray-800" style="aspect-ratio:4/5;object-fit:contain" onerror="this.style.visibility='hidden'">
                            <span class="block p-1.5">
                                <span class="block text-xs font-medium text-gray-900 dark:text-white truncate" x-text="t.name"></span>
                                <span class="block text-[11px] text-gray-500 truncate" x-text="t.type"></span>
                            </span>
                        </button>
                    </form>
                </template>
            </div>
        @endif
    </div>

    {{-- Ready-made designs: a starting point that becomes an ordinary, editable preset --}}
    <div x-show="panel === 'readyMade'" x-cloak class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('Pick a theme, then a design. It is added to these presets and opened in the editor so you can make it your own.') }}</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach($themes as $t)
                    <button type="button" @click="theme = @js($t['key'])"
                            :class="theme === @js($t['key']) ? 'ring-2 ring-offset-1 ring-indigo-500' : ''"
                            class="px-2.5 py-1 rounded-full text-xs font-semibold"
                            style="background:linear-gradient(135deg, {{ $t['from'] }}, {{ $t['to'] }}); color: {{ $t['accent'] }}">{{ $t['name'] }}</button>
                @endforeach
            </div>
        </div>
        @foreach($readyMade as $type => $list)
            <div>
                <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1.5">{{ \App\Models\TournamentTemplate::getTypeDisplay($type) }}</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                    @foreach($list as $p)
                        <form method="POST" action="{{ route('admin.template-library.add-ready-made', ['scope' => $scopeParam]) }}">
                            @csrf
                            <input type="hidden" name="preset" value="{{ $p['key'] }}">
                            <input type="hidden" name="theme" :value="theme">
                            <button class="w-full text-left rounded-lg border-2 border-gray-200 dark:border-gray-700 overflow-hidden hover:border-indigo-400 transition">
                                <img loading="lazy" alt="" class="w-full bg-gray-100 dark:bg-gray-800" style="aspect-ratio:4/5;object-fit:contain"
                                     @if($p['themed'])
                                         :src="@js(route('admin.template-library.ready-made-thumb', ['key' => $p['key'], 'theme' => '__T__'])).replace('__T__', theme)"
                                     @else
                                         src="{{ route('admin.template-library.ready-made-thumb', ['key' => $p['key'], 'theme' => 'own']) }}"
                                     @endif>
                                <span class="block p-1.5 text-xs font-medium text-gray-900 dark:text-white truncate">{{ $p['name'] }}</span>
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- This scope's presets, by poster type --}}
    @foreach($types as $type)
        @php $list = $templates[$type] ?? collect(); @endphp
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center gap-2 flex-wrap">
                <div class="min-w-0">
                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm truncate">{{ \App\Models\TournamentTemplate::getTypeDisplay($type) }}</h3>
                    <p class="text-xs text-gray-500">{{ $list->isEmpty() ? __('No presets yet') : trans_choice(':n preset|:n presets', $list->count(), ['n' => $list->count()]) }}</p>
                </div>
                <a href="{{ route('admin.template-library.create', ['type' => $type, 'scope' => $scopeParam]) }}" class="flex-shrink-0 text-sm text-indigo-600 hover:text-indigo-800 font-medium whitespace-nowrap">
                    + {{ __('New preset') }}
                </a>
            </div>

            @if($list->isNotEmpty())
                <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach($list as $template)
                        <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                            <a href="{{ route('admin.template-library.edit', $template) }}" class="block relative">
                                <img src="{{ route('admin.template-library.thumb', [$template, 'v' => $template->design_version]) }}" alt="{{ $template->name }}" loading="lazy"
                                     class="w-full bg-gray-100 dark:bg-gray-800" style="aspect-ratio:4/5;object-fit:contain" onerror="this.style.visibility='hidden'">
                                <div class="absolute top-2 left-2 flex flex-wrap gap-1">
                                    @if($template->is_default)
                                        <span class="bg-green-500 text-white text-xs px-2 py-0.5 rounded-full font-medium">{{ __('Default') }}</span>
                                    @endif
                                    <span class="{{ $template->is_active ? 'bg-indigo-500' : 'bg-gray-500' }} text-white text-xs px-2 py-0.5 rounded-full font-medium">
                                        {{ $template->is_active ? __('Ticked by default') : __('Not ticked') }}
                                    </span>
                                    @if($template->isPremium())
                                        <span class="bg-amber-500 text-white text-xs px-2 py-0.5 rounded-full font-medium">{{ __('Premium') }} · {{ number_format($template->price) }}</span>
                                    @else
                                        <span class="bg-sky-500 text-white text-xs px-2 py-0.5 rounded-full font-medium">{{ __('Free') }}</span>
                                    @endif
                                </div>
                            </a>
                            <div class="p-3 space-y-2">
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white text-sm truncate">{{ $template->name }}</h4>
                                    <p class="text-xs text-gray-500">{{ $template->canvas_width ?? 1080 }} × {{ $template->canvas_height ?? 1080 }} · {{ __('edited :when', ['when' => $template->updated_at->diffForHumans()]) }}</p>
                                </div>
                                <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs font-medium">
                                    <a href="{{ route('admin.template-library.edit', $template) }}" class="text-indigo-600 hover:text-indigo-800">{{ __('Edit') }}</a>
                                    <form method="POST" action="{{ route('admin.template-library.duplicate', $template) }}">@csrf
                                        <button class="text-blue-600 hover:text-blue-800">{{ __('Duplicate') }}</button>
                                    </form>
                                    @unless($template->is_default)
                                        <form method="POST" action="{{ route('admin.template-library.set-default', $template) }}">@csrf
                                            <button class="text-green-600 hover:text-green-800">{{ __('Make default') }}</button>
                                        </form>
                                    @endunless
                                    <form method="POST" action="{{ route('admin.template-library.toggle-active', $template) }}">@csrf
                                        <button class="text-gray-600 dark:text-gray-300 hover:text-gray-900">{{ $template->is_active ? __('Untick by default') : __('Tick by default') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.template-library.destroy', $template) }}" onsubmit="return confirm(@js(__('Delete this preset? Tournaments that already have a copy keep it.')))">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 hover:text-red-800">{{ __('Delete') }}</button>
                                    </form>
                                </div>
                                @php $use = $usage[$template->id] ?? ['copies' => 0, 'behind' => 0]; @endphp
                                @if($use['copies'] > 0)
                                    <div class="flex items-center justify-between gap-2 text-xs rounded bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-2 py-1.5">
                                        <span class="text-gray-600 dark:text-gray-300">
                                            {{ trans_choice('Used by :n tournament template|Used by :n tournament templates', $use['copies'], ['n' => $use['copies']]) }}@if($use['behind']) · <span class="text-amber-600 font-medium">{{ __(':n behind', ['n' => $use['behind']]) }}</span>@endif
                                        </span>
                                        @if($use['behind'])
                                            <form method="POST" action="{{ route('admin.template-library.sync', $template) }}"
                                                  onsubmit="return confirm(@js(__('Update the tournaments\' copies to this version? Copies a tournament has edited are left as they are.')))">@csrf
                                                <button class="text-indigo-600 hover:text-indigo-800 font-semibold whitespace-nowrap">{{ __('Update tournaments') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                @endif

                                {{-- Free or Premium, and who it is offered to --}}
                                <details class="text-xs">
                                    <summary class="cursor-pointer text-gray-600 dark:text-gray-300 font-medium">{{ __('Pricing & availability') }}</summary>
                                    <form method="POST" action="{{ route('admin.template-library.settings', $template) }}" class="mt-2 space-y-2"
                                          x-data="{ tier: @js($template->tier ?? 'free'), offer: @js($template->offered_to ? 'selected' : 'all') }">
                                        @csrf
                                        <div class="flex items-center gap-3">
                                            <label class="flex items-center gap-1"><input type="radio" name="tier" value="free" x-model="tier"> {{ __('Free') }}</label>
                                            <label class="flex items-center gap-1"><input type="radio" name="tier" value="premium" x-model="tier"> {{ __('Premium') }}</label>
                                            <input type="number" name="price" min="1" value="{{ $template->price ?: '' }}" x-show="tier === 'premium'" x-cloak
                                                   placeholder="{{ __('Credits') }}" class="w-24 text-xs py-0.5 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                                        </div>
                                        <p class="text-gray-500" x-show="tier === 'free'">{{ __('Free: tournaments can change only its background (a paid change).') }}</p>
                                        <p class="text-gray-500" x-show="tier === 'premium'" x-cloak>{{ __('Premium: charged once per tournament; organizers can change its background for free.') }}</p>
                                        @if($template->organization_id === null && $organizations->isNotEmpty())
                                            <div class="space-y-1">
                                                <label class="flex items-center gap-1"><input type="radio" name="offer" value="all" x-model="offer"> {{ __('Offered to every organization') }}</label>
                                                <label class="flex items-center gap-1"><input type="radio" name="offer" value="selected" x-model="offer"> {{ __('Only to selected organizations') }}</label>
                                                <div x-show="offer === 'selected'" x-cloak class="max-h-28 overflow-y-auto pl-4 space-y-0.5">
                                                    @foreach($organizations as $org)
                                                        <label class="flex items-center gap-1"><input type="checkbox" name="offered_to[]" value="{{ $org->id }}" @checked(in_array($org->id, $template->offered_to ?? []))> {{ $org->name }}</label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                        <button class="px-2 py-1 rounded bg-indigo-600 text-white font-semibold">{{ __('Save') }}</button>
                                    </form>
                                </details>

                                @if($isSuper)
                                    <form method="POST" action="{{ route('admin.template-library.move', $template) }}" class="flex items-center gap-1.5 text-xs">
                                        @csrf
                                        <select name="scope" class="text-xs py-0.5 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 flex-1 min-w-0">
                                            <option value="global" @selected($scope === null)>{{ __('Global') }}</option>
                                            @foreach($organizations as $org)
                                                <option value="{{ $org->id }}" @selected($scope === $org->id)>{{ $org->name }}</option>
                                            @endforeach
                                        </select>
                                        <button class="text-gray-600 dark:text-gray-300 hover:text-gray-900 font-medium">{{ __('Move') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach

    {{-- Global presets, read-only here: an organization copies one to change it --}}
    @if($scope !== null && $global->isNotEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-5 space-y-4">
            <div>
                <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('Global presets') }}</h3>
                <p class="text-xs text-gray-500">{{ __('Also offered to :org\'s tournaments. Copy one into your own presets to change it.', ['org' => $scopeName]) }}</p>
            </div>
            @foreach($global as $type => $list)
                <div>
                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1.5">{{ \App\Models\TournamentTemplate::getTypeDisplay($type) }}</p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                        @foreach($list as $template)
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <img src="{{ route('admin.template-library.thumb', [$template, 'v' => $template->design_version]) }}" alt="" loading="lazy"
                                     class="w-full bg-gray-100 dark:bg-gray-800" style="aspect-ratio:4/5;object-fit:contain" onerror="this.style.visibility='hidden'">
                                <div class="p-1.5 space-y-1">
                                    <p class="text-xs font-medium text-gray-900 dark:text-white truncate">{{ $template->name }}</p>
                                    <form method="POST" action="{{ route('admin.template-library.duplicate', $template) }}">@csrf
                                        <button class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">{{ $isSuper ? __('Duplicate') : __('Copy to my presets') }}</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
