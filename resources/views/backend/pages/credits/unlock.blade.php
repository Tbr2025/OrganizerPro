@extends('backend.layouts.app')

@section('title')
    {{ __('Unlock :feature', ['feature' => $feature->name]) }} | {{ config('app.name') }}
@endsection

@section('admin-content')
{{-- Shown before anything is spent: what it costs, what is left after. --}}
<div class="p-4 mx-auto sm:p-6 max-w-xl">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 space-y-4">
        <div>
            <p class="text-sm text-gray-500">{{ $tournament->name }}</p>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $feature->name }}</h2>
            <p class="text-sm text-gray-500 mt-1">{{ $feature->description }}</p>
        </div>

        <dl class="grid grid-cols-2 gap-y-2 text-sm">
            <dt class="text-gray-500">{{ __('Price') }}</dt>
            <dd class="text-right font-semibold">
                {{ $credits->format($quote['cost']) }}
                @if($quote['saved'])<span class="block text-xs text-green-600 font-normal">{{ __('was :base, :name applied', ['base' => $quote['base'], 'name' => $quote['discount']?->name]) }}</span>@endif
            </dd>
            <dt class="text-gray-500">{{ __('Your balance') }}</dt>
            <dd class="text-right">{{ $credits->format($balance) }}</dd>
            <dt class="text-gray-500">{{ __('After') }}</dt>
            <dd class="text-right {{ $enough ? '' : 'text-red-600 font-semibold' }}">{{ $credits->format($balance - $quote['cost']) }}</dd>
        </dl>

        @if($enough)
            <form method="POST" action="{{ route('admin.credits.unlock.store') }}" class="flex gap-2 justify-end">
                @csrf
                <input type="hidden" name="feature" value="{{ $feature->key }}">
                <input type="hidden" name="tournament" value="{{ $tournament->id }}">
                <input type="hidden" name="return" value="{{ $return }}">
                @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                <a href="{{ $return }}" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm">{{ __('Cancel') }}</a>
                <button class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
                    {{ __('Unlock for :price', ['price' => $credits->format($quote['cost'])]) }}
                </button>
            </form>
        @else
            <p class="text-sm text-red-600">{{ __('Not enough :term. You need :n more.', ['term' => $credits->term(), 'n' => $quote['cost'] - $balance]) }}</p>
            <div class="flex gap-2 justify-end">
                <a href="{{ $return }}" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm">{{ __('Back') }}</a>
                <a href="{{ route('admin.credits.index') }}" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold">{{ __('Top up') }}</a>
            </div>
        @endif
    </div>
</div>
@endsection
