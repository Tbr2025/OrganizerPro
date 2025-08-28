@extends('backend.layouts.app')

@section('title', 'Live Scoring')

@section('admin-content')
    <div class="max-w-4xl mx-auto bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-md">
        <h2 class="text-2xl font-bold mb-6 text-center text-indigo-700">
            🏏 Live Scoring - {{ $match->name }}
        </h2>

        {{-- Ball Entry Form --}}
        <form id="ajaxBallForm" class="space-y-6">
            @csrf
            <input type="hidden" name="match_id" value="{{ $match->id }}">

            @php
                $battingTeam = $match->teamA;
                $bowlingTeam = $match->teamB;
            @endphp

            <div class="grid md:grid-cols-2 gap-6">
                {{-- Batsman --}}
                {{-- Batsman --}}
                <div>
                    <label class="block font-semibold text-gray-700 mb-2">
                        Batting - {{ $battingTeam->name }}
                    </label>
                    <select name="batsman_id" id="batsman_id" required
                        class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                        @php
                            // Get IDs of out batsmen
                            $outBatsmanIds = \App\Models\Ball::where('match_id', $match->id)
                                ->where('is_wicket', 1)
                                ->pluck('batsman_id')
                                ->toArray();

                            // Filter batting players
                            $availableBatsmen = $battingTeam->users->whereNotIn('id', $outBatsmanIds);
                        @endphp

                        @foreach ($availableBatsmen as $player)
                            <option value="{{ $player->id }}">{{ $player->name }}</option>
                        @endforeach
                    </select>
                </div>


                {{-- Bowler --}}
                <div>
                    <label class="block font-semibold text-gray-700 mb-2">
                        Bowling - {{ $bowlingTeam->name }}
                    </label>
                    <select name="bowler_id" id="bowler_id" required
                        class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach ($bowlingTeam->users as $player)
                            <option value="{{ $player->id }}">{{ $player->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Runs --}}
            <div>
                <label class="block font-semibold text-gray-700 mb-2">Runs</label>
                <div class="flex flex-wrap gap-2">
                    @for ($i = 0; $i <= 6; $i++)
                        <label
                            class="flex items-center gap-1 px-3 py-1 border rounded-lg cursor-pointer hover:bg-indigo-50">
                            <input type="radio" name="runs" value="{{ $i }}"
                                {{ $i === 0 ? 'checked' : '' }}>
                            {{ $i }}
                        </label>
                    @endfor
                </div>
            </div>

            {{-- Extras --}}
            <div>
                <label class="block font-semibold text-gray-700 mb-2">Extras</label>
                <div class="flex flex-wrap gap-3">
                    @php
                        $extras = [
                            '' => 'None',
                            'wide' => 'Wide',
                            'no_ball' => 'No Ball',
                            'bye' => 'Bye',
                            'leg_bye' => 'Leg Bye',
                        ];
                    @endphp
                    @foreach ($extras as $value => $label)
                        <label
                            class="flex items-center gap-1 px-3 py-1 border rounded-lg cursor-pointer hover:bg-indigo-50">
                            <input type="radio" name="extra_type" value="{{ $value }}"
                                {{ $value === '' ? 'checked' : '' }}>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <input type="number" name="extra_runs"
                    class="mt-2 w-28 rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="Extra runs" min="0" max="10" value="0">
            </div>

            {{-- Wicket --}}
            <div>
                <label class="flex items-center gap-2 font-semibold text-red-600">
                    <input type="checkbox" name="is_wicket" value="1" class="h-4 w-4 text-red-600 focus:ring-red-500">
                    Wicket
                </label>
            </div>

            {{-- Submit --}}
            <div class="text-center">
                <button type="submit"
                    class="px-8 py-3 bg-indigo-600 text-white rounded-xl shadow hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500">
                    ✅ Submit Ball
                </button>
            </div>
        </form>

        {{-- Over Summary --}}
        <div id="overSummary" class="mt-10">
            @include('backend.pages.matches.partials.over-summary', ['overs' => $overs ?? collect()])
        </div>
    </div>

    {{-- JS --}}
    <script>
        document.getElementById('ajaxBallForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = e.target;
            const formData = new FormData(form);

            fetch("{{ route('admin.balls.ajaxStore', $match->id) }}", {
                    method: 'POST',
                    body: new FormData(document.getElementById('ajaxBallForm'))
                })

                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        form.reset();
                        refreshOverSummary();
                    } else {
                        alert(data.error || 'Error saving ball!');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Unexpected error! Check console.');
                });
        });



        function refreshOverSummary() {
            fetch("{{ route('admin.balls.summary', $match) }}")
                .then(res => res.text())
                .then(html => {
                    document.getElementById('overSummary').innerHTML = html;
                });
        }

        function undoBall(matchId, ballId) {
            if (!confirm("Are you sure you want to remove this ball?")) return;

            fetch(`/admin/matches/${matchId}/balls/${ballId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        refreshOverSummary();
                    } else {
                        alert(data.error || 'Error removing ball!');
                    }
                });
        }
    </script>
@endsection
