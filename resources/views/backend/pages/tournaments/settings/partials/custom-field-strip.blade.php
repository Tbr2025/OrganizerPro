@php
    /**
     * The custom fields belonging to one registration form, shown right under that form's field
     * groups — which is where an organizer is already looking when they think "I need one more
     * question". The editors themselves live in modals so this stays a list you can scan.
     *
     * @var string $form   'player' or 'team'
     * @var \Illuminate\Support\Collection $fields
     */
    $fields = ($allCustomFields ?? collect())->where('form', $form)->sortBy('sort_order');
@endphp

<div class="mt-4 rounded-lg border border-dashed border-indigo-200 dark:border-indigo-900/60 bg-indigo-50/40 dark:bg-indigo-900/10 p-3">
    <div class="flex items-center gap-2 flex-wrap">
        <span class="text-xs font-semibold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">
            Custom {{ $form }} fields
        </span>
        <span class="text-[11px] text-gray-500 dark:text-gray-400">
            {{ $fields->count() }} added · {{ $fields->where('visible', true)->count() }} shown on the form
        </span>
        <button type="button" data-cf-open="new-{{ $form }}"
                class="ml-auto inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add custom field
        </button>
    </div>

    @if($fields->isEmpty())
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
            None yet. Added fields appear in the section you choose, on the public form and in the admin player record.
        </p>
    @else
        <div class="mt-2 space-y-1.5">
            @foreach($fields as $cf)
                <div class="flex items-center gap-2 rounded-md bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 px-2.5 py-1.5 {{ $cf->visible ? '' : 'opacity-60' }}">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate max-w-[38%]">{{ $cf->label }}</span>

                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-300 shrink-0">
                        {{ \App\Models\TournamentCustomField::TYPES[$cf->type] ?? $cf->type }}
                    </span>
                    <span class="text-[11px] text-gray-400 truncate hidden sm:inline">{{ $cf->section }}</span>

                    @if($cf->required)
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 shrink-0">required</span>
                    @endif
                    @if(is_array($cf->conditions) && $cf->conditions)
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 shrink-0"
                              title="Only shown when its conditions match">conditional</span>
                    @endif
                    @unless($cf->visible)
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-200 shrink-0">disabled</span>
                    @endunless

                    <div class="ml-auto flex items-center gap-1 shrink-0">
                        {{-- Enable/disable is its own one-click post: it is the switch that decides
                             whether the field appears on the registration form and in the admin
                             player record, and it should not need a trip through the editor. --}}
                        <form method="POST" action="{{ route('admin.tournaments.settings.custom-fields.toggle', [$tournament, $cf]) }}">
                            @csrf
                            <button type="submit" title="{{ $cf->visible ? 'Disable — hide from the form (answers are kept)' : 'Enable — show on the form' }}"
                                    class="px-2 py-1 text-[11px] font-medium rounded-md {{ $cf->visible ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 hover:bg-gray-200' }}">
                                {{ $cf->visible ? 'Enabled' : 'Disabled' }}
                            </button>
                        </form>

                        <button type="button" data-cf-open="{{ $cf->id }}"
                                class="px-2 py-1 text-[11px] font-medium rounded-md bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200">
                            Edit
                        </button>

                        <form method="POST" action="{{ route('admin.tournaments.settings.custom-fields.destroy', [$tournament, $cf]) }}"
                              onsubmit="return confirm('Remove the field &quot;{{ $cf->label }}&quot;? Answers already collected stay on the records that have them, but the field disappears from the form.')">
                            @csrf @method('DELETE')
                            <button type="submit" title="Remove this field"
                                    class="px-2 py-1 text-[11px] font-medium rounded-md border border-red-200 dark:border-red-900 text-red-600 hover:bg-red-50">
                                Remove
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
