@extends('backend.layouts.app')

@section('title')
    {{ __('Template library') }} | {{ config('app.name') }}
@endsection

@section('admin-content')
<div class="p-4 mx-auto sm:p-6 max-w-7xl space-y-6" x-data="{ adding: false }">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <p class="text-sm text-gray-500 dark:text-gray-400 max-w-3xl">
            {{ __('Every new tournament starts with a copy of each active template here, and a new season can too. Edit a template here and the next tournament gets the edited version; tournaments that already have a copy keep their own. The Default of each type becomes the new tournament\'s default.') }}
        </p>
        <div class="flex gap-2 flex-shrink-0">
            <button type="button" @click="adding = !adding" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ __('Add a ready-made design') }}
            </button>
        </div>
    </div>

    {{-- Ready-made designs, dropped into the library to be edited --}}
    <div x-show="adding" x-cloak class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 space-y-4">
        <form method="POST" action="{{ route('admin.template-library.add-preset') }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
            @csrf
            <label class="flex-1 text-sm text-gray-700 dark:text-gray-300">{{ __('Design') }}
                <select name="preset" required class="form-control w-full mt-1">
                    @foreach($presets as $type => $list)
                        <optgroup label="{{ \App\Models\TournamentTemplate::getTypeDisplay($type) }}">
                            @foreach($list as $p)
                                <option value="{{ $p['key'] }}">{{ $p['name'] }}{{ $p['is_active'] ? '' : ' (' . __('switched off') . ')' }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </label>
            <label class="sm:w-56 text-sm text-gray-700 dark:text-gray-300">{{ __('Theme') }}
                <select name="theme" class="form-control w-full mt-1">
                    @foreach($themes as $theme)
                        <option value="{{ $theme['key'] }}">{{ $theme['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <button class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">{{ __('Add to library') }}</button>
        </form>
        <p class="text-xs text-gray-500">{{ __('To bring in a design you already made for a tournament, open that tournament\'s Templates page and use "Save to library" on it.') }}</p>
    </div>

    @foreach($types as $type)
        @php $list = $templates[$type] ?? collect(); @endphp
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center gap-2 flex-wrap">
                <div class="min-w-0">
                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm truncate">{{ \App\Models\TournamentTemplate::getTypeDisplay($type) }}</h3>
                    <p class="text-xs text-gray-500">
                        @if($list->isEmpty())
                            {{ __('None — new tournaments get no :type.', ['type' => strtolower(\App\Models\TournamentTemplate::getTypeDisplay($type))]) }}
                        @else
                            {{ trans_choice(':n template|:n templates', $list->count(), ['n' => $list->count()]) }}
                        @endif
                    </p>
                </div>
                <a href="{{ route('admin.template-library.create', ['type' => $type]) }}" class="flex-shrink-0 text-sm text-indigo-600 hover:text-indigo-800 font-medium whitespace-nowrap">
                    + {{ __('New template') }}
                </a>
            </div>

            @if($list->isNotEmpty())
                <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach($list as $template)
                        <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 {{ $template->is_active ? '' : 'opacity-60' }}">
                            <a href="{{ route('admin.template-library.edit', $template) }}" class="block aspect-square relative overflow-hidden">
                                @if($template->background_image)
                                    <img src="{{ $template->background_image_url }}" alt="{{ $template->name }}" loading="lazy" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900 text-xs text-gray-400">
                                        {{ __(':n layers', ['n' => count($template->layout_json ?? [])]) }}
                                    </div>
                                @endif
                                <div class="absolute top-2 left-2 flex gap-1">
                                    @if($template->is_default)
                                        <span class="bg-green-500 text-white text-xs px-2 py-0.5 rounded-full font-medium">{{ __('Default') }}</span>
                                    @endif
                                    @if(! $template->is_active)
                                        <span class="bg-gray-500 text-white text-xs px-2 py-0.5 rounded-full font-medium">{{ __('Not copied') }}</span>
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
                                            <button class="text-green-600 hover:text-green-800">{{ __('Set default') }}</button>
                                        </form>
                                    @endunless
                                    <form method="POST" action="{{ route('admin.template-library.toggle-active', $template) }}">@csrf
                                        <button class="text-gray-600 dark:text-gray-300 hover:text-gray-900">{{ $template->is_active ? __('Don\'t copy') : __('Copy again') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.template-library.destroy', $template) }}" onsubmit="return confirm('{{ __('Remove this template from the library? Tournaments that already have a copy keep it.') }}')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 hover:text-red-800">{{ __('Delete') }}</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>
@endsection
