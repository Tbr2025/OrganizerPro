<div {{ $attributes->merge(['class' => 'bg-white shadow-md rounded p-4']) }}>
    @isset($header)
        <div class="border-b pb-2 mb-4 text-lg font-semibold">{{ $header }}</div>
    @endisset

    <div>
        {{ $body ?? $slot }}
    </div>
</div>
