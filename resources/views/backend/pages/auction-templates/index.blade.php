@extends('backend.layouts.app')

@section('title', 'Auction Templates | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Auction Templates</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Configure LED wall display templates for auctions</p>
        </div>
        <a href="{{ route('admin.auction-templates.create') }}" class="btn btn-primary inline-flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            New Template
        </a>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Templates Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($templates as $template)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                {{-- Preview Image --}}
                <div class="relative h-48 bg-gray-900 flex items-center justify-center">
                    @if($template->background_image)
                        <img src="{{ asset('storage/' . $template->background_image) }}"
                             alt="{{ $template->name }}"
                             class="w-full h-full object-cover opacity-80">
                    @else
                        <div class="text-gray-500 text-center">
                            <svg class="w-16 h-16 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-sm">No background</p>
                        </div>
                    @endif

                    {{-- Badges --}}
                    <div class="absolute top-3 left-3 flex gap-2">
                        @if($template->is_default)
                            <span class="px-2 py-1 bg-green-500 text-white text-xs font-bold rounded">DEFAULT</span>
                        @endif
                        <span class="px-2 py-1 bg-blue-500 text-white text-xs font-bold rounded uppercase">
                            {{ str_replace('_', ' ', $template->type) }}
                        </span>
                    </div>

                    @if(!$template->is_active)
                        <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                            <span class="px-3 py-1 bg-red-500 text-white text-sm font-bold rounded">INACTIVE</span>
                        </div>
                    @endif
                </div>

                {{-- Content --}}
                <div class="p-4">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-1">{{ $template->name }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                        {{ $template->canvas_width }} x {{ $template->canvas_height }}px
                        @if($template->auction)
                            <span class="text-purple-500">| {{ $template->auction->name }}</span>
                        @endif
                    </p>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.auction-templates.edit', $template) }}"
                           class="flex-1 btn btn-sm btn-secondary text-center">
                            Edit
                        </a>
                        <a href="{{ route('admin.auction-templates.preview', $template) }}"
                           class="btn btn-sm btn-outline" target="_blank">
                            Preview
                        </a>
                        @if(!$template->is_default)
                            <form action="{{ route('admin.auction-templates.set-default', $template) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success" title="Set as Default">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            </form>
                        @endif
                        <form action="{{ route('admin.auction-templates.destroy', $template) }}" method="POST"
                              onsubmit="return confirm('Delete this template?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full p-12 text-center bg-white dark:bg-gray-800 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600">
                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No templates yet</h3>
                <p class="text-gray-500 dark:text-gray-400 mb-4">Create your first auction display template</p>
                <a href="{{ route('admin.auction-templates.create') }}" class="btn btn-primary">
                    Create Template
                </a>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-8">
        {{ $templates->links() }}
    </div>
</div>
@endsection
