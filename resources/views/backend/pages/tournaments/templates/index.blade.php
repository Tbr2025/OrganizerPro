@extends('backend.layouts.app')

@section('title', 'Templates | ' . $tournament->name)

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Tournaments', 'route' => route('admin.tournaments.index')],
    ['name' => $tournament->name, 'route' => route('admin.tournaments.show', $tournament)],
    ['name' => 'Templates']
]" />

<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-indigo-600 to-purple-700 rounded-xl p-6 text-white">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold">Template Designer</h1>
                <p class="text-indigo-200 mt-1">Create and manage visual templates for {{ $tournament->name }}</p>
                <div class="flex items-center gap-4 mt-3">
                    <span class="text-sm bg-white/20 px-3 py-1 rounded-full">
                        {{ $templates->flatten()->count() }} Templates
                    </span>
                    <span class="text-sm bg-white/20 px-3 py-1 rounded-full">
                        {{ count($templateTypes) }} Categories
                    </span>
                </div>
            </div>
            <a href="{{ route('admin.tournaments.templates.create', $tournament) }}"
               class="inline-flex items-center px-4 py-2 bg-white text-indigo-700 font-semibold rounded-lg hover:bg-indigo-50 transition">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                New Template
            </a>
        </div>
    </div>

    {{-- Template Type Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
        @php
            $typeIcons = [
                'welcome_card' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />',
                'match_poster' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />',
                'match_summary' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />',
                'award_poster' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />',
                'flyer' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />',
                'champions_poster' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />',
                'point_table' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />',
            ];
            $typeColors = [
                'welcome_card' => 'from-blue-500 to-cyan-500',
                'match_poster' => 'from-orange-500 to-red-500',
                'match_summary' => 'from-green-500 to-emerald-500',
                'award_poster' => 'from-yellow-500 to-amber-500',
                'flyer' => 'from-pink-500 to-rose-500',
                'champions_poster' => 'from-purple-500 to-violet-500',
                'point_table' => 'from-gray-500 to-slate-500',
            ];
        @endphp
        @foreach($templateTypes as $type)
            <a href="{{ route('admin.tournaments.templates.create', ['tournament' => $tournament, 'type' => $type]) }}"
               class="group relative bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:shadow-lg transition-all hover:-translate-y-1">
                <div class="w-12 h-12 rounded-lg bg-gradient-to-br {{ $typeColors[$type] ?? 'from-gray-500 to-gray-600' }} flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        {!! $typeIcons[$type] ?? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />' !!}
                    </svg>
                </div>
                <h3 class="font-medium text-gray-900 dark:text-white text-sm">{{ \App\Models\TournamentTemplate::getTypeDisplay($type) }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ isset($templates[$type]) ? $templates[$type]->count() : 0 }} template(s)
                </p>
                <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition">
                    <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded">+ Add</span>
                </div>
            </a>
        @endforeach
    </div>

    {{-- Templates by Type --}}
    @foreach($templateTypes as $type)
        @if(isset($templates[$type]) && $templates[$type]->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br {{ $typeColors[$type] ?? 'from-gray-500 to-gray-600' }} flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                {!! $typeIcons[$type] ?? '' !!}
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ \App\Models\TournamentTemplate::getTypeDisplay($type) }}</h3>
                            <p class="text-xs text-gray-500">{{ $templates[$type]->count() }} template(s)</p>
                        </div>
                    </div>
                    <a href="{{ route('admin.tournaments.templates.create', ['tournament' => $tournament, 'type' => $type]) }}"
                       class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                        + Add New
                    </a>
                </div>

                <div class="p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        @foreach($templates[$type] as $template)
                            <div class="group relative bg-gray-50 dark:bg-gray-900 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 hover:border-indigo-300 transition">
                                {{-- Preview Image --}}
                                <div class="aspect-square relative overflow-hidden">
                                    @if($template->background_image)
                                        <img src="{{ $template->background_image_url }}"
                                             alt="{{ $template->name }}"
                                             class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900">
                                            <svg class="w-16 h-16 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    @endif

                                    {{-- Badges --}}
                                    <div class="absolute top-2 left-2 flex gap-1">
                                        @if($template->is_default)
                                            <span class="bg-green-500 text-white text-xs px-2 py-0.5 rounded-full font-medium">Default</span>
                                        @endif
                                        @if(!$template->is_active)
                                            <span class="bg-gray-500 text-white text-xs px-2 py-0.5 rounded-full font-medium">Inactive</span>
                                        @endif
                                    </div>

                                    {{-- Hover Actions --}}
                                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
                                        <a href="{{ route('admin.tournaments.templates.edit', [$tournament, $template]) }}"
                                           class="p-2 bg-white rounded-lg hover:bg-gray-100 transition" title="Edit">
                                            <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>
                                        <a href="{{ route('admin.tournaments.templates.preview', [$tournament, $template]) }}"
                                           class="p-2 bg-white rounded-lg hover:bg-gray-100 transition" title="Preview" target="_blank">
                                            <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </a>
                                        <form action="{{ route('admin.tournaments.templates.destroy', [$tournament, $template]) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Delete this template?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-2 bg-white rounded-lg hover:bg-red-50 transition" title="Delete">
                                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                {{-- Info --}}
                                <div class="p-3">
                                    <h4 class="font-medium text-gray-900 dark:text-white text-sm truncate">{{ $template->name }}</h4>
                                    <div class="flex items-center justify-between mt-2">
                                        <span class="text-xs text-gray-500">{{ $template->created_at->diffForHumans() }}</span>
                                        @if(!$template->is_default)
                                            <form action="{{ route('admin.tournaments.templates.set-default', [$tournament, $template]) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                                    Set Default
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endforeach

    {{-- Empty State --}}
    @if($templates->flatten()->count() === 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 p-12 text-center">
            <div class="w-16 h-16 mx-auto bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No Templates Yet</h3>
            <p class="text-gray-500 mb-6">Create your first template to generate posters and cards for your tournament.</p>
            <a href="{{ route('admin.tournaments.templates.create', $tournament) }}" class="btn-primary">
                Create Your First Template
            </a>
        </div>
    @endif
</div>
@endsection
