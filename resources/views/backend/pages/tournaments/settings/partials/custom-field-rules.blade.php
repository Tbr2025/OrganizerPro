@php
    /**
     * Rules, help text and conditional visibility for one custom field.
     *
     * Included by both the "edit" row and the "add new" form, so the two can never drift.
     * $cf is null when adding.
     *
     * Every input here is optional. Posting none of them leaves validation/conditions null,
     * which the model reads as "no extra rules, always visible" — the behaviour of every field
     * created before this existed.
     */
    /** @var \App\Models\TournamentCustomField|null $cf */
    $cf = $cf ?? null;
    $v = $cf && is_array($cf->validation) ? $cf->validation : [];
    $conds = $cf && is_array($cf->conditions) ? array_values($cf->conditions) : [];
    $uid = $cf?->id ?? 'new';
    $inputCls = 'w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white';

    // What a condition may key off: the tournament's other custom fields, plus the built-in
    // form fields. Keyed by id for custom fields and by input name for core ones, matching
    // what the browser and the server both look up at submit time.
    $otherFields = ($allCustomFields ?? collect())->reject(fn ($f) => $cf && $f->id === $cf->id);
    $coreKeys = array_values(array_unique(array_merge(
        collect(\App\Helpers\PlayerFormConfig::fieldGroups())->flatten()->all(),
        collect(\App\Helpers\TeamFormConfig::fieldGroups())->flatten()->all(),
    )));
@endphp

<div class="md:col-span-12 mt-1">
    <details class="rounded-md border border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-white/[0.02]"
             @if($conds || $v || ($cf?->help_text)) open @endif>
        <summary class="cursor-pointer select-none px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            Rules &amp; conditions
            @if($conds)<span class="ml-1 normal-case font-normal text-indigo-600">· {{ count($conds) }} condition{{ count($conds) === 1 ? '' : 's' }}</span>@endif
        </summary>

        <div class="px-3 pb-3 space-y-3 cf-rules" data-cf-uid="{{ $uid }}">

            <div>
                <label class="block text-[11px] uppercase tracking-wider text-gray-400 mb-1">Help text (shown under the field)</label>
                <input type="text" name="help_text" value="{{ $cf?->help_text }}" maxlength="500"
                       placeholder="e.g. Under-19 tournament — age as of 1 September" class="{{ $inputCls }}">
            </div>

            {{-- Validation. Every box is rendered and JS shows only the ones that apply to the
                 chosen type, so switching type never silently drops a rule you cannot see. --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <div data-rule-for="number,year">
                    <label class="block text-[11px] text-gray-400 mb-1">Min value</label>
                    <input type="number" step="any" name="validation[min]" value="{{ $v['min'] ?? '' }}" class="{{ $inputCls }}">
                </div>
                <div data-rule-for="number,year">
                    <label class="block text-[11px] text-gray-400 mb-1">Max value</label>
                    <input type="number" step="any" name="validation[max]" value="{{ $v['max'] ?? '' }}" class="{{ $inputCls }}">
                </div>
                <div data-rule-for="text,textarea,tel">
                    <label class="block text-[11px] text-gray-400 mb-1">Min length</label>
                    <input type="number" min="0" name="validation[minlength]" value="{{ $v['minlength'] ?? '' }}" class="{{ $inputCls }}">
                </div>
                <div data-rule-for="text,textarea,tel">
                    <label class="block text-[11px] text-gray-400 mb-1">Max length</label>
                    <input type="number" min="1" name="validation[maxlength]" value="{{ $v['maxlength'] ?? '' }}" placeholder="1000" class="{{ $inputCls }}">
                </div>
                <div data-rule-for="date,datetime,month" class="col-span-2">
                    <label class="block text-[11px] text-gray-400 mb-1">Earliest date</label>
                    <input type="date" name="validation[after]" value="{{ $v['after'] ?? '' }}" class="{{ $inputCls }}">
                </div>
                <div data-rule-for="date,datetime,month" class="col-span-2">
                    <label class="block text-[11px] text-gray-400 mb-1">Latest date</label>
                    <input type="date" name="validation[before]" value="{{ $v['before'] ?? '' }}" class="{{ $inputCls }}">
                </div>
                <div data-rule-for="checkbox_group,multiselect">
                    <label class="block text-[11px] text-gray-400 mb-1">Min choices</label>
                    <input type="number" min="0" name="validation[min_choices]" value="{{ $v['min_choices'] ?? '' }}" class="{{ $inputCls }}">
                </div>
                <div data-rule-for="checkbox_group,multiselect">
                    <label class="block text-[11px] text-gray-400 mb-1">Max choices</label>
                    <input type="number" min="1" name="validation[max_choices]" value="{{ $v['max_choices'] ?? '' }}" class="{{ $inputCls }}">
                </div>
                <div data-rule-for="file" class="col-span-2">
                    <label class="block text-[11px] text-gray-400 mb-1">Allowed types</label>
                    <input type="text" name="validation[file_types]" value="{{ $v['file_types'] ?? '' }}" placeholder="pdf,jpg,png" class="{{ $inputCls }}">
                </div>
                <div data-rule-for="file" class="col-span-2">
                    <label class="block text-[11px] text-gray-400 mb-1">Max size (KB)</label>
                    <input type="number" min="1" name="validation[file_max_kb]" value="{{ $v['file_max_kb'] ?? '' }}" placeholder="4096" class="{{ $inputCls }}">
                </div>
                <div data-rule-for="text" class="col-span-4">
                    <label class="block text-[11px] text-gray-400 mb-1">Pattern (the whole answer must match)</label>
                    <div class="flex gap-2">
                        <select class="cf-pattern-preset text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <option value="">Preset…</option>
                            <option value="[A-Za-z ]+">Letters and spaces</option>
                            <option value="[A-Za-z0-9]+">Letters and numbers</option>
                            <option value="[0-9]+">Digits only</option>
                            <option value="[A-Z]{2,}[0-9]{2,}">Letters then digits (e.g. AB1234)</option>
                        </select>
                        <input type="text" name="validation[pattern]" value="{{ $v['pattern'] ?? '' }}"
                               placeholder="[A-Za-z ]+" class="{{ $inputCls }} cf-pattern-input font-mono">
                    </div>
                </div>
            </div>

            {{-- Conditional visibility --}}
            <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[11px] uppercase tracking-wider text-gray-400">Show this field when</span>
                    <select name="condition_match" class="text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        @foreach([\App\Models\TournamentCustomField::MATCH_ALL => 'ALL of these match (and)',
                                  \App\Models\TournamentCustomField::MATCH_ANY => 'ANY of these match (or)',
                                  \App\Models\TournamentCustomField::MATCH_NONE => 'NONE of these match (not)'] as $val => $lbl)
                            <option value="{{ $val }}" {{ ($cf?->condition_match ?: 'all') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="cf-add-condition ml-auto px-2 py-1 text-[11px] font-medium rounded-md bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100">
                        + Add condition
                    </button>
                </div>

                <div class="cf-conditions mt-2 space-y-2">
                    @foreach($conds as $i => $c)
                        <div class="cf-condition flex items-center gap-2">
                            <select name="conditions[{{ $i }}][field]" class="{{ $inputCls }} flex-1">
                                <option value="">— choose a field —</option>
                                <optgroup label="Custom fields">
                                    @foreach($otherFields as $of)
                                        <option value="{{ $of->id }}" {{ (string) ($c['field'] ?? '') === (string) $of->id ? 'selected' : '' }}>{{ $of->label }}</option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Standard fields">
                                    @foreach($coreKeys as $ck)
                                        <option value="{{ $ck }}" {{ ($c['field'] ?? '') === $ck ? 'selected' : '' }}>{{ str_replace('_', ' ', $ck) }}</option>
                                    @endforeach
                                </optgroup>
                            </select>
                            <select name="conditions[{{ $i }}][operator]" class="{{ $inputCls }} w-40">
                                @foreach(\App\Models\TournamentCustomField::OPERATORS as $op => $lbl)
                                    <option value="{{ $op }}" {{ ($c['operator'] ?? 'equals') === $op ? 'selected' : '' }}>{{ $lbl }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="conditions[{{ $i }}][value]" value="{{ $c['value'] ?? '' }}" placeholder="value" class="{{ $inputCls }} flex-1">
                            <button type="button" class="cf-remove-condition px-2 py-1 text-xs rounded-md border border-red-200 text-red-600 hover:bg-red-50">×</button>
                        </div>
                    @endforeach
                </div>

                <p class="text-[11px] text-gray-400 mt-2">
                    Leave empty to always show the field. Hidden fields are not validated and store nothing,
                    even if they are marked required.
                </p>

                {{-- The blank row the "+ Add condition" button clones. --}}
                <template class="cf-condition-template">
                    <div class="cf-condition flex items-center gap-2">
                        <select data-name="field" class="{{ $inputCls }} flex-1">
                            <option value="">— choose a field —</option>
                            <optgroup label="Custom fields">
                                @foreach($otherFields as $of)
                                    <option value="{{ $of->id }}">{{ $of->label }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Standard fields">
                                @foreach($coreKeys as $ck)
                                    <option value="{{ $ck }}">{{ str_replace('_', ' ', $ck) }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                        <select data-name="operator" class="{{ $inputCls }} w-40">
                            @foreach(\App\Models\TournamentCustomField::OPERATORS as $op => $lbl)
                                <option value="{{ $op }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                        <input type="text" data-name="value" placeholder="value" class="{{ $inputCls }} flex-1">
                        <button type="button" class="cf-remove-condition px-2 py-1 text-xs rounded-md border border-red-200 text-red-600 hover:bg-red-50">×</button>
                    </div>
                </template>
            </div>
        </div>
    </details>
</div>
