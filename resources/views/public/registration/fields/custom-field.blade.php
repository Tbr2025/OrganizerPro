@php
    /** @var \App\Models\TournamentCustomField $cf */
    $name = 'custom_fields[' . $cf->id . ']';
    $id = 'cf_' . $cf->id;
    $old = old('custom_fields.' . $cf->id);
    $req = (bool) $cf->required;
    $fullWidth = in_array($cf->type, ['textarea'], true);
@endphp
<div class="{{ $fullWidth ? 'md:col-span-2' : '' }}">
    @switch($cf->type)
        @case('textarea')
            <label for="{{ $id }}" class="reg-label">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</label>
            <textarea name="{{ $name }}" id="{{ $id }}" rows="2" class="reg-input" {{ $req ? 'required' : '' }} placeholder="{{ $cf->label }}">{{ $old }}</textarea>
            @break

        @case('number')
            <label for="{{ $id }}" class="reg-label">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</label>
            <input type="number" name="{{ $name }}" id="{{ $id }}" value="{{ $old }}" class="reg-input" {{ $req ? 'required' : '' }} placeholder="{{ $cf->label }}">
            @break

        @case('date')
            <label for="{{ $id }}" class="reg-label">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</label>
            <input type="date" name="{{ $name }}" id="{{ $id }}" value="{{ $old }}" class="reg-input" {{ $req ? 'required' : '' }}>
            @break

        @case('dropdown')
            <label for="{{ $id }}" class="reg-label">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</label>
            <select name="{{ $name }}" id="{{ $id }}" class="reg-select" {{ $req ? 'required' : '' }}>
                <option value="">Select {{ $cf->label }}</option>
                @foreach((array) $cf->options as $opt)
                    <option value="{{ $opt }}" {{ (string) $old === (string) $opt ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
            @break

        @case('checkbox')
            <label class="reg-check">
                <input type="hidden" name="{{ $name }}" value="0">
                <input type="checkbox" name="{{ $name }}" id="{{ $id }}" value="1" {{ (string) $old === '1' ? 'checked' : '' }} {{ $req ? 'required' : '' }}>
                <span class="text-sm">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</span>
            </label>
            @break

        @default
            <label for="{{ $id }}" class="reg-label">{{ $cf->label }} @if($req)<span class="reg-req">*</span>@endif</label>
            <input type="text" name="{{ $name }}" id="{{ $id }}" value="{{ $old }}" class="reg-input" {{ $req ? 'required' : '' }} placeholder="{{ $cf->label }}">
    @endswitch
    @error('custom_fields.' . $cf->id)<p class="reg-err">{{ $message }}</p>@enderror
</div>
