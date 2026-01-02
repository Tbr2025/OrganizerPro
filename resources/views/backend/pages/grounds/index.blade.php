@extends('backend.layouts.app')

@section('title', 'Grounds | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Grounds / Venues</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Manage match venues and locations.</p>
        </div>
        <button type="button" onclick="openCreateModal()"
                class="btn btn-primary inline-flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Add New Ground
        </button>
    </div>

    {{-- Grounds Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($grounds as $ground)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                {{-- Image --}}
                @if($ground->image)
                    <div class="h-40 bg-gray-200 dark:bg-gray-700">
                        <img src="{{ Storage::url($ground->image) }}" alt="{{ $ground->name }}" class="w-full h-full object-cover">
                    </div>
                @else
                    <div class="h-40 bg-gradient-to-br from-green-600 to-green-700 flex items-center justify-center">
                        <svg class="w-16 h-16 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                @endif

                {{-- Content --}}
                <div class="p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 dark:text-white">{{ $ground->name }}</h3>
                            @if($ground->city)
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $ground->city }}</p>
                            @endif
                        </div>
                        <span class="px-2 py-1 text-xs font-medium rounded-full {{ $ground->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                            {{ $ground->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    @if($ground->address)
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 truncate">{{ $ground->address }}</p>
                    @endif

                    {{-- Actions --}}
                    <div class="mt-4 flex items-center gap-2">
                        @if($ground->google_maps_link)
                            <a href="{{ $ground->google_maps_link }}" target="_blank"
                               class="text-blue-600 hover:text-blue-800 text-sm">
                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                Maps
                            </a>
                        @endif
                        <button type="button" onclick="openEditModal({{ $ground->id }})"
                                class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white text-sm ml-auto">
                            Edit
                        </button>
                        <form action="{{ route('admin.grounds.destroy', $ground) }}" method="POST" class="inline"
                              onsubmit="return confirm('Are you sure you want to delete this ground?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full p-8 text-center bg-white dark:bg-gray-800 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No grounds added</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add venues where matches will be played.</p>
                <div class="mt-6">
                    <button type="button" onclick="openCreateModal()" class="btn btn-primary">
                        Add Ground
                    </button>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if(method_exists($grounds, 'links'))
        <div class="mt-8">
            {{ $grounds->links() }}
        </div>
    @endif
</div>

{{-- Create/Edit Modal --}}
<div id="groundModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
        <form id="groundForm" method="POST" enctype="multipart/form-data">
            @csrf
            <div id="methodField"></div>

            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Add Ground</h3>
            </div>

            <div class="p-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name *</label>
                    <input type="text" name="name" id="groundName" required class="form-control">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City</label>
                    <input type="text" name="city" id="groundCity" class="form-control">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                    <textarea name="address" id="groundAddress" rows="2" class="form-control"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Google Maps Link</label>
                    <input type="url" name="google_maps_link" id="groundMapsLink" class="form-control" placeholder="https://maps.google.com/...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Image</label>
                    <input type="file" name="image" accept="image/*" class="form-control">
                </div>
                <div class="flex items-center">
                    <input type="checkbox" name="is_active" id="groundActive" value="1" checked class="w-4 h-4 text-blue-600 rounded">
                    <label for="groundActive" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</label>
                </div>
            </div>

            <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-2">
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    const grounds = @json($grounds->keyBy('id'));

    function openCreateModal() {
        document.getElementById('modalTitle').textContent = 'Add Ground';
        document.getElementById('groundForm').action = '{{ route("admin.grounds.store") }}';
        document.getElementById('methodField').innerHTML = '';
        document.getElementById('groundName').value = '';
        document.getElementById('groundCity').value = '';
        document.getElementById('groundAddress').value = '';
        document.getElementById('groundMapsLink').value = '';
        document.getElementById('groundActive').checked = true;
        document.getElementById('groundModal').classList.remove('hidden');
        document.getElementById('groundModal').classList.add('flex');
    }

    function openEditModal(id) {
        const ground = grounds[id];
        if (!ground) return;

        document.getElementById('modalTitle').textContent = 'Edit Ground';
        document.getElementById('groundForm').action = `/admin/grounds/${id}`;
        document.getElementById('methodField').innerHTML = '<input type="hidden" name="_method" value="PUT">';
        document.getElementById('groundName').value = ground.name || '';
        document.getElementById('groundCity').value = ground.city || '';
        document.getElementById('groundAddress').value = ground.address || '';
        document.getElementById('groundMapsLink').value = ground.google_maps_link || '';
        document.getElementById('groundActive').checked = ground.is_active;
        document.getElementById('groundModal').classList.remove('hidden');
        document.getElementById('groundModal').classList.add('flex');
    }

    function closeModal() {
        document.getElementById('groundModal').classList.add('hidden');
        document.getElementById('groundModal').classList.remove('flex');
    }

    document.getElementById('groundModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
</script>
@endpush
@endsection
