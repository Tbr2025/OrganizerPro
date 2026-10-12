{{--
    The match format, set in the scoring area before the first ball: innings each,
    overs, players a side, bowler limit, powerplays. Open until play starts, then
    folded to one line (still editable — rain, a late arrival — never below what
    has been played; MatchesController::updateFormat checks).
--}}
@php
    $fmtOvers = (int) old('overs', $match->overs ?: 20);
    $fmtInnings = (int) old('innings_per_team', $match->innings_per_team ?: 1);
    $fmtPlayers = old('players_per_team', $match->players_per_team);
    $fmtBowler = old('bowler_overs_limit', $match->bowler_overs_limit);
    $fmtPowerplays = array_values(old('powerplays', $match->powerplays ?? []));
    $fmtStarted = $scoringStarted ?? false;
    $fmtSummary = collect([
        $fmtOvers . ($fmtOvers === 1 ? ' over' : ' overs'),
        $fmtInnings === 1 ? '1 innings each' : $fmtInnings . ' innings each',
        ($match->players_per_team ?: 11) . ' a side',
        'bowler max ' . $bowlerQuota,
        collect($match->powerplays ?? [])->map(fn ($p) => 'PP ' . $p['from'] . '–' . $p['to'])->implode(', ') ?: null,
    ])->filter()->implode(' · ');
    $fmtHasErrors = $errors->hasAny(['overs', 'innings_per_team', 'players_per_team', 'bowler_overs_limit', 'powerplays.*']);
@endphp
<details id="matchFormat" class="mb-4 rounded-xl border {{ $fmtStarted ? 'border-gray-200 dark:border-gray-700' : 'border-indigo-300 dark:border-indigo-700 bg-indigo-50/60 dark:bg-indigo-900/20' }}" {{ ! $fmtStarted || $fmtHasErrors ? 'open' : '' }}>
    <summary class="cursor-pointer select-none px-4 py-3 flex flex-wrap items-center gap-2 text-sm">
        <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $fmtStarted ? 'Match format' : 'Set up the match before scoring' }}</span>
        <span class="text-gray-500 dark:text-gray-400">{{ $fmtSummary }}</span>
        <span class="ml-auto text-xs text-indigo-600 dark:text-indigo-400">{{ $fmtStarted ? 'Change' : '' }}</span>
    </summary>
    <form method="POST" action="{{ route('admin.matches.format', $match) }}" class="px-4 pb-4 pt-1 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
        @csrf
        <label class="flex flex-col gap-1">
            <span class="text-gray-600 dark:text-gray-300">Innings per team</span>
            <select name="innings_per_team" class="form-control">
                @foreach(range(1, \App\Services\Scoring\MatchStateService::MAX_INNINGS_PER_TEAM) as $n)
                    <option value="{{ $n }}" @selected($fmtInnings === $n)>{{ $n }}{{ $n === 1 ? ' (limited overs)' : ($n === 2 ? ' (two-innings match)' : '') }}</option>
                @endforeach
            </select>
            @error('innings_per_team')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-gray-600 dark:text-gray-300">Overs per innings</span>
            <input type="number" name="overs" min="1" max="90" required value="{{ $fmtOvers }}" class="form-control">
            @error('overs')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-gray-600 dark:text-gray-300">Players per team</span>
            <input type="number" name="players_per_team" min="2" max="15" value="{{ $fmtPlayers }}" placeholder="11" class="form-control">
            @error('players_per_team')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-gray-600 dark:text-gray-300">Max overs per bowler</span>
            <input type="number" name="bowler_overs_limit" min="1" max="90" value="{{ $fmtBowler }}" placeholder="{{ (int) ceil($fmtOvers / 5) }} (standard)" class="form-control">
            @error('bowler_overs_limit')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
        </label>

        <fieldset class="col-span-2 md:col-span-4">
            <legend class="text-gray-600 dark:text-gray-300 mb-1">Powerplay overs <span class="text-xs text-gray-400">— e.g. 1 to 6; leave blank for none</span></legend>
            <div class="flex flex-wrap gap-3">
                @foreach(range(0, 2) as $k)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="text-xs text-gray-500">P{{ $k + 1 }}</span>
                        <input type="number" name="powerplays[{{ $k }}][from]" min="1" max="90" value="{{ $fmtPowerplays[$k]['from'] ?? '' }}" placeholder="from" class="form-control !py-1 w-20" aria-label="Powerplay {{ $k + 1 }} from over">
                        <span class="text-gray-400">to</span>
                        <input type="number" name="powerplays[{{ $k }}][to]" min="1" max="90" value="{{ $fmtPowerplays[$k]['to'] ?? '' }}" placeholder="to" class="form-control !py-1 w-20" aria-label="Powerplay {{ $k + 1 }} to over">
                    </span>
                @endforeach
            </div>
            @error('powerplays.*')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
        </fieldset>

        <p class="col-span-2 md:col-span-3 text-xs text-gray-500 dark:text-gray-400">
            Two innings each: the sides bat in turn and the total of both innings decides it. Players per team sets all out (11 a side = 10 wickets); blank uses the named line-up, else 11. Max overs per bowler is a fifth of the overs when blank.
        </p>
        <div class="col-span-2 md:col-span-1 flex md:justify-end items-end">
            <button class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">{{ $fmtStarted ? 'Save format' : 'Save & start scoring' }}</button>
        </div>
    </form>
</details>
