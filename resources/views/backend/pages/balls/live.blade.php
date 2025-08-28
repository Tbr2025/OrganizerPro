@extends('backend.layouts.app')

@section('title', 'Live Scoring')

@section('admin-content')
<div class="max-w-4xl mx-auto bg-white dark:bg-gray-900 p-6 rounded-xl shadow">
    <h2 class="text-xl font-bold mb-4 text-gray-800 dark:text-gray-100">
        Live Scoring – {{ $match->name }}
    </h2>

    {{-- ================= Ball Input Form ================= --}}
    <form id="ajaxBallForm" class="space-y-4">
        @csrf
        <input type="hidden" name="match_id" value="{{ $match->id }}">

        @php
            // Pick batting team dynamically (later you can toggle by innings)
            $battingTeam = $match->teamA;
            $bowlingTeam = $match->teamB;
        @endphp

        <div class="grid grid-cols-2 gap-4">
            {{-- Batsman --}}
            <div>
                <label class="font-medium block mb-1">Batsman (Batting – {{ $battingTeam->name }})</label>
                <select name="batsman_id" id="batsman_id" required class="w-full border rounded p-2">
                    <optgroup label="Batting – {{ $battingTeam->name }}">
                        @foreach ($battingTeam->actualTeamUsers as $player)
                            <option value="{{ $player->id }}">{{ $player->user->name }}</option>
                        @endforeach
                    </optgroup>
                </select>
            </div>

            {{-- Bowler --}}
            <div>
                <label class="font-medium block mb-1">Bowler (Bowling – {{ $bowlingTeam->name }})</label>
                <select name="bowler_id" id="bowler_id" required class="w-full border rounded p-2">
                    <optgroup label="Bowling – {{ $bowlingTeam->name }}">
                        @foreach ($bowlingTeam->actualTeamUsers as $player)
                            <option value="{{ $player->id }}">{{ $player->user->name }}</option>
                        @endforeach
                    </optgroup>
                </select>
            </div>
        </div>

        {{-- Runs --}}
        <div>
            <label class="font-medium block mb-1">Runs</label>
            <div class="flex flex-wrap gap-2">
                @for ($i = 0; $i <= 6; $i++)
                    <label class="flex items-center gap-1 px-3 py-1 border rounded cursor-pointer">
                        <input type="radio" name="runs" value="{{ $i }}" {{ $i === 0 ? 'checked' : '' }}>
                        {{ $i }}
                    </label>
                @endfor
            </div>
        </div>

        {{-- Extras --}}
        <div>
            <label class="font-medium block mb-1">Extras</label>
            <div class="flex flex-wrap gap-3">
                @php $extras = ['' => 'None', 'wide' => 'Wide', 'no_ball' => 'No Ball', 'bye' => 'Bye', 'leg_bye' => 'Leg Bye']; @endphp
                @foreach ($extras as $value => $label)
                    <label class="flex items-center gap-1 px-3 py-1 border rounded cursor-pointer">
                        <input type="radio" name="extra_type" value="{{ $value }}" {{ $value === '' ? 'checked' : '' }}>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <input type="number" name="extra_runs" class="w-24 mt-2 border rounded p-1" placeholder="Extra runs"
                min="0" max="10" value="0">
        </div>

        {{-- Wicket --}}
        <div>
            <label class="flex items-center gap-2 font-medium">
                <input type="checkbox" name="is_wicket" value="1"> Wicket
            </label>
        </div>

        <button type="submit"
            class="mt-4 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg shadow">
            Submit Ball
        </button>
    </form>

    {{-- ================= Over Summary ================= --}}
    <div id="overSummary" class="mt-8">
        @include('backend.pages.matches.partials.over-summary', ['overs' => $overs ?? collect(), 'match' => $match])
    </div>
</div>

{{-- ================= Scripts ================= --}}
<script>
document.getElementById('ajaxBallForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);

    fetch("{{ route('balls.store', $match) }}", {
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

function undoBall(matchId, ballId) {
    if (!confirm("Are you sure you want to undo this ball?")) return;

    fetch(`/matches/balls/${ballId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': "{{ csrf_token() }}" }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            refreshOverSummary();
        } else {
            alert(data.error || 'Error undoing ball');
        }
    });
}
</script>
@endsection
