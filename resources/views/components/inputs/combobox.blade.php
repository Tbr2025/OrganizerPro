@props([
    'name',
    'label' => '',
    'placeholder' => 'Please Select',
    'options' => [],
    'selected' => [],
    'multiple' => false,
    'searchable' => false,
    'required' => false,
    'class' => '',
    'disabled' => false,
    // New functional props
    'queryParam' => null,
    'refreshPage' => false,
    // Optional: restrict visible options to those whose `group` matches this
    // value. Options must include a `group` key. Can be changed at runtime by
    // dispatching a `combobox-set-group` window event with { name, group }.
    'initialGroup' => null,
])

@php
    $selectedValues = is_array($selected) ? $selected : [$selected];
    $selectedValues = array_filter($selectedValues, fn($val) => !empty($val));
@endphp

<div x-data="{
        allOptions: {{ json_encode($options) }} ?? [],
        options: {{ json_encode($options) }} ?? [],
        isOpen: false,
        openedWithKeyboard: false,
        selectedOptions: ({{ json_encode($selectedValues) }} || []).map(v => String(v)),
        selectedOption: {{ $multiple ? 'null' : json_encode($selectedValues[0] ?? null) }},
        multiple: {{ $multiple ? 'true' : 'false' }},
        searchable: {{ $searchable ? 'true' : 'false' }},
        queryParam: '{{ $queryParam }}',
        refreshPage: {{ $refreshPage ? 'true' : 'false' }},
        searchQuery: '',
        groupFilter: {{ $initialGroup !== null && $initialGroup !== '' ? json_encode((string) $initialGroup) : 'null' }},

        setLabelText() {
            // Helper function to find option by value
            const findOption = (value) => {
                if (!this.allOptions) return null;

                if (Array.isArray(this.allOptions)) {
                    // Handle array format
                    return this.allOptions.find(opt => opt.value == value);
                } else {
                    // Handle object format - convert to array first
                    const optionsArray = Object.keys(this.allOptions)
                    .map(key => ({
                        value: key + '',
                        label: this.allOptions[key]?.label ?? '{{ __($placeholder) }}'
                    }));
                    return optionsArray.find(opt => opt.value == value);
                }
            };

            if (this.multiple) {
                const count = this.selectedOptions.length;
                if (count === 0) return '{{ __($placeholder) }}';
                if (count === 1) {
                    const option = findOption(this.selectedOptions[0]);
                    return option ? option.label : this.selectedOptions[0];
                }
                return count + ' items selected';
            } else {
                if (!this.selectedOption) return '{{ __($placeholder) }}';
                const option = findOption(this.selectedOption);
                return option?.label ?? this.selectedOption ?? '{{ __($placeholder) }}';
            }
        },

        setSelectedOption(option) {
            if (this.multiple) {
                return; // Handle in checkbox change
            } else {
                this.selectedOption = option.value + '';
                this.isOpen = false;
                this.openedWithKeyboard = false;
                this.$refs.hiddenTextField.value = option.value;

                // Handle URL update if needed
                if (this.queryParam) {
                    this.updateUrlParam(this.queryParam, option.value);
                }

                // Dispatch custom event
                const event = new CustomEvent('combobox-change', {
                    detail: {
                        name: '{{ $name }}',
                        value: option.value,
                        option: option
                    },
                    bubbles: true
                });
                this.$el.dispatchEvent(event);
            }
        },

        handleOptionToggle(optionValue, checked) {
            const strVal = String(optionValue);
            if (checked) {
                if (!this.selectedOptions.includes(strVal)) {
                    this.selectedOptions.push(strVal);
                }
            } else {
                this.selectedOptions = this.selectedOptions.filter(val => val !== strVal);
            }

            // Handle URL update for multiple select
            if (this.queryParam) {
                this.updateUrlParam(this.queryParam, this.selectedOptions.join(','));
            }

            // Dispatch custom event for multiple select
            const option = this.allOptions.find(opt => opt.value == optionValue);
            if (option) {
                const event = new CustomEvent('combobox-change', {
                    detail: {
                        name: '{{ $name }}',
                        value: this.selectedOptions,
                        option: option,
                        allSelected: this.selectedOptions
                    },
                    bubbles: true
                });
                this.$el.dispatchEvent(event);
            }
        },

        getFilteredOptions(query) {
            this.searchQuery = query;
            this.applyFilters();
        },

        applyFilters() {
            let base = Array.isArray(this.allOptions) ? this.allOptions : [];
            // Restrict to the active group (e.g. selected organization) first
            if (this.groupFilter !== null && this.groupFilter !== '') {
                base = base.filter(o => String(o.group ?? '') === String(this.groupFilter));
            }
            // Then apply the search query
            if (this.searchable && this.searchQuery) {
                const q = this.searchQuery.toLowerCase();
                base = base.filter(o => (o.label || '').toLowerCase().includes(q));
            }
            this.options = base;
        },

        setGroupFilter(group) {
            this.groupFilter = (group === null || group === undefined || group === '') ? null : String(group);
            this.applyFilters();
            // Drop selections that no longer belong to the active group
            if (this.groupFilter !== null) {
                const valid = new Set(
                    (Array.isArray(this.allOptions) ? this.allOptions : [])
                        .filter(o => String(o.group ?? '') === String(this.groupFilter))
                        .map(o => String(o.value))
                );
                this.selectedOptions = this.selectedOptions.filter(v => valid.has(String(v)));
                if (this.selectedOption && !valid.has(String(this.selectedOption))) {
                    this.selectedOption = null;
                    if (this.$refs.hiddenTextField) this.$refs.hiddenTextField.value = '';
                }
            }
        },

        updateUrlParam(param, value) {
            if (!param) return;

            const url = new URL(window.location.href);
            if (value && value !== '') {
                url.searchParams.set(param, value);
            } else {
                url.searchParams.delete(param);
            }

            // Update URL and refresh page if needed
            if (this.refreshPage) {
                window.location.href = url.toString();
            } else {
                window.history.pushState({}, '', url.toString());
            }
        },

        highlightFirstMatchingOption(pressedKey) {
            if (pressedKey === 'Enter') return;
            const option = this.options.find(item =>
                item.label.toLowerCase().startsWith(pressedKey.toLowerCase())
            );
            if (option) {
                const index = this.options.indexOf(option);
                const allOptions = document.querySelectorAll('.combobox-option');
                if (allOptions[index]) {
                    allOptions[index].focus();
                }
            }
        },

        positionDropdown() {
            const dd = this.$refs.dropdown;
            if (!dd) return;

            if (window.innerWidth < 640) {
                // Mobile: bottom sheet style
                dd.style.position = 'fixed';
                dd.style.left = '0';
                dd.style.right = '0';
                dd.style.bottom = '0';
                dd.style.top = 'auto';
                dd.style.width = '100%';
                dd.style.maxWidth = '';
            } else {
                // Desktop: fixed position below trigger, avoids overflow clipping
                const trigger = dd.previousElementSibling?.matches?.('input')
                    ? dd.closest('.relative')?.querySelector('button[role=combobox]')
                    : dd.closest('.relative')?.querySelector('button[role=combobox]');
                if (!trigger) return;
                const rect = trigger.getBoundingClientRect();
                dd.style.position = 'fixed';
                dd.style.left = rect.left + 'px';
                dd.style.top = (rect.bottom + 4) + 'px';
                dd.style.bottom = 'auto';
                dd.style.right = 'auto';
                dd.style.width = rect.width + 'px';
                dd.style.maxWidth = '320px';
            }
        },

        init() {
            // If queryParam is provided, check URL for initial value
            if (this.queryParam) {
                const url = new URL(window.location.href);
                const paramValue = url.searchParams.get(this.queryParam);

                if (paramValue) {
                    if (this.multiple) {
                        this.selectedOptions = paramValue.split(',');
                    } else {
                        this.selectedOption = paramValue;
                        this.$refs.hiddenTextField.value = paramValue;
                    }
                }
            }

            // Apply any initial group filter to the rendered options
            this.applyFilters();
        }
    }"
    class="w-full flex flex-col gap-1 {{ $class }}"
    x-on:keydown="highlightFirstMatchingOption($event.key)"
    x-on:keydown.esc.window="isOpen = false, openedWithKeyboard = false"
    x-on:combobox-set-group.window="if ($event.detail && $event.detail.name === '{{ $name }}') setGroupFilter($event.detail.group)"
    {{ $attributes->whereStartsWith('x-on:') }}>

    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __($label) }} @if($required) <span class="crm:text-red-500">*</span> @endif</label>

    @endif

    <div class="relative">
        <!-- Mobile backdrop overlay -->
        <div x-show="isOpen || openedWithKeyboard" x-cloak
            class="sm:hidden fixed inset-0 bg-black/30 z-[9998]"
            @click="isOpen = false; openedWithKeyboard = false;">
        </div>

        <!-- Trigger button -->
        <button type="button"
            role="combobox"
            class="form-control-combobox"
            x-on:click="isOpen = !isOpen"
            x-on:keydown.down.prevent="openedWithKeyboard = true"
            x-on:keydown.enter.prevent="openedWithKeyboard = true"
            x-on:keydown.space.prevent="openedWithKeyboard = true"
            x-bind:aria-expanded="isOpen || openedWithKeyboard"
            @if($disabled) disabled @endif>
            <span class="text-sm font-normal text-left truncate" x-text="setLabelText()"></span>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/>
            </svg>
        </button>

        <!-- Hidden input -->
        <template x-if="multiple">
            <div>
                <template x-for="(value, index) in selectedOptions" x-bind:key="index">
                    <input type="hidden" x-bind:name="'{{ str_replace('[]', '', $name) }}[' + index + ']'" x-bind:value="value" />
                </template>
            </div>
        </template>

        <input x-show="!multiple"
            x-bind:disabled="multiple"
            name="{{ $name }}"
            type="hidden"
            x-ref="hiddenTextField"
            x-bind:value="selectedOption"
            @if($required) required @endif />

        <!-- Dropdown -->
        <div x-cloak
            x-show="isOpen || openedWithKeyboard"
            x-ref="dropdown"
            x-init="$watch('isOpen', (val) => { if (val) $nextTick(() => positionDropdown()) })"
            class="fixed z-[9999] left-0 right-0 bottom-0 sm:bottom-auto sm:right-auto w-full overflow-hidden rounded-t-xl sm:rounded-xl sm:rounded-md border border-gray-300 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900"
            @click.outside="isOpen = false; openedWithKeyboard = false;"
            x-on:keydown.down.prevent="$focus.wrap().next()"
            x-on:keydown.up.prevent="$focus.wrap().previous()"
            x-transition
            x-trap="openedWithKeyboard">

            {{-- Mobile handle bar --}}
            <div class="sm:hidden flex justify-center py-2 border-b border-gray-200 dark:border-gray-700">
                <div class="w-10 h-1 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
            </div>

            @if($searchable)
            <!-- Search input -->
            <div class="border-b border-gray-200 dark:border-gray-700 p-2">
                <input type="text"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    placeholder="{{ __('Search...') }}"
                    x-on:input="getFilteredOptions($el.value)"
                    x-ref="searchField" />
            </div>
            @endif

            <!-- Options list -->
            <ul class="max-h-[50vh] sm:max-h-60 overflow-y-auto py-1 overscroll-contain"
                style="max-height: min(60vh, 20rem); overflow-y: auto;">
                <template x-for="(item, index) in options" x-bind:key="item.value">
                    @if($multiple)
                    <li role="option">
                        <label class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-white/90 dark:hover:bg-gray-800 cursor-pointer"
                            x-bind:for="'option_' + index">
                            <input type="checkbox"
                                class="form-checkbox combobox-option h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary dark:border-gray-700 dark:bg-gray-900"
                                x-bind:value="item.value"
                                x-bind:id="'option_' + index"
                                x-bind:checked="selectedOptions.includes(String(item.value))"
                                x-on:change="handleOptionToggle(item.value, $el.checked)"
                                tabindex="0" />
                            <span x-text="item.label"></span>
                        </label>
                    </li>
                    @else
                    <li class="combobox-option px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-white/90 dark:hover:bg-gray-800 cursor-pointer flex items-center justify-between"
                        role="option"
                        x-on:click="setSelectedOption(item)"
                        x-on:keydown.enter="setSelectedOption(item)"
                        x-bind:id="'option_' + index"
                        tabindex="0">
                        <span x-bind:class="selectedOption == item.value ? 'font-medium' : ''" x-text="item.label"></span>
                        <svg x-cloak x-show="selectedOption == item.value" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" class="size-4 text-primary">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                    </li>
                    @endif
                </template>

                <li x-show="options.length === 0" class="px-4 py-2 text-sm text-gray-500 dark:text-gray-300">
                    {{ __('No options found') }}
                </li>
            </ul>
        </div>
    </div>
</div>
