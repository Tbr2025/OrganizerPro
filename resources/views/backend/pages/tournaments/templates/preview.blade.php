@extends('backend.layouts.app')

@section('title', 'Preview Template | ' . $tournament->name)

@php
    $imagePlaceholders = ['player_image', 'team_logo', 'tournament_logo', 'team_a_logo', 'team_b_logo',
                          'team_a_captain_image', 'team_b_captain_image', 'man_of_the_match_image',
                          'team_a_sponsor_logo', 'team_b_sponsor_logo', 'qr_code'];
    $placeholders = \App\Models\TournamentTemplate::getDefaultPlaceholders($template->type);
@endphp

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Tournaments', 'route' => route('admin.tournaments.index')],
    ['name' => $tournament->name, 'route' => route('admin.tournaments.dashboard', $tournament)],
    ['name' => 'Templates', 'route' => route('admin.tournaments.templates.index', $tournament)],
    ['name' => 'Preview: ' . $template->name]
]" />

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Preview Panel -->
    <div class="lg:col-span-2">
        <div class="card p-4">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-xl font-bold">{{ $template->name }}</h2>
                    <p class="text-gray-500 text-sm">{{ $template->type_display }} Template Preview</p>
                </div>
                <div class="flex space-x-2">
                    <a href="{{ route('admin.tournaments.templates.edit', [$tournament, $template]) }}"
                       class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                        Edit Template
                    </a>
                    @if($previewUrl)
                        <button onclick="downloadPreview()" class="btn-primary">
                            Download HD
                        </button>
                    @endif
                </div>
            </div>

            <!-- Error Message -->
            @if(isset($previewError) && $previewError)
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        {{ $previewError }}
                    </div>
                </div>
            @endif

            <!-- Layout Info -->
            @if(empty($template->layout_json))
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded mb-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        No layout elements configured. <a href="{{ route('admin.tournaments.templates.edit', [$tournament, $template]) }}" class="underline font-medium">Add elements to the template</a> to see a rendered preview.
                    </div>
                </div>
            @endif

            <!-- Preview Image -->
            <div class="bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden flex items-center justify-center p-4 min-h-[400px]">
                @if($previewUrl)
                    <img src="{!! $previewUrl !!}"
                         alt="Template Preview"
                         class="max-w-full h-auto shadow-lg rounded"
                         id="previewImage">
                @else
                    <div class="text-center text-gray-400 py-16">
                        <svg class="w-24 h-24 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-lg">Preview not available</p>
                        <p class="text-sm mt-2">Upload a background image to generate previews</p>
                    </div>
                @endif
            </div>

            <!-- Layout Elements Debug -->
            @if(!empty($template->layout_json))
                <div class="mt-4 text-xs text-gray-500">
                    <span class="font-medium">{{ count($template->layout_json) }} elements</span> in layout
                </div>
            @endif
        </div>
    </div>

    <!-- Custom Data Panel -->
    <div class="lg:col-span-1">
        <div class="card p-4 sticky top-4 max-h-[calc(100vh-100px)] overflow-y-auto">
            <h3 class="font-bold mb-4">Test with Custom Data</h3>

            <form id="previewForm" action="{{ route('admin.tournaments.templates.preview', [$tournament, $template]) }}" method="POST" enctype="multipart/form-data">
                @csrf

                {{-- Text Fields --}}
                <div class="space-y-3 mb-4">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Text Fields</h4>
                    @foreach($placeholders as $placeholder)
                        @if(!in_array($placeholder, $imagePlaceholders))
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    {{ str_replace('_', ' ', ucwords($placeholder, '_')) }}
                                </label>
                                <input type="text"
                                       name="{{ $placeholder }}"
                                       value="{{ request($placeholder, $sampleData[$placeholder] ?? '') }}"
                                       placeholder="{{ $placeholder }}"
                                       class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Image Fields --}}
                @php
                    $templateImagePlaceholders = array_intersect($placeholders, $imagePlaceholders);
                @endphp
                @if(count($templateImagePlaceholders) > 0)
                    <div class="space-y-3 mb-4 pt-4 border-t dark:border-gray-700">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Image Fields</h4>
                        @foreach($templateImagePlaceholders as $placeholder)
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    {{ str_replace('_', ' ', ucwords($placeholder, '_')) }}
                                </label>
                                <div class="flex items-center gap-2">
                                    <input type="file"
                                           name="{{ $placeholder }}"
                                           accept="image/*"
                                           class="w-full text-xs file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                    @if(isset($sampleData[$placeholder]) && !str_starts_with($sampleData[$placeholder], '['))
                                        <span class="text-xs text-green-600" title="Has default image">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <button type="submit" class="w-full btn-primary text-sm" id="regenerateBtn">
                    <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Regenerate Preview
                </button>
            </form>

            <!-- Quick Actions -->
            <div class="mt-4 pt-4 border-t dark:border-gray-700 space-y-2">
                <button onclick="resetForm()" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                    Reset to Defaults
                </button>
                <a href="{{ route('admin.tournaments.templates.index', $tournament) }}"
                   class="block text-center w-full px-3 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                    &larr; Back to Templates
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function downloadPreview() {
    const img = document.getElementById('previewImage');
    if (!img || !img.src) return;

    const link = document.createElement('a');
    link.download = '{{ $template->name }}-preview.png';
    link.href = img.src;
    link.click();
}

function resetForm() {
    document.getElementById('previewForm').reset();
    // Redirect to clean preview
    window.location.href = '{{ route('admin.tournaments.templates.preview', [$tournament, $template]) }}';
}

// Show loading state on form submit
document.getElementById('previewForm').addEventListener('submit', function() {
    const btn = document.getElementById('regenerateBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 mr-1 inline animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Generating...';
});
</script>
@endpush
