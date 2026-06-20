@extends('backend.layouts.app')

@section('title', __('Font Manager'))

@section('admin-content')
@php($fontService = app(\App\Services\Fonts\FontService::class))

{{-- Inject installed fonts so previews on this page use the real font files --}}
<style>{!! $fontService->fontFaceCss() !!}</style>

<div class="p-4 lg:p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Font Manager') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Add Google Fonts or upload custom fonts. Installed fonts appear in the template editor and render identically in generated posters.') }}</p>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-green-50 border border-green-200 text-green-800 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300 px-4 py-3 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="rounded-lg bg-red-50 border border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300 px-4 py-3 text-sm">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Add Google Font --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h2 class="font-semibold text-gray-800 dark:text-white mb-1">{{ __('Add a Google Font') }}</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">{{ __('Type the exact Google Fonts family name (e.g. Lato, Teko, Rubik).') }}</p>
            <form method="POST" action="{{ route('admin.fonts.google') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Family name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="family" value="{{ old('family') }}" required placeholder="e.g. Rubik" class="form-control mt-1">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Weights') }} <span class="text-red-500">*</span></label>
                    <div class="flex flex-wrap gap-3">
                        @foreach ([300 => 'Light', 400 => 'Regular', 500 => 'Medium', 600 => 'SemiBold', 700 => 'Bold', 800 => 'ExtraBold', 900 => 'Black'] as $w => $lbl)
                            <label class="inline-flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" name="weights[]" value="{{ $w }}" @checked(in_array($w, old('weights', [400, 700]))) class="form-checkbox rounded">
                                {{ $w }} <span class="text-gray-400">{{ $lbl }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="include_italic" value="1" @checked(old('include_italic')) class="form-checkbox rounded">
                    {{ __('Also download italic styles') }}
                </label>
                <div>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                        <iconify-icon icon="lucide:download" width="16"></iconify-icon>
                        {{ __('Install Google Font') }}
                    </button>
                </div>
            </form>
        </div>

        {{-- Upload Custom Font --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h2 class="font-semibold text-gray-800 dark:text-white mb-1">{{ __('Upload a Custom Font') }}</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">{{ __('Upload a .ttf or .otf file. Add one variant at a time (e.g. Bold).') }}</p>
            <form method="POST" action="{{ route('admin.fonts.custom') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Family name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="family" value="{{ old('family') }}" required placeholder="e.g. My Brand Font" class="form-control mt-1">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Weight') }}</label>
                        <select name="weight" class="form-control mt-1">
                            @foreach ([300 => 'Light', 400 => 'Regular', 500 => 'Medium', 600 => 'SemiBold', 700 => 'Bold', 800 => 'ExtraBold', 900 => 'Black'] as $w => $lbl)
                                <option value="{{ $w }}" @selected(old('weight', 400) == $w)>{{ $w }} — {{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Style') }}</label>
                        <select name="style" class="form-control mt-1">
                            <option value="normal" @selected(old('style') === 'normal')>{{ __('Normal') }}</option>
                            <option value="italic" @selected(old('style') === 'italic')>{{ __('Italic') }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Font file (.ttf / .otf)') }} <span class="text-red-500">*</span></label>
                    <input type="file" name="font_file" accept=".ttf,.otf" required class="form-control mt-1">
                </div>
                <div>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                        <iconify-icon icon="lucide:upload" width="16"></iconify-icon>
                        {{ __('Upload Font') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Installed fonts --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-semibold text-gray-800 dark:text-white">{{ __('Installed Fonts') }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/40 text-gray-500 dark:text-gray-400 text-left">
                    <tr>
                        <th class="px-5 py-3 font-medium">{{ __('Font') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('Preview') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('Source') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('Variants') }}</th>
                        <th class="px-5 py-3 font-medium text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($fonts as $font)
                        <tr>
                            <td class="px-5 py-3 font-medium text-gray-800 dark:text-white">{{ $font->name }}</td>
                            <td class="px-5 py-3 text-gray-800 dark:text-gray-200" style="font-family: '{{ $font->name }}', sans-serif; font-size: 20px;">
                                Aa Bb Cc 123
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $font->source === 'google' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300' }}">
                                    {{ ucfirst($font->source) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-400">
                                {{ collect($font->variants ?? [])->map(fn($v) => $v['weight'] . ($v['style'] === 'italic' ? 'i' : ''))->implode(', ') }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                <form method="POST" action="{{ route('admin.fonts.destroy', $font) }}" onsubmit="return confirm('{{ __('Remove this font? Templates using it will fall back to a default font.') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 dark:border-red-800 px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                                        <iconify-icon icon="lucide:trash-2" width="14"></iconify-icon>
                                        {{ __('Remove') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-gray-400">{{ __('No custom fonts installed yet. Add one above.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
