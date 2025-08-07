@extends('backend.layouts.app')

@section('title', 'Live Scoring')

@section('admin-content')
    <div class="max-w-3xl mx-auto bg-white p-6 rounded shadow">
        <h2 class="text-xl font-bold mb-4">Live Scoring - {{ $match->name }}</h2>
        <form id="ajaxBallForm">
            @csrf
            <input type="hidden" name="match_id" value="{{ $match->id }}">
            @php
                $battingTeam = $match->teamA;
                $bowlingTeam = $match->teamB;
            @endphp
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="font-medium">Batsman</label>
                    <select name="batsman_id" id="batsman_id" required class="w-full border rounded">
                        <optgroup label="Batting - {{ $battingTeam->name }}">
                            @foreach ($battingTeam->players as $player)
                                <option value="{{ $player->id }}">{{ $player->name }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>

                <div>
                    <label class="font-medium">Bowler</label>
                    <select name="bowler_id" id="bowler_id" required class="w-full border rounded">
                        <optgroup label="Bowling - {{ $bowlingTeam->name }}">
                            @foreach ($bowlingTeam->players as $player)
                                <option value="{{ $player->id }}">{{ $player->name }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <label class="font-medium block mb-1">Runs</label>
                <div class="flex flex-wrap gap-2">
                    @for ($i = 0; $i <= 6; $i++)
                        <label class="flex items-center gap-1 px-3 py-1 border rounded cursor-pointer">
                            <input type="radio" name="runs" value="{{ $i }}"> {{ $i }}
                        </label>
                    @endfor
                </div>
            </div>

            <div class="mt-4">
                <label class="font-medium block mb-1">Extra</label>
                <div class="flex flex-wrap gap-3">
                    @php $extras = ['' => 'None', 'wide' => 'Wide', 'no_ball' => 'No Ball', 'bye' => 'Bye', 'leg_bye' => 'Leg Bye']; @endphp
                    @foreach ($extras as $value => $label)
                        <label class="flex items-center gap-1 px-3 py-1 border rounded cursor-pointer">
                            <input type="radio" name="extra_type" value="{{ $value }}"> {{ $label }}
                        </label>
                    @endforeach
                </div>
                <input type="number" name="extra_runs" class="w-24 mt-2 border rounded" placeholder="Extra runs" min="0" max="10" value="0">
            </div>

            <div class="mt-4">
                <label class="flex items-center gap-2 font-medium">
                    <input type="checkbox" name="is_wicket" value="1"> Wicket
                </label>
            </div>

            <div class="grid grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="font-medium">Over</label>
                    <input type="number" name="over" class="w-full border rounded" min="0">
                </div>
                <div>
                    <label class="font-medium">Ball (1-6)</label>
                    <input type="number" name="ball_in_over" class="w-full border rounded" min="1" max="6">
                </div>
            </div>

            <button type="submit" class="mt-6 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded">Submit Ball</button>
        </form>

        <div id="overSummary" class="mt-8">
            @include('backend.pages.matches.partials.over-summary', ['overs' => $overs ?? collect()])
        </div>
    </div>

    <script>
        document.getElementById('ajaxBallForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = e.target;
            const formData = new FormData(form);

            fetch("{{ route('admin.balls.ajaxStore', $match) }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': formData.get('_token')
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    form.reset();
                    refreshOverSummary();
                } else {
                    alert(data.error || 'Error saving ball!');
                }
            });
        });

        function refreshOverSummary() {
            fetch("{{ route('admin.balls.summary', $match) }}")
                .then(res => res.text())
                .then(html => {
                    document.getElementById('overSummary').innerHTML = html;
                });
        }
    </script>
@endsection
