@extends('backend.layouts.app')

@section('title', 'Templates | ' . $tournament->name)

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Tournaments', 'route' => route('admin.tournaments.index')],
    ['name' => $tournament->name, 'route' => route('admin.tournaments.show', $tournament)],
    ['name' => 'Templates']
]" />

<div class="card p-4">
    <div class="flex justify-between items-center mb-4">
        <div>
            <h2 class="text-xl font-bold">Tournament Templates</h2>
            <p class="text-gray-500 text-sm">Manage poster and card templates for {{ $tournament->name }}</p>
        </div>
        <a href="{{ route('admin.tournaments.templates.create', $tournament) }}" class="btn-primary">
            + New Template
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <!-- Template Types Navigation -->
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="flex space-x-4 overflow-x-auto" aria-label="Tabs">
            @foreach($templateTypes as $type)
                <button
                    type="button"
                    class="px-4 py-2 text-sm font-medium rounded-t-lg border-b-2 transition-colors
                           {{ request('type') === $type ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                    onclick="filterByType('{{ $type }}')"
                >
                    {{ \App\Models\TournamentTemplate::getTypeDisplay($type) ?? ucwords(str_replace('_', ' ', $type)) }}
                    @if(isset($templates[$type]))
                        <span class="ml-1 bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-xs">
                            {{ $templates[$type]->count() }}
                        </span>
                    @endif
                </button>
            @endforeach
        </nav>
    </div>

    <!-- Templates Grid -->
    @foreach($templateTypes as $type)
        <div id="type-{{ $type }}" class="template-type-section mb-8">
            <h3 class="text-lg font-semibold mb-4 capitalize">
                {{ ucwords(str_replace('_', ' ', $type)) }}
            </h3>

            @if(isset($templates[$type]) && $templates[$type]->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($templates[$type] as $template)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                            <!-- Template Preview -->
                            <div class="aspect-square bg-gray-100 dark:bg-gray-800 relative">
                                @if($template->background_image)
                                    <img src="{{ $template->background_image_url }}"
                                         alt="{{ $template->name }}"
                                         class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-400">
                                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                @endif

                                <!-- Default Badge -->
                                @if($template->is_default)
                                    <span class="absolute top-2 right-2 bg-primary-500 text-white text-xs px-2 py-1 rounded">
                                        Default
                                    </span>
                                @endif

                                <!-- Inactive Overlay -->
                                @if(!$template->is_active)
                                    <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                                        <span class="text-white font-medium">Inactive</span>
                                    </div>
                                @endif
                            </div>

                            <!-- Template Info -->
                            <div class="p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white">{{ $template->name }}</h4>
                                <p class="text-sm text-gray-500">{{ $template->type_display }}</p>

                                <!-- Actions -->
                                <div class="mt-3 flex items-center justify-between">
                                    <div class="flex space-x-2">
                                        <a href="{{ route('admin.tournaments.templates.edit', [$tournament, $template]) }}"
                                           class="text-sm text-primary-600 hover:text-primary-800">
                                            Edit
                                        </a>
                                        <a href="{{ route('admin.tournaments.templates.preview', [$tournament, $template]) }}"
                                           class="text-sm text-gray-600 hover:text-gray-800"
                                           target="_blank">
                                            Preview
                                        </a>
                                    </div>

                                    <div class="flex space-x-2">
                                        @if(!$template->is_default)
                                            <form action="{{ route('admin.tournaments.templates.set-default', [$tournament, $template]) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="text-sm text-green-600 hover:text-green-800">
                                                    Set Default
                                                </button>
                                            </form>
                                        @endif

                                        <form action="{{ route('admin.tournaments.templates.destroy', [$tournament, $template]) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Are you sure you want to delete this template?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-red-600 hover:text-red-800">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <!-- Add New Template Card -->
                    <a href="{{ route('admin.tournaments.templates.create', ['tournament' => $tournament, 'type' => $type]) }}"
                       class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg flex items-center justify-center min-h-[300px] hover:border-primary-500 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <div class="text-center text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            <span>Add {{ ucwords(str_replace('_', ' ', $type)) }} Template</span>
                        </div>
                    </a>
                </div>
            @else
                <div class="text-center py-8 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <p class="text-gray-500 mb-4">No {{ str_replace('_', ' ', $type) }} templates yet.</p>
                    <a href="{{ route('admin.tournaments.templates.create', ['tournament' => $tournament, 'type' => $type]) }}"
                       class="btn-primary">
                        Create First Template
                    </a>
                </div>
            @endif
        </div>
    @endforeach
</div>

@push('scripts')
<script>
function filterByType(type) {
    // Show all sections or filter
    document.querySelectorAll('.template-type-section').forEach(section => {
        section.style.display = type ? 'none' : 'block';
    });
    if (type) {
        document.getElementById('type-' + type).style.display = 'block';
    }
}
</script>
@endpush
@endsection
