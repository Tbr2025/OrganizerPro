@foreach ($overs as $over => $balls)
    <div class="my-2">
        <span class="font-semibold">Over {{ $over }}:</span>
        @foreach ($balls as $ball)
            <span class="inline-block bg-gray-200 text-sm px-2 py-1 rounded mx-1">
                {{ $ball->runs }}{{ $ball->extra_type ? strtoupper(substr($ball->extra_type, 0, 1)) : '' }}{{ $ball->is_wicket ? 'W' : '' }}
            </span>
        @endforeach
    </div>
@endforeach
