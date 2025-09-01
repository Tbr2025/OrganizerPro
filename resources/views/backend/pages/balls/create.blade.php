@extends('backend.layouts.app')

@section('title', 'Live Scoring - ' . $match->name)

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
                // Basic assumption for initial batting/bowling teams.
                // You'll need more sophisticated logic to determine this if it changes mid-match.
$battingTeam = $match->teamA;
$bowlingTeam = $match->teamB;

// Fetch IDs of all batsmen who are out in this match
$outBatsmanIds = \App\Models\Ball::where('match_id', $match->id)
    ->where('is_wicket', 1)
    ->pluck('batsman_id')
    ->unique() // Ensure each batsman ID is listed only once
    ->toArray();

// Placeholder for current innings state.
$currentStriker = null; // Ideally, fetch from session or DB
$currentNonStriker = null; // Ideally, fetch from session or DB
$currentBowler = null; // Ideally, fetch from session or DB

// --- Example of how you might fetch current striker/non-striker from session ---
// if (session()->has('current_striker_id_' . $match->id)) {
//     $currentStriker = $battingTeam->users->find(session('current_striker_id_' . $match->id));
// }
// if (session()->has('current_non_striker_id_' . $match->id)) {
//     $currentNonStriker = $battingTeam->users->find(session('current_non_striker_id_' . $match->id));
// }
// if (session()->has('current_bowler_id_' . $match->id)) {
//     $currentBowler = $bowlingTeam->users->find(session('current_bowler_id_' . $match->id));
                // }

            @endphp

            <div class="grid md:grid-cols-2 gap-6">
                {{-- Batsman (Striker and Non-Striker) --}}
                <div>
                    <label class="block font-semibold text-gray-700 mb-2">
                        Batting - {{ $battingTeam->name }}
                    </label>
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-600 mb-1">Striker</label>
                            <select name="batsman_id" id="batsman_id" required
                                class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">-- Select Striker --</option>
                                @foreach ($battingTeam->users as $player)
                                    {{-- Check if the player is OUT --}}
                                    @if (!in_array($player->id, $outBatsmanIds))
                                        <option value="{{ $player->id }}"
                                            {{ $currentStriker && $currentStriker->id == $player->id ? 'selected' : '' }}>
                                            {{ $player->name }}{{ $currentStriker && $currentStriker->id == $player->id ? '*' : '' }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-600 mb-1">Non-Striker</label>
                            <select name="non_striker_id" id="non_striker_id" required
                                class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">-- Select Non-Striker --</option>
                                @foreach ($battingTeam->users as $player)
                                    {{-- Check if the player is OUT --}}
                                    @if (!in_array($player->id, $outBatsmanIds))
                                        <option value="{{ $player->id }}"
                                            {{ $currentNonStriker && $currentNonStriker->id == $player->id ? 'selected' : '' }}>
                                            {{ $player->name }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Bowler --}}
                <div>
                    <label class="block font-semibold text-gray-700 mb-2">
                        Bowling - {{ $bowlingTeam->name }}
                    </label>
                    <select name="bowler_id" id="bowler_id" required
                        class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">-- Select Bowler --</option>
                        @foreach ($bowlingTeam->users as $player)
                            <option value="{{ $player->id }}"
                                {{ $currentBowler && $currentBowler->id == $player->id ? 'selected' : '' }}>
                                {{ $player->name }}</option>
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
                            class="inline-flex items-center gap-1 px-3 py-1 border rounded-lg cursor-pointer hover:bg-indigo-50 {{ $i === 0 ? 'bg-indigo-100 border-indigo-500' : '' }}">
                            <input type="radio" name="runs" value="{{ $i }}"
                                {{ $i === 0 ? 'checked' : '' }} class="form-radio text-indigo-600">
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
                            class="inline-flex items-center gap-1 px-3 py-1 border rounded-lg cursor-pointer hover:bg-indigo-50 {{ $value === '' ? 'bg-indigo-100 border-indigo-500' : '' }}">
                            <input type="radio" name="extra_type" value="{{ $value }}"
                                {{ $value === '' ? 'checked' : '' }} class="form-radio text-indigo-600">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <input type="number" name="extra_runs"
                    class="mt-2 w-28 rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="Extra runs" min="0" value="0">
            </div>

            {{-- Wicket --}}
            <div>
                <label class="flex items-center gap-2 font-semibold text-red-600">
                    <input type="checkbox" name="is_wicket" id="is_wicket" value="1"
                        class="h-4 w-4 text-red-600 focus:ring-red-500">
                    Wicket
                </label>
                {{-- Wicket Mode Dropdown (conditionally shown) --}}
                <div id="wicket_mode_container" class="mt-2 hidden">
                    <label for="dismissal_type" class="block text-sm font-medium text-gray-700">Dismissal Type</label>
                    <select name="dismissal_type" id="dismissal_type"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">-- Select Dismissal Type --</option>
                        <option value="caught">Caught</option>
                        <option value="bowled">Bowled</option>
                        <option value="lbw">LBW</option>
                        <option value="run_out">Run Out</option>
                        <option value="stumped">Stumped</option>
                        <option value="caught_and_bowled">Caught and Bowled</option>
                        <option value="hit_wicket">Hit Wicket</option>
                        <option value="obstructing_the_field">Obstructing the Field</option>
                        <option value="handled_the_ball">Handled the Ball</option>
                        <option value="timed_out">Timed Out</option>
                    </select>
                </div>
            </div>
            <input type="hidden" name="current_striker_user_id"
                value="{{ session('current_striker_id_' . $match->id) ?? '' }}">
            <input type="hidden" name="current_non_striker_user_id"
                value="{{ session('current_non_striker_id_' . $match->id) ?? '' }}">
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
        // --- Wicket Mode Toggle Logic ---
        const wicketCheckbox = document.getElementById('is_wicket');
        const wicketModeContainer = document.getElementById('wicket_mode_container');
        const dismissalTypeSelect = document.getElementById('dismissal_type');
        const fielderSelectContainer = document.getElementById(
            'fielder_select_container'); // New container for fielder dropdown
        const fielderSelect = document.getElementById('fielder_id'); // New fielder select element
        function toggleWicketModeDropdown() {
            if (wicketCheckbox.checked) {
                wicketModeContainer.classList.remove('hidden');
                dismissalTypeSelect.setAttribute('required', 'required');
            } else {
                wicketModeContainer.classList.add('hidden');
                dismissalTypeSelect.removeAttribute('required');
                dismissalTypeSelect.value = ''; // Reset value
            }
        }

        wicketCheckbox.addEventListener('change', toggleWicketModeDropdown);

        // Initial setup for wicket checkbox on page load
        document.addEventListener('DOMContentLoaded', toggleWicketModeDropdown);

        // --- Striker/Non-Striker Synchronization Logic (Enhanced for Odd Runs) ---
        const strikerSelect = document.getElementById(
            'batsman_id'); // Renamed for clarity if you use 'batsman_id' as striker
        const nonStrikerSelect = document.getElementById('non_striker_id');
        const runRadios = document.querySelectorAll('input[name="runs"]');

        function toggleWicketModeAndFielder() {
            const isWicket = wicketCheckbox.checked;
            const selectedDismissalType = dismissalTypeSelect.value;

            if (isWicket) {
                wicketModeContainer.classList.remove('hidden');
                dismissalTypeSelect.setAttribute('required', 'required');

                // Show fielder dropdown for relevant dismissal types
                if (selectedDismissalType === 'caught' || selectedDismissalType === 'run_out' || selectedDismissalType ===
                    'stumped' || selectedDismissalType === 'caught_and_bowled') {
                    fielderSelectContainer.classList.remove('hidden');
                    fielderSelect.setAttribute('required', 'required');
                } else {
                    fielderSelectContainer.classList.add('hidden');
                    fielderSelect.removeAttribute('required');
                    fielderSelect.value = ''; // Reset value
                }
            } else {
                wicketModeContainer.classList.add('hidden');
                dismissalTypeSelect.removeAttribute('required');
                dismissalTypeSelect.value = ''; // Reset value

                fielderSelectContainer.classList.add('hidden');
                fielderSelect.removeAttribute('required');
                fielderSelect.value = ''; // Reset value
            }
        }


        wicketCheckbox.addEventListener('change', toggleWicketModeAndFielder);
        dismissalTypeSelect.addEventListener('change', toggleWicketModeAndFielder);

        // Initial setup for wicket checkbox and fielder dropdown on page load
        document.addEventListener('DOMContentLoaded', toggleWicketModeAndFielder);


        function updateNonStrikerOptions() {
            const selectedStrikerValue = strikerSelect.value;

            // Reset all non-striker options first
            Array.from(nonStrikerSelect.options).forEach(option => {
                option.disabled = false;
                option.style.display = ''; // Ensure they are visible
            });

            // If a striker is selected, disable that option in the non-striker dropdown
            if (selectedStrikerValue) {
                const strikerOptionElement = nonStrikerSelect.querySelector(`option[value="${selectedStrikerValue}"]`);
                if (strikerOptionElement) {
                    strikerOptionElement.disabled = true;
                    strikerOptionElement.style.display = 'none'; // Hide it visually
                }
            }

            // Ensure the currently selected non-striker is valid
            if (nonStrikerSelect.value === selectedStrikerValue) {
                nonStrikerSelect.value = ''; // Reset if it's the same as striker
                nonStrikerSelect.dispatchEvent(new Event('change')); // Trigger any change listeners if needed
            }
        }

        function updateStrikerOptions() {
            const selectedNonStrikerValue = nonStrikerSelect.value;

            // Reset all striker options first
            Array.from(strikerSelect.options).forEach(option => {
                option.disabled = false;
                option.style.display = ''; // Ensure they are visible
            });

            // If a non-striker is selected, disable that option in the striker dropdown
            if (selectedNonStrikerValue) {
                const nonStrikerOptionElement = strikerSelect.querySelector(`option[value="${selectedNonStrikerValue}"]`);
                if (nonStrikerOptionElement) {
                    nonStrikerOptionElement.disabled = true;
                    nonStrikerOptionElement.style.display = 'none'; // Hide it visually
                }
            }

            // Ensure the currently selected striker is valid
            if (strikerSelect.value === selectedNonStrikerValue) {
                strikerSelect.value = ''; // Reset if it's the same as non-striker
                strikerSelect.dispatchEvent(new Event('change')); // Trigger any change listeners if needed
            }
        }
        // Function to swap striker and non-striker
        function swapStrikerAndNonStriker() {
            const currentStriker = strikerSelect.value;
            const currentNonStriker = nonStrikerSelect.value;

            strikerSelect.value = currentNonStriker;
            nonStrikerSelect.value = currentStriker;

            // Re-apply constraints after swapping
            updateNonStrikerOptions();
            updateStrikerOptions();
        }

        // Event listeners for when striker or non-striker changes
        strikerSelect.addEventListener('change', updateNonStrikerOptions);
        nonStrikerSelect.addEventListener('change', updateStrikerOptions);

        // Add listeners for run radio buttons
        runRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                const runsScored = parseInt(this.value);
                // Check if it's an odd number of runs (1, 3, 5)
                if (runsScored > 0 && runsScored % 2 !== 0) {
                    swapStrikerAndNonStriker();
                }
            });
        });


        // --- Form Submission Logic ---
        document.getElementById('ajaxBallForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = e.target;
            const formData = new FormData(form);

            // Disable submit button to prevent double submission
            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = 'Submitting...';

            fetch("{{ route('admin.balls.ajaxStore', $match->id) }}", {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // form.reset(); // Resets the form fields to their default states
                        toggleWicketModeDropdown(); // Hide wicket mode if it was shown and form reset
                        // Reset striker/non-striker indications if necessary after reset
                        // You'll need backend logic to determine the *next* striker/non-striker.
                        // For now, we just reset the form.
                        refreshOverSummary(); // Refresh the score summary
                        const isWicket = wicketCheckbox.checked;
                        if (isWicket) {
                            window.location.reload(); // Reload the page to reset state}
                        }
                    } else {
                        alert(data.error || 'Error saving ball!');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Unexpected error! Check console.');
                })
                .finally(() => {
                    // Re-enable submit button
                    submitButton.disabled = false;
                    submitButton.innerHTML = '✅ Submit Ball';
                });
        });

        // Function to refresh the over summary
        function refreshOverSummary() {
            fetch("{{ route('admin.balls.summary', $match) }}")
                .then(res => res.text())
                .then(html => {
                    document.getElementById('overSummary').innerHTML = html;
                })
                .catch(err => {
                    console.error('Error refreshing over summary:', err);
                });
        }

        // Function to undo a ball
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
                })
                .catch(err => {
                    console.error('Error undoing ball:', err);
                    alert('Error undoing ball!');
                });
        }
    </script>
@endsection
