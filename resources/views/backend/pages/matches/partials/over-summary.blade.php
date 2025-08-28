@foreach ($overs as $overNumber => $balls)
    <div class="my-3">
        <span class="font-semibold">Over {{ $overNumber }}:</span>

        <div class="flex flex-wrap gap-2 mt-2">
            @foreach ($balls as $ball)
                @php
                    // Label logic
                    $label = $ball->runs;
                    if ($ball->runs == 0 && !$ball->is_wicket && !$ball->extra_type) {
                        $label = '•';
                    }
                    if ($ball->is_wicket) {
                        $label = 'W';
                    }
                    if ($ball->extra_type) {
                        $map = [
                            'wide' => 'WD',
                            'no_ball' => 'NB',
                            'bye' => 'B',
                            'leg_bye' => 'LB',
                        ];
                        $label = $map[$ball->extra_type] ?? strtoupper($ball->extra_type);
                        if ($ball->extra_runs > 0) {
                            $label .= $ball->extra_runs;
                        }
                    }
                @endphp

                <span
                    class="
                        relative inline-flex items-center justify-center
                        w-8 h-8 rounded-full text-sm font-semibold
                        @if ($label === 'W') bg-red-500 text-white
                        @elseif(in_array($label, ['4', '6'])) bg-green-500 text-white
                        @elseif($label === '•') bg-gray-200 text-gray-700
                        @else bg-blue-100 text-blue-800 @endif
                    ">
                    {{ $label }}

                    {{-- Remove button --}}
                    <button onclick="removeBall({{ $ball->id }})"
                        class="absolute -top-2 -right-2 bg-black text-white rounded-full w-4 h-4 flex items-center justify-center text-xs leading-none"
                        title="Remove Ball">
                        ×
                    </button>
                </span>
            @endforeach
        </div>
    </div>
@endforeach

<script>
    function refreshOverSummary() {
        fetch("{{ route('admin.balls.summary', $match) }}")
            .then(res => res.text())
            .then(html => {
                console.log(html);
                // document.getElementById('overSummary').innerHTML = html;
            });
    }

    function removeBall(ballId) {
        if (!confirm("Remove this ball?")) return;

        fetch("{{ route('admin.balls.destroy', ['match' => $match->id, 'ball' => ':id']) }}".replace(':id', ballId), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // just refresh once
                    refreshOverSummary();
                } else {
                    alert(data.error || 'Error removing ball');
                }
            });
    }
</script>
