<button {{ $attributes->merge(['class' => 'inline-flex items-center px-3 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700']) }}>
    {{ $slot }}
</button>
