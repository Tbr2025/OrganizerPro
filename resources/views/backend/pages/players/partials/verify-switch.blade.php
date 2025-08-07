@props(['name', 'checked' => false])

<label class="inline-flex items-center space-x-2">
    <input type="checkbox" name="verified_{{ $name }}" class="form-checkbox h-5 w-5 text-green-600"
        {{ $checked ? 'checked' : '' }}>
    <span class="text-sm text-gray-700">Verified</span>
</label>
