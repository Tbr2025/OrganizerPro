@php
    $ai = app(\App\Services\Blog\BlogGenerationService::class);
    $aiSettings = app(\App\Services\Blog\AiSettings::class);
    $pdf = app(\App\Services\Blog\MatchReportPdfService::class);

    $providers = $aiSettings->providers();
    $active = $aiSettings->provider();
    $spend = \App\Models\MatchReport::spendSummary();

    $inputClass = 'dark:bg-dark-900 w-full rounded-md border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-700 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30';
@endphp

<div class="rounded-md border border-gray-200 dark:border-gray-800 dark:bg-white/[0.03]"
     x-data="{ provider: '{{ $active }}', loading: false, loaded: {}, error: '' }">
    <div class="px-5 py-4 sm:px-6 sm:py-5">
        <h3 class="text-base font-medium text-gray-700 dark:text-white/90">{{ __('AI & Blog') }}</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Drafts match blog posts from a CricHeroes report. Each provider keeps its own key, URL and model, so switching between them loses nothing.') }}
        </p>
    </div>

    <div class="space-y-6 border-t border-gray-100 p-5 sm:p-6 dark:border-gray-800">

        {{-- Status --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                <p class="text-xs text-gray-500">{{ __('Active provider') }}</p>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $providers[$active]['label'] ?? $active }}</p>
                <p class="text-[11px] {{ $aiSettings->hasKey() ? 'text-green-600' : 'text-amber-600' }}">
                    {{ $aiSettings->hasKey() ? $aiSettings->maskedKey() . ' (' . ($aiSettings->keySource() === 'env' ? __('from .env') : __('saved here')) . ')' : __('no key set') }}
                </p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                <p class="text-xs text-gray-500">{{ __('Model in use') }}</p>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 font-mono truncate">{{ $ai->model() }}</p>
                <p class="text-[11px] text-gray-400">
                    @if($ai->estimatedCost() !== null)
                        @if($ai->estimatedCost() > 0) ~${{ number_format((float) $ai->estimatedCost(), 4) }}/{{ __('post') }} @else {{ __('free') }} @endif
                    @else
                        {{ __('no published price') }}
                    @endif
                </p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                <p class="text-xs text-gray-500">{{ __('PDF reader') }}</p>
                <p class="text-sm font-semibold {{ $pdf->isAvailable() ? 'text-green-600' : 'text-amber-600' }}">
                    {{ $pdf->isAvailable() ? __('pdftotext ready') : __('pdftotext missing') }}
                </p>
            </div>
        </div>

        {{-- Which provider --}}
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Provider') }}</label>
            <select name="ai_provider" x-model="provider" class="{{ $inputClass }}">
                @foreach($providers as $key => $meta)
                    <option value="{{ $key }}">{{ $meta['label'] }}</option>
                @endforeach
            </select>
            @foreach($providers as $key => $meta)
                <p x-show="provider === '{{ $key }}'" x-cloak class="mt-2 text-sm text-gray-500 dark:text-gray-300">
                    {{ $meta['note'] }}
                    @if(! empty($meta['keys_url']))
                        <a href="{{ $meta['keys_url'] }}" target="_blank" class="text-blue-500 hover:underline">{{ __('Get a key') }}</a>
                    @endif
                </p>
            @endforeach
        </div>

        {{-- One block per provider: its own key, URL and model, all saved together. --}}
        @foreach($providers as $key => $meta)
            <div x-show="provider === '{{ $key }}'" x-cloak class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $meta['label'] }}</p>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('API Key') }}</label>
                    {{-- Always rendered empty: a saved key is never sent back to the browser, so
                         it cannot be read out of the page source. Blank keeps what is stored. --}}
                    <input type="password" name="ai_key_{{ $key }}" autocomplete="new-password" spellcheck="false"
                           placeholder="{{ $aiSettings->hasKey($key) ? $aiSettings->maskedKey($key) . ' — ' . __('leave blank to keep') : __('paste key') }}"
                           class="{{ $inputClass }}">
                </div>

                <div x-data="{ url: '{{ get_setting('ai_base_url_' . $key) }}' }">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('API Base URL') }}</label>
                    <input type="text" name="ai_base_url_{{ $key }}" spellcheck="false" x-model="url"
                           placeholder="{{ $meta['base_url'] ?: 'https://...' }}"
                           class="{{ $inputClass }}">
                    <p class="mt-1 text-[11px] text-gray-400">{{ __('Blank uses') }} <span class="font-mono">{{ $meta['base_url'] ?: __('nothing — required for a custom provider') }}</span></p>

                    {{-- The URL that will actually be called, shown as you type.
                         A 404 from a wrong base URL carries no error body to explain itself, so
                         the only way to catch it is to see the assembled address beforehand. --}}
                    <p class="mt-1 text-[11px] text-gray-500">
                        {{ __('Will call:') }}
                        <span class="font-mono" x-text="(url || '{{ $meta['base_url'] }}').replace(/\/+$/, '') + '/chat/completions'"></span>
                    </p>

                    {{-- Gemini publishes two endpoints and the wrong one looks plausible: the
                         native /v1beta/models/{model} path is what most of its documentation
                         shows, and it 404s here because it already names a model. --}}
                    <p x-show="/\/models\//.test(url) || /generateContent/.test(url)" x-cloak
                       class="mt-1 text-sm text-red-600">
                        {{ __('That looks like a native endpoint that already names a model. Use the OpenAI-compatible one:') }}
                        <button type="button" class="font-mono underline"
                                @click="url = '{{ $meta['base_url'] }}'">{{ $meta['base_url'] }}</button>
                    </p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Model') }}</label>
                        {{-- The only reliable answer to "does this model exist for me": ask. --}}
                        <button type="button" class="text-xs font-semibold text-blue-600 hover:underline"
                                :disabled="loading"
                                @click="loading = true; error = '';
                                        fetch('{{ route('admin.ai.models') }}', {headers: {'Accept': 'application/json'}})
                                          .then(r => r.json().then(d => ({ok: r.ok, d})))
                                          .then(({ok, d}) => { ok ? loaded['{{ $key }}'] = d.models : error = d.message; })
                                          .catch(e => error = e.message)
                                          .finally(() => loading = false)">
                            <span x-show="!loading">{{ __('Load available models') }}</span>
                            <span x-show="loading" x-cloak>{{ __('Loading…') }}</span>
                        </button>
                    </div>

                    {{-- Free text, not a select: model ids change constantly and differ by what a
                         key is entitled to, so a whitelist would block working setups. --}}
                    <input type="text" name="ai_model_{{ $key }}" spellcheck="false" list="ai-models-{{ $key }}"
                           value="{{ get_setting('ai_model_' . $key) }}"
                           placeholder="{{ $meta['models'][0] ?? 'model-id' }}"
                           class="{{ $inputClass }}">
                    <datalist id="ai-models-{{ $key }}">
                        @foreach($meta['models'] ?? [] as $suggested)
                            <option value="{{ $suggested }}"></option>
                        @endforeach
                        <template x-for="m in (loaded['{{ $key }}'] || [])" :key="m">
                            <option :value="m"></option>
                        </template>
                    </datalist>

                    <p x-show="error" x-cloak class="mt-1 text-sm text-red-600" x-text="error"></p>

                    <div x-show="loaded['{{ $key }}'] && loaded['{{ $key }}'].length" x-cloak class="mt-2">
                        <p class="text-[11px] text-gray-500 mb-1">
                            <span x-text="(loaded['{{ $key }}'] || []).length"></span> {{ __('models this key can use — click one:') }}
                        </p>
                        <div class="flex flex-wrap gap-1 max-h-32 overflow-y-auto">
                            <template x-for="m in (loaded['{{ $key }}'] || [])" :key="m">
                                <button type="button" x-text="m"
                                        @click="$el.closest('div').parentElement.parentElement.querySelector('input[name=\'ai_model_{{ $key }}\']').value = m"
                                        class="text-[11px] font-mono px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-blue-100"></button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Spend --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('Spend') }}</p>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        @if($spend['count'] > 0) ${{ number_format($spend['average'], 4) }} @elseif($ai->estimatedCost() !== null) ~${{ number_format((float) $ai->estimatedCost(), 4) }} @else — @endif
                    </p>
                    <p class="text-[11px] text-gray-500">{{ $spend['count'] > 0 ? __('Actual average per post') : __('Estimated per post') }}</p>
                </div>
                <div>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $spend['count'] }}</p>
                    <p class="text-[11px] text-gray-500">{{ __('Posts generated') }}</p>
                </div>
                <div>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">${{ number_format($spend['total'], 4) }}</p>
                    <p class="text-[11px] text-gray-500">{{ __('Spent so far') }}</p>
                </div>
            </div>
            @php $per = $spend['count'] > 0 ? $spend['average'] : $ai->estimatedCost(); @endphp
            @if($per > 0)
                <p class="mt-3 text-center text-[11px] text-gray-400">
                    {{ __('At this rate $5 of credit is about') }} {{ number_format(5 / $per) }} {{ __('posts. List prices — an estimate, not a bill.') }}
                </p>
            @endif
        </div>

        <p class="text-[11px] text-gray-400">{{ __('Remember to press Save at the bottom of this page.') }}</p>
    </div>
</div>
