<button {{ $attributes->merge(['class' => 'inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700']) }}>
    {{ $slot }}
</button>
