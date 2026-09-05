@php
    $blog = app(\App\Services\Blog\BlogSettings::class);
    $isSuperadmin = auth()->user()?->hasRole('Superadmin');
    $inputClass = 'dark:bg-dark-900 w-full rounded-md border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-700 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30';
@endphp

<div class="rounded-md border border-gray-200 dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="px-5 py-4 sm:px-6 sm:py-5">
        <h3 class="text-base font-medium text-gray-700 dark:text-white/90">{{ __('Blog') }}</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Layout and advertising for') }} <a href="{{ route('public.blog.index') }}" target="_blank" class="text-blue-500 hover:underline">{{ route('public.blog.index') }}</a>.
            {{ __('Any post can override these individually from its own editor.') }}
        </p>
    </div>

    <div class="space-y-6 border-t border-gray-100 p-5 sm:p-6 dark:border-gray-800">

        {{-- Layout --}}
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Sidebar') }}</label>
            <select name="blog_sidebar_position" class="{{ $inputClass }}">
                @foreach(\App\Services\Blog\BlogSettings::SIDEBAR_POSITIONS as $value => $label)
                    <option value="{{ $value }}" @selected(get_setting('blog_sidebar_position') === $value || (! get_setting('blog_sidebar_position') && $value === 'right'))>{{ __($label) }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-[11px] text-gray-400">{{ __('Articles stay a comfortable reading width either way; the sidebar sits beside them on desktop and below them on a phone.') }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Sidebar heading') }}</label>
                <input type="text" name="blog_sidebar_heading" value="{{ get_setting('blog_sidebar_heading') }}"
                       placeholder="{{ __('Latest posts') }}" class="{{ $inputClass }}">
            </div>
            <div class="flex items-end pb-1">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="blog_sidebar_recent" value="0">
                    <input type="checkbox" name="blog_sidebar_recent" value="1"
                           @checked(get_setting('blog_sidebar_recent') === null || get_setting('blog_sidebar_recent'))
                           class="rounded border-gray-300 text-brand-500">
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Show recent posts in the sidebar') }}</span>
                </label>
            </div>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('About text') }} <span class="text-gray-400 font-normal">— {{ __('optional, shown in the sidebar') }}</span></label>
            <textarea name="blog_sidebar_about" rows="3" class="{{ $inputClass }}"
                      placeholder="{{ __('A sentence or two about this blog.') }}">{{ get_setting('blog_sidebar_about') }}</textarea>
        </div>

        {{-- Advertising --}}
        <div class="border-t border-gray-100 dark:border-gray-800 pt-6">
            <label class="flex items-center gap-2 cursor-pointer mb-4">
                <input type="hidden" name="blog_ads_enabled" value="0">
                <input type="checkbox" name="blog_ads_enabled" value="1" @checked(get_setting('blog_ads_enabled'))
                       class="rounded border-gray-300 text-brand-500">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Show advertising on blog pages') }}</span>
            </label>

            @if($isSuperadmin)
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    {{ __('Paste the code your ad network gives you. It is rendered as-is, so only paste code you trust.') }}
                </p>

                @foreach(\App\Services\Blog\BlogSettings::AD_SLOTS as $slot => $label)
                    <div class="mb-4">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __($label) }}</label>
                        <textarea name="blog_ad_{{ $slot }}" rows="3" spellcheck="false"
                                  class="{{ $inputClass }} font-mono text-xs"
                                  placeholder="&lt;script&gt;…&lt;/script&gt; or &lt;a&gt;&lt;img&gt;…"
                        >{{ get_setting('blog_ad_' . $slot) }}</textarea>
                    </div>
                @endforeach
            @else
                {{-- Arbitrary HTML on a public page is script on every visitor's browser, which is
                     a narrower thing than "may edit settings". The switch above stays available;
                     the code behind it does not. --}}
                <p class="text-sm rounded-lg bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 p-3">
                    {{ __('Only a Super Admin can edit the ad code itself, because it runs on every visitor\'s browser. You can still switch advertising on and off here.') }}
                </p>
            @endif
        </div>
    </div>
</div>
