{{--
    Raw HTML authoring pane.

    A monospace <textarea> on purpose — no CodeMirror/Monaco/Ace. Zero new dependencies
    and zero build step matters here, because Tailwind is compiled on the server and any
    new front-end package would have to survive that deploy. (Quill is vendored locally
    but is a rich-text editor: it would silently rewrite HTML source into its own markup.)
--}}
@php
    $tokenGroups = \App\Services\Auction\TemplateTokenService::catalogue();
@endphp

<div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg border border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-bold text-gray-800 dark:text-white">HTML Screen</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Write the whole display yourself. Live values go in as <code>{tokens}</code>.
            </p>
        </div>
        @isset($template)
            <a href="{{ route('admin.auction-templates.preview', $template) }}" target="_blank"
               class="px-3 py-1.5 text-xs font-medium text-gray-700 border border-gray-300 rounded-lg dark:text-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800">
                Preview
            </a>
        @endisset
    </div>

    <div class="p-3 mb-4 text-xs rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
        HTML and CSS only — scripts are rejected on save and blocked in the browser.
        Everything that changes during the auction comes from the tokens below.
        Put <code>class="s-{status}"</code> on a wrapper and you can style the whole
        sold/unsold state in CSS alone.
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">
            <div>
                <label for="html_body" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Body HTML</label>
                <textarea name="html_body" id="html_body" rows="26"
                          class="form-control"
                          style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; white-space: pre; tab-size: 2;"
                          placeholder="&lt;div class=&quot;lower-third s-{status}&quot;&gt;&#10;  &lt;img src=&quot;{player_image}&quot; alt=&quot;&quot;&gt;&#10;  &lt;h1&gt;{player_name}&lt;/h1&gt;&#10;  &lt;span&gt;{player_role}&lt;/span&gt;&#10;  &lt;div class=&quot;bid&quot;&gt;{current_bid}&lt;/div&gt;&#10;&lt;/div&gt;">{{ old('html_body', $template->html_body ?? '') }}</textarea>
            </div>

            <div>
                <label for="html_css" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CSS</label>
                <textarea name="html_css" id="html_css" rows="14"
                          class="form-control"
                          style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; white-space: pre; tab-size: 2;"
                          placeholder=".lower-third { position: fixed; bottom: 80px; left: 60px; }">{{ old('html_css', $template->html_css ?? '') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="html_refresh_ms" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Refresh (ms)</label>
                    <input type="number" name="html_refresh_ms" id="html_refresh_ms" min="500" max="60000" step="100"
                           value="{{ old('html_refresh_ms', $template->html_refresh_ms ?? 2000) }}"
                           class="form-control">
                    <p class="text-xs text-gray-400 mt-1">How often it re-reads live values.</p>
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer pb-2">
                        <input type="hidden" name="html_transparent_bg" value="0">
                        <input type="checkbox" name="html_transparent_bg" value="1"
                               {{ old('html_transparent_bg', $template->html_transparent_bg ?? false) ? 'checked' : '' }}>
                        Transparent background (OBS overlay)
                    </label>
                </div>
            </div>
        </div>

        {{-- Click-to-insert cheat-sheet. --}}
        <div class="lg:col-span-1">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tokens</p>
            <div class="space-y-3 max-h-[36rem] overflow-y-auto pr-1">
                @foreach($tokenGroups as $group => $tokens)
                    <div>
                        <p class="text-[11px] uppercase tracking-wider text-gray-400 mb-1">{{ $group }}</p>
                        <div class="flex flex-wrap gap-1">
                            @foreach($tokens as $token => $description)
                                <button type="button" onclick="insertToken('{{ $token }}')"
                                        title="{{ $description }}"
                                        class="px-2 py-1 text-[11px] font-mono rounded border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                    {{ '{' . $token . '}' }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
