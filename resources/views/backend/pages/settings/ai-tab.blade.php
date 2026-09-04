@php
    $ai = app(\App\Services\Blog\BlogGenerationService::class);
    $aiSettings = app(\App\Services\Blog\AiSettings::class);
    $pdf = app(\App\Services\Blog\MatchReportPdfService::class);

    $models = $ai->models();
    $activeModel = $ai->model();
    $spend = \App\Models\MatchReport::spendSummary();
    $endpointHost = parse_url($aiSettings->baseUrl(), PHP_URL_HOST) ?: 'not set';
@endphp

<div class="rounded-md border border-gray-200 dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="px-5 py-4 sm:px-6 sm:py-5">
        <h3 class="text-base font-medium text-gray-700 dark:text-white/90">{{ __('AI & Blog') }}</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Used to draft match blog posts from a CricHeroes report. Super Admin only.') }}
        </p>
    </div>

    <div class="space-y-6 border-t border-gray-100 p-5 sm:p-6 dark:border-gray-800">

        {{-- Status --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                <p class="text-xs text-gray-500">{{ __('API key') }}</p>
                @if($aiSettings->hasKey())
                    <p class="text-sm font-semibold text-green-600">{{ $aiSettings->maskedKey() }}</p>
                    <p class="text-[11px] text-gray-400">{{ $aiSettings->keySource() === 'dashboard' ? __('Saved here') : __('From .env') }}</p>
                @else
                    <p class="text-sm font-semibold text-amber-600">{{ __('Not set') }}</p>
                @endif
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                <p class="text-xs text-gray-500">{{ __('Endpoint') }}</p>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 font-mono truncate">{{ $endpointHost }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                <p class="text-xs text-gray-500">{{ __('PDF reader') }}</p>
                <p class="text-sm font-semibold {{ $pdf->isAvailable() ? 'text-green-600' : 'text-amber-600' }}">
                    {{ $pdf->isAvailable() ? __('pdftotext ready') : __('pdftotext missing') }}
                </p>
            </div>
        </div>

        {{-- Key --}}
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('API Key') }}</label>
            {{-- Rendered empty on purpose: a saved key is never sent back to the browser, so it
                 cannot be read out of the page source. Leaving this blank keeps the current key. --}}
            <input type="password" name="openai_api_key" autocomplete="new-password" spellcheck="false"
                   placeholder="{{ $aiSettings->hasKey() ? __('Saved — leave blank to keep it') : 'sk-...' }}"
                   class="dark:bg-dark-900 w-full rounded-md border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-700 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-300">
                {{ __('Stored encrypted in the database, never shown again. Get one at') }}
                <a href="https://platform.openai.com/api-keys" target="_blank" class="text-blue-500 hover:underline">platform.openai.com/api-keys</a>.
            </p>
        </div>

        {{-- Endpoint --}}
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('API Base URL') }}</label>
            <input type="text" name="openai_base_url" value="{{ get_setting(\App\Services\Blog\AiSettings::BASE_URL) }}"
                   placeholder="https://api.openai.com/v1" spellcheck="false"
                   class="dark:bg-dark-900 w-full rounded-md border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-700 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-300">
                {{ __('Leave blank for OpenAI. Any OpenAI-compatible provider works — the free ones are:') }}
            </p>
            <ul class="mt-1 text-sm text-gray-500 dark:text-gray-400 list-disc ms-5 space-y-0.5">
                <li><span class="font-mono text-xs">https://api.groq.com/openai/v1</span> — {{ __('Groq, free, ~1,000 requests/day') }}</li>
                <li><span class="font-mono text-xs">https://generativelanguage.googleapis.com/v1beta/openai/</span> — {{ __('Gemini, free tier is ~20 requests/day') }}</li>
            </ul>
            <p class="mt-1 text-sm text-amber-600 dark:text-amber-400">
                {{ __('Change the model below to match the provider — a model from one provider on another provider\'s URL returns a 404.') }}
            </p>
        </div>

        {{-- Model --}}
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Model') }}</label>
            <select name="{{ \App\Services\Blog\BlogGenerationService::MODEL_SETTING }}"
                    class="dark:bg-dark-900 w-full rounded-md border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-700 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                @foreach($models as $modelId => $rates)
                    <option value="{{ $modelId }}" @selected($modelId === $activeModel)>
                        {{ $rates['label'] }} — @if(($rates['input'] ?? 0) == 0 && ($rates['output'] ?? 0) == 0) {{ __('free') }} @else ~${{ number_format((float) $ai->estimatedCost($modelId), 4) }}/post @endif
                    </option>
                @endforeach
            </select>
            @if(isset($models[$activeModel]))
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-300">{{ $models[$activeModel]['note'] }}</p>
            @endif
        </div>

        {{-- Spend --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('Spend') }}</p>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        @if($spend['count'] > 0) ${{ number_format($spend['average'], 4) }} @else ~${{ number_format((float) $ai->estimatedCost($activeModel), 4) }} @endif
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
            @php $per = $spend['count'] > 0 ? $spend['average'] : $ai->estimatedCost($activeModel); @endphp
            @if($per > 0)
                <p class="mt-3 text-center text-[11px] text-gray-400">
                    {{ __('At this rate $5 of credit is about') }} {{ number_format(5 / $per) }} {{ __('posts. List prices — an estimate, not a bill.') }}
                </p>
            @endif
        </div>
    </div>
</div>
