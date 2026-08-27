@php
    /** @var \App\Models\TournamentCustomField $cf */
    $name = 'custom_fields[' . $cf->id . ']';
    $id = 'cf_' . $cf->id;
    $old = old('custom_fields.' . $cf->id);
    $req = (bool) $cf->required;
    $multi = $cf->isMultiValue();
    $oldArr = is_array($old) ? array_map('strval', $old) : [];
    $opts = (array) $cf->options;
    $v = is_array($cf->validation) ? $cf->validation : [];

    // Wide types get the full row; a two-column grid would squash them.
    $fullWidth = in_array($cf->type, ['textarea', 'checkbox_group', 'radio', 'multiselect', 'heading', 'divider'], true);

    /*
     * Conditional visibility travels to the browser as data attributes and is applied by
     * registration-conditions.js. The server re-evaluates the same rules on submit — this is
     * only so the form reacts as it is filled in.
     */
    $conds = is_array($cf->conditions) ? $cf->conditions : [];
@endphp
<div class="cf-wrap {{ $fullWidth ? 'md:col-span-2' : '' }}"
     data-cf-id="{{ $cf->id }}"
     data-cf-type="{{ $cf->type }}"
     @if($conds) data-cf-conditions="{{ json_encode($conds) }}" data-cf-match="{{ $cf->condition_match ?: 'all' }}" @endif>
    @switch($cf->type)

        {{-- Layout only: these collect nothing, so they carry no input and no name. --}}
        @case('heading')
            <h4 class="text-base font-semibold text-gray-900 dark:text-white mt-2">{{ $cf->label }}</h4>
            @if($cf->help_text)<p class="reg-help">{{ $cf->help_text }}</p>@endif
            @break

        @case('divider')
            <hr class="my-3 border-gray-200 dark:border-gray-700">
            @break

        @case('textarea')
            <label for="{{ $id }}" class="reg-label">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</label>
            <textarea name="{{ $name }}" id="{{ $id }}" rows="3" class="reg-input" {{ $req ? 'required' : '' }}
                      @if(isset($v['maxlength'])) maxlength="{{ (int) $v['maxlength'] }}" @endif
                      placeholder="{{ $cf->label }}">{{ $old }}</textarea>
            @break

        @case('number')
        @case('year')
            <label for="{{ $id }}" class="reg-label">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</label>
            <input type="number" name="{{ $name }}" id="{{ $id }}" value="{{ $old }}" class="reg-input" {{ $req ? 'required' : '' }}
                   @if(isset($v['min']) && $v['min'] !== '') min="{{ $v['min'] }}" @endif
                   @if(isset($v['max']) && $v['max'] !== '') max="{{ $v['max'] }}" @endif
                   @if($cf->type === 'year') step="1" placeholder="YYYY" @else placeholder="{{ $cf->label }}" @endif>
            @break

        @case('email')
        @case('tel')
        @case('url')
            <label for="{{ $id }}" class="reg-label">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</label>
            <input type="{{ $cf->type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $old }}" class="reg-input" {{ $req ? 'required' : '' }}
                   @if($cf->type === 'tel') inputmode="tel" @endif
                   placeholder="{{ $cf->type === 'url' ? 'https://…' : $cf->label }}">
            @break

        @case('date')
        @case('time')
        @case('month')
            <label for="{{ $id }}" class="reg-label">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</label>
            <input type="{{ $cf->type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $old }}" class="reg-input" {{ $req ? 'required' : '' }}
                   @if(!empty($v['after'])) min="{{ $v['after'] }}" @endif
                   @if(!empty($v['before'])) max="{{ $v['before'] }}" @endif>
            @break

        @case('datetime')
            <label for="{{ $id }}" class="reg-label">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</label>
            <input type="datetime-local" name="{{ $name }}" id="{{ $id }}" value="{{ $old }}" class="reg-input" {{ $req ? 'required' : '' }}>
            @break

        @case('file')
            <label for="{{ $id }}" class="reg-label">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</label>
            <input type="file" name="{{ $name }}" id="{{ $id }}" class="reg-input" {{ $req ? 'required' : '' }}
                   @if(!empty($v['file_types'])) accept=".{{ str_replace(',', ',.', str_replace(' ', '', $v['file_types'])) }}" @endif>
            @break

        @case('dropdown')
            <label for="{{ $id }}" class="reg-label">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</label>
            <select name="{{ $name }}" id="{{ $id }}" class="reg-select" {{ $req ? 'required' : '' }}>
                <option value="">Select {{ $cf->label }}</option>
                @foreach($opts as $opt)
                    <option value="{{ $opt }}" {{ (string) $old === (string) $opt ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
            @break

        @case('multiselect')
            <label for="{{ $id }}" class="reg-label">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</label>
            {{-- The [] on the name is what makes the browser post every chosen option. --}}
            <select name="custom_fields[{{ $cf->id }}][]" id="{{ $id }}" multiple size="{{ min(6, max(3, count($opts))) }}"
                    class="reg-select" {{ $req ? 'required' : '' }}>
                @foreach($opts as $opt)
                    <option value="{{ $opt }}" {{ in_array((string) $opt, $oldArr, true) ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
            <p class="reg-help">Hold Ctrl (or Cmd) to choose more than one.</p>
            @break

        @case('radio')
            <span class="reg-label">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</span>
            <div class="flex flex-wrap gap-x-5 gap-y-2 mt-1">
                @foreach($opts as $i => $opt)
                    <label class="reg-check">
                        <input type="radio" name="{{ $name }}" id="{{ $id }}_{{ $i }}" value="{{ $opt }}"
                               {{ (string) $old === (string) $opt ? 'checked' : '' }} {{ $req ? 'required' : '' }}>
                        <span class="text-sm">{{ $opt }}</span>
                    </label>
                @endforeach
            </div>
            @break

        @case('checkbox_group')
            <span class="reg-label">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</span>
            <div class="flex flex-wrap gap-x-5 gap-y-2 mt-1">
                @foreach($opts as $i => $opt)
                    <label class="reg-check">
                        <input type="checkbox" name="custom_fields[{{ $cf->id }}][]" id="{{ $id }}_{{ $i }}" value="{{ $opt }}"
                               {{ in_array((string) $opt, $oldArr, true) ? 'checked' : '' }}>
                        <span class="text-sm">{{ $opt }}</span>
                    </label>
                @endforeach
            </div>
            @break

        @case('checkbox')
            <label class="reg-check">
                {{-- The hidden 0 makes an unticked box post something, so "unticked" is a real
                     answer rather than a missing key. --}}
                <input type="hidden" name="{{ $name }}" value="0">
                <input type="checkbox" name="{{ $name }}" id="{{ $id }}" value="1" {{ (string) $old === '1' ? 'checked' : '' }} {{ $req ? 'required' : '' }}>
                <span class="text-sm">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</span>
            </label>
            @break

        @default
            <label for="{{ $id }}" class="reg-label">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</label>
            <input type="text" name="{{ $name }}" id="{{ $id }}" value="{{ $old }}" class="reg-input" {{ $req ? 'required' : '' }}
                   @if(isset($v['maxlength'])) maxlength="{{ (int) $v['maxlength'] }}" @endif
                   placeholder="{{ $cf->label }}">
    @endswitch

    @if($cf->help_text && ! in_array($cf->type, ['heading', 'divider'], true))
        <p class="reg-help">{{ $cf->help_text }}</p>
    @endif
    @error('custom_fields.' . $cf->id)<p class="reg-err">{{ $message }}</p>@enderror
</div>
