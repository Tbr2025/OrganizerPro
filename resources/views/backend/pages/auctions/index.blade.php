@extends('backend.layouts.app')

@section('title', 'Auctions | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-7xl md:p-6">
        {{-- Breadcrumbs --}}
        <x-breadcrumbs :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Auctions']]" />

        <div class="space-y-6">

            @php
                $auctionLocked = false;
                if (!auth()->user()->hasRole('Superadmin') && auth()->user()->organization_id) {
                    $userOrg = \App\Models\Organization::find(auth()->user()->organization_id);
                    $auctionLocked = $userOrg && !$userOrg->isAuctionEnabled();
                }
            @endphp

            @if($auctionLocked)
                <div class="relative rounded-xl overflow-hidden">
                    {{-- Lock Overlay --}}
                    <div class="absolute inset-0 z-10 backdrop-blur-sm bg-white/60 dark:bg-gray-900/60 flex flex-col items-center justify-center rounded-xl">
                        <iconify-icon icon="lucide:lock" class="text-5xl text-gray-400 dark:text-gray-500 mb-3"></iconify-icon>
                        <p class="text-lg font-semibold text-gray-600 dark:text-gray-300">Auctions Not Available</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Your package does not include auction features.</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Contact your administrator to upgrade.</p>
                    </div>
                    {{-- Blurred Content --}}
                    <div class="pointer-events-none select-none filter blur-[2px] opacity-50">
            @endif

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                {{-- Toolbar --}}
                <div class="px-5 py-4 sm:px-6 flex flex-col md:flex-row justify-between items-center gap-3">
                    @include('backend.partials.search-form', [
                        'placeholder' => 'Search auctions...',
                    ])
                    <div class="flex items-center gap-2">
                        @if (!auth()->user()->hasRole('Team Manager') || auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Admin'))
                            <a href="{{ route('admin.auctions.create') }}" class="btn-primary flex items-center gap-1.5 text-sm">
                                <iconify-icon icon="lucide:plus" width="16"></iconify-icon>
                                Create Auction
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Table --}}
                <div class="overflow-x-auto overflow-y-visible">
                    <table class="w-full">
                        <thead>
                            <tr class="border-t border-b border-gray-100 dark:border-gray-800">
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-left">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">#</span>
                                </th>
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-left">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Name</span>
                                </th>
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-left">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Organization</span>
                                </th>
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-left">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tournament</span>
                                </th>
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-left">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</span>
                                </th>
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-left">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Start Date</span>
                                </th>
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-right">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($auctions as $auction)
                                <tr class="group hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors duration-150">
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $loop->iteration + ($auctions->currentPage() - 1) * $auctions->perPage() }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $auction->name }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ $auction->organization->name ?? 'N/A' }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ $auction->tournament->name ?? 'N/A' }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        @php
                                            $statusColors = [
                                                'scheduled' => 'bg-blue-50 text-blue-700 ring-blue-600/10 dark:bg-blue-500/10 dark:text-blue-400',
                                                'running' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/10 dark:bg-emerald-500/10 dark:text-emerald-400',
                                                'paused' => 'bg-amber-50 text-amber-700 ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-400',
                                                'completed' => 'bg-gray-50 text-gray-600 ring-gray-500/10 dark:bg-gray-500/10 dark:text-gray-400',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-md ring-1 ring-inset {{ $statusColors[$auction->status] ?? 'bg-gray-50 text-gray-600 ring-gray-500/10' }}">
                                            {{ ucfirst($auction->status) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ $auction->start_at ? $auction->start_at->format('Y-m-d') : '-' }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-right">
                                        <x-buttons.action-buttons :label="__('Actions')" :show-label="false" align="right">
                                            <x-buttons.action-item :href="route('admin.auctions.show', $auction)" icon="lucide:eye"
                                                :label="__('View')" />

                                            @if (!auth()->user()->hasRole('Team Manager') || auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Admin'))
                                                <x-buttons.action-item :href="route('admin.auctions.edit', $auction)" icon="lucide:pencil"
                                                    :label="__('Edit')" />

                                                {{-- Manage / Organizer Panel link --}}
                                                @if (in_array($auction->status, ['running', 'paused', 'scheduled']))
                                                    <x-buttons.action-item :href="route('admin.auction.organizer.panel', $auction)" icon="lucide:settings"
                                                        :label="__('Organizer Panel')" class="text-emerald-600 dark:text-emerald-400" />
                                                @endif

                                                {{-- Public LED Wall Display --}}
                                                <x-buttons.action-item :href="route('public.auction.live', $auction)" icon="lucide:monitor-play"
                                                    :label="__('Live Display')" class="text-purple-600 dark:text-purple-400" />

                                                <div x-data="{ deleteModalOpen: false }">
                                                    <x-buttons.action-item type="modal-trigger"
                                                        modal-target="deleteModalOpen" icon="lucide:trash-2"
                                                        :label="__('Delete')" class="text-red-600 dark:text-red-400" />

                                                    <x-modals.confirm-delete id="delete-modal-{{ $auction->id }}"
                                                        title="Delete Auction"
                                                        content="Are you sure you want to delete this auction?"
                                                        formId="delete-form-{{ $auction->id }}"
                                                        formAction="{{ route('admin.auctions.destroy', $auction) }}"
                                                        modalTrigger="deleteModalOpen"
                                                        cancelButtonText="No, cancel"
                                                        confirmButtonText="Yes, Confirm" />
                                                </div>
                                            @endif
                                        </x-buttons.action-buttons>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-12">
                                        <div class="flex flex-col items-center gap-2">
                                            <iconify-icon icon="lucide:gavel" width="32" class="text-gray-300 dark:text-gray-600"></iconify-icon>
                                            <p class="text-sm text-gray-400 dark:text-gray-500">No auctions found</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($auctions->hasPages())
                    <div class="border-t border-gray-100 dark:border-gray-800 px-5 py-4 sm:px-6">
                        {{ $auctions->links() }}
                    </div>
                @endif
            </div>

            @if($auctionLocked)
                    </div>
                </div>
            @endif

        </div>
    </div>
@endsection
