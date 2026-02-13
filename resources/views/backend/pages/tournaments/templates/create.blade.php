@extends('backend.layouts.app')

@section('title', 'Create Template | ' . $tournament->name)

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Tournaments', 'route' => route('admin.tournaments.index')],
    ['name' => $tournament->name, 'route' => route('admin.tournaments.show', $tournament)],
    ['name' => 'Templates', 'route' => route('admin.tournaments.templates.index', $tournament)],
    ['name' => 'Create']
]" />

<div class="card p-6">
    <div class="mb-6">
        <h2 class="text-xl font-bold">Create New Template</h2>
        <p class="text-gray-500 text-sm">Create a new {{ str_replace('_', ' ', $type) }} template for {{ $tournament->name }}</p>
    </div>

    <form action="{{ route('admin.tournaments.templates.store', $tournament) }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Column: Form Fields -->
            <div class="space-y-6">
                <!-- Template Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Template Name *
                    </label>
                    <input type="text"
                           name="name"
                           id="name"
                           value="{{ old('name') }}"
                           required
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                           placeholder="e.g., Default Welcome Card">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Template Type -->
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Template Type *
                    </label>
                    <select name="type"
                            id="type"
                            required
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        @foreach(\App\Models\TournamentTemplate::TYPES as $templateType)
                            <option value="{{ $templateType }}" {{ $type === $templateType ? 'selected' : '' }}>
                                {{ ucwords(str_replace('_', ' ', $templateType)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Background Image -->
                <div>
                    <label for="background_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Background Image
                    </label>
                    <input type="file"
                           name="background_image"
                           id="background_image"
                           accept="image/*"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                           onchange="previewImage(this)">
                    <p class="mt-1 text-xs text-gray-500">Recommended: 1080x1080 or 1080x1350 pixels. Max 5MB.</p>
                    @error('background_image')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Set as Default -->
                <div class="flex items-center">
                    <input type="checkbox"
                           name="is_default"
                           id="is_default"
                           value="1"
                           {{ old('is_default') ? 'checked' : '' }}
                           class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    <label for="is_default" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                        Set as default template for this type
                    </label>
                </div>

                <!-- Available Placeholders -->
                <div>
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Available Placeholders
                    </h4>
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 max-h-48 overflow-y-auto">
                        <div class="flex flex-wrap gap-2">
                            @foreach($placeholders as $placeholder)
                                <span class="inline-flex items-center px-2 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs rounded cursor-pointer hover:bg-gray-300"
                                      onclick="copyPlaceholder('{{ $placeholder }}')"
                                      title="Click to copy">
                                    {{ '{{' . $placeholder . '}}' }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Click on a placeholder to copy it.</p>
                </div>
            </div>

            <!-- Right Column: Preview -->
            <div>
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Background Preview
                </h4>
                <div id="preview-container" class="aspect-square bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden flex items-center justify-center">
                    <div id="preview-placeholder" class="text-center text-gray-400">
                        <svg class="w-16 h-16 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p>Upload an image to preview</p>
                    </div>
                    <img id="preview-image" src="" alt="Preview" class="w-full h-full object-cover hidden">
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="mt-6 flex justify-end space-x-3">
            <a href="{{ route('admin.tournaments.templates.index', $tournament) }}"
               class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                Cancel
            </a>
            <button type="submit" class="btn-primary">
                Create Template
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview-image').src = e.target.result;
            document.getElementById('preview-image').classList.remove('hidden');
            document.getElementById('preview-placeholder').classList.add('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function copyPlaceholder(placeholder) {
    const text = '{{' + placeholder + '}}';
    navigator.clipboard.writeText(text).then(() => {
        // Show a toast or notification
        alert('Copied: ' + text);
    });
}
</script>
@endpush
@endsection
