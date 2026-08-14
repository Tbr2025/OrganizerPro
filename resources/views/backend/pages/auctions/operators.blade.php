@extends('backend.layouts.app')

@section('title', 'Who runs this auction · ' . $auction->name . ' | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-4xl md:p-6">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Who runs this auction</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                People at the desk — an auctioneer, a compere, an assistant. Each one is added to
                <strong>this</strong> auction only.
            </p>
        </div>
        <a href="{{ route('admin.auctions.show', $auction) }}"
           class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
            Back to auction
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    {{-- Said once, plainly: this is a narrowing, not a grant of everything. --}}
    <div class="mb-5 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-4 py-3 text-xs text-blue-800 dark:text-blue-300">
        Organizers and admins already run every auction in their organization and do not need to be
        listed here. Anyone else can open <em>only</em> the auctions they appear on, and only do the
        things ticked against their name.
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 mb-6">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Add someone</h2>

        <form action="{{ route('admin.auctions.operators.store', $auction) }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Person</label>
                {{-- Players and team managers are not offered: a player has an account to complete
                     their own registration, and a manager has a side. Neither should be one click
                     from the controls of the auction they are in. --}}
                <select name="user_id" required class="form-control text-sm max-w-md">
                    <option value="">Choose a user&hellip;</option>
                    @foreach($candidates as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>
                    @endforeach
                </select>
                @if($candidates->isEmpty())
                    <p class="text-xs text-amber-600 mt-1">
                        No eligible users yet. Create one under Users first — players and team
                        managers are deliberately not offered here.
                    </p>
                @endif
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-2">What they may do</label>
                <div class="space-y-2">
                    @foreach($abilities as $key => $label)
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" name="abilities[]" value="{{ $key }}"
                                   @checked($key === 'observe')
                                   class="mt-0.5 rounded border-gray-300 text-indigo-600">
                            <span class="text-sm text-gray-700 dark:text-gray-200">
                                <span class="font-semibold">{{ ucfirst($key) }}</span>
                                <span class="text-gray-500 dark:text-gray-400">— {{ $label }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-2">
                    Selling is separate from taking bids on purpose: the person calling the lots
                    usually should not also be the one ending them.
                </p>
            </div>

            <button class="px-4 py-2 rounded-lg bg-brand-500 text-white text-sm font-semibold hover:bg-brand-600">
                Add to this auction
            </button>
        </form>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 text-sm font-semibold text-gray-900 dark:text-white">
            On this auction
        </div>

        @forelse($operators as $operator)
            <div class="flex items-center gap-4 px-5 py-3 border-b border-gray-100 dark:border-gray-700 last:border-0 flex-wrap">
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                        {{ $operator->user?->name ?? 'Deleted user' }}
                    </div>
                    <div class="text-xs text-gray-500 truncate">{{ $operator->user?->email }}</div>
                </div>

                <div class="flex flex-wrap gap-1.5">
                    @foreach($operator->abilities ?? [] as $ability)
                        <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">
                            {{ $ability }}
                        </span>
                    @endforeach
                </div>

                <form action="{{ route('admin.auctions.operators.destroy', [$auction, $operator]) }}" method="POST"
                      onsubmit="return confirm('Remove {{ $operator->user?->name }} from this auction?')">
                    @csrf @method('DELETE')
                    <button class="px-3 py-1.5 rounded-lg border border-red-300 dark:border-red-800 text-red-600 dark:text-red-400 text-xs font-semibold hover:bg-red-50 dark:hover:bg-red-900/20">
                        Remove
                    </button>
                </form>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-gray-500">
                Nobody has been added yet. Only organizers and admins can run this auction.
            </div>
        @endforelse
    </div>
</div>
@endsection
