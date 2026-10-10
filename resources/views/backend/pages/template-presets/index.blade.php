@extends('backend.layouts.app')

@section('title')
    {{ __('Ready-made designs') }} | {{ config('app.name') }}
@endsection

@section('admin-content')
<div class="p-4 mx-auto sm:p-6 max-w-7xl space-y-6">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('Every design is available in :n themes. Switch a design off to hide it, or make it premium with a price in :term (charged once per tournament).', ['n' => count($themes), 'term' => $credits->term()]) }}
    </p>

    @foreach($presets as $type => $list)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-3">{{ $types[$type] ?? \Illuminate\Support\Str::headline($type) }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($list as $p)
                    <form method="POST" action="{{ route('admin.template-presets.update', $p['key']) }}" class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden text-sm {{ $p['is_active'] ? '' : 'opacity-60' }}">
                        @csrf
                        <img src="{{ route('admin.presets.thumb', ['key' => $p['key'], 'theme' => $p['themed'] ? 'midnight' : 'own']) }}" alt="" loading="lazy" class="w-full bg-gray-100 dark:bg-gray-800" style="aspect-ratio:4/5;object-fit:contain">
                        <div class="p-3 space-y-2">
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $p['name'] }}</p>
                                <p class="text-xs text-gray-500">{{ $p['description'] }}</p>
                                <p class="text-[11px] text-gray-400 mt-1">{{ $p['canvas_width'] }} × {{ $p['canvas_height'] }} · {{ $p['themed'] ? __('themed') : __('own colours') }} · v{{ $p['version'] }}</p>
                            </div>
                            <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($p['is_active'])> {{ __('Available') }}</label>
                            <div class="flex items-center gap-2">
                                <label class="flex items-center gap-2"><input type="checkbox" name="is_premium" value="1" @checked($p['is_premium'])> {{ __('Premium') }}</label>
                                <input type="number" name="credit_cost" min="0" value="{{ $p['credit_cost'] }}" class="form-control w-20 text-xs" title="{{ $credits->term() }}">
                                <input type="number" name="sort" min="0" value="{{ $p['sort'] }}" class="form-control w-16 text-xs" title="{{ __('Order') }}">
                            </div>
                            <button class="w-full px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600">{{ __('Save') }}</button>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@endsection
