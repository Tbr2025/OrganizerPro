@extends('backend.layouts.app')

@section('title')
    {{ $breadcrumbs['title'] }} | {{ config('app.name') }}
@endsection

@section('admin-content')
    <div class="p-4 mx-auto max-w-7xl md:p-6">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

        {!! ld_apply_filters('users_after_breadcrumbs', '') !!}

        <div class="space-y-6">
            {{-- Player Summary --}}
            @if($player)
            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Player Summary</h3>
                    <div class="flex flex-wrap gap-1.5">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $player->status === 'approved' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : ($player->status === 'rejected' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400') }}">
                            Player: {{ ucfirst($player->status ?? 'pending') }}
                        </span>
                        @if($user->hasRole('Team Manager'))
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400">
                                Team Manager
                            </span>
                        @endif
                    </div>
                </div>
                <div class="p-5 sm:p-6 space-y-6">
                    {{-- Player Profile Card --}}
                    <div class="flex items-start gap-5">
                        @if($player->image_path)
                        <img src="{{ Storage::url($player->image_path) }}" class="w-20 h-24 object-cover rounded-lg bg-gray-100 dark:bg-gray-700" />
                        @else
                        <div class="w-20 h-24 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                            <svg class="w-10 h-12 text-gray-400" fill="currentColor" viewBox="0 0 24 32"><ellipse cx="12" cy="8" rx="5" ry="6"/><path d="M2 28c0-6 4-10 10-10s10 4 10 10"/></svg>
                        </div>
                        @endif
                        <div class="flex-1 grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-2 text-sm">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Name</span>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $player->name }}</p>
                            </div>
                            @if($player->jersey_number)
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Jersey #</span>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $player->jersey_number }}</p>
                            </div>
                            @endif
                            @if($player->player_type)
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Type</span>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $player->player_type->type }}</p>
                            </div>
                            @endif
                            @if($player->batting_profile)
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Batting</span>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $player->batting_profile->name }}</p>
                            </div>
                            @endif
                            @if($player->bowling_profile)
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Bowling</span>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $player->bowling_profile->name }}</p>
                            </div>
                            @endif
                            @if($player->actualTeam)
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Team</span>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $player->actualTeam->name }}</p>
                            </div>
                            @endif
                            @if($player->is_wicket_keeper)
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Role</span>
                                <p class="font-medium text-gray-900 dark:text-white">Wicket Keeper</p>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Team Assignments --}}
                    @if($player->actualTeamAssignments->isNotEmpty())
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Team Assignments</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($player->actualTeamAssignments as $team)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                {{ $team->name }}
                                @if($team->pivot->role)
                                <span class="text-blue-500 dark:text-blue-400">({{ $team->pivot->role }})</span>
                                @endif
                            </span>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Tournaments --}}
                    @if($playerTournaments->isNotEmpty())
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Tournaments</h4>
                        <div class="space-y-2">
                            @foreach($playerTournaments as $tournament)
                            @php
                                $hasPlayerAssignment = $player->actualTeamAssignments->contains(fn($t) => $t->pivot->tournament_id == $tournament->id);
                                $stat = $playerStats->firstWhere('tournament_id', $tournament->id);
                            @endphp
                            <div class="flex items-center justify-between p-3 rounded-lg border {{ $hasPlayerAssignment ? 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50' : 'border-yellow-300 dark:border-yellow-700 bg-yellow-50 dark:bg-yellow-900/20' }}">
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $tournament->name }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $tournament->status === 'completed' ? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' : ($tournament->status === 'active' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400') }}">
                                        {{ ucfirst($tournament->status) }}
                                    </span>
                                    @if($stat)
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $stat->matches }} match{{ $stat->matches !== 1 ? 'es' : '' }}</span>
                                    @endif
                                </div>
                                <div>
                                    @if(!$hasPlayerAssignment)
                                    <span class="inline-flex items-center gap-1 text-xs text-yellow-600 dark:text-yellow-400 font-medium">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                        Not assigned via roster
                                    </span>
                                    @else
                                    <span class="text-xs text-green-600 dark:text-green-400">Assigned</span>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Tournament Stats --}}
                    @if($playerStats->isNotEmpty())
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Tournament Statistics</h4>
                        <div class="space-y-4">
                            @foreach($playerStats as $stat)
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 dark:bg-gray-800/50">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-sm text-gray-800 dark:text-white">{{ $stat->tournament?->name ?? 'Unknown Tournament' }}</span>
                                        @if($stat->team)
                                        <span class="text-xs text-gray-500 dark:text-gray-400">- {{ $stat->team->name }}</span>
                                        @endif
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $stat->matches ?? 0 }} match{{ ($stat->matches ?? 0) !== 1 ? 'es' : '' }}</span>
                                </div>
                                <div class="p-4">
                                    {{-- Batting --}}
                                    @if($stat->innings_batted > 0)
                                    <div class="mb-3">
                                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Batting</p>
                                        <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">
                                            <div class="text-center p-2 rounded bg-gray-50 dark:bg-gray-800/50">
                                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stat->runs }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Runs</p>
                                            </div>
                                            <div class="text-center p-2 rounded bg-gray-50 dark:bg-gray-800/50">
                                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stat->innings_batted }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Innings</p>
                                            </div>
                                            <div class="text-center p-2 rounded bg-gray-50 dark:bg-gray-800/50">
                                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stat->highest_score_display }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Highest</p>
                                            </div>
                                            <div class="text-center p-2 rounded bg-gray-50 dark:bg-gray-800/50">
                                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stat->batting_average !== null ? number_format($stat->batting_average, 2) : '-' }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Average</p>
                                            </div>
                                            <div class="text-center p-2 rounded bg-gray-50 dark:bg-gray-800/50">
                                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stat->strike_rate > 0 ? number_format($stat->strike_rate, 1) : '-' }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">SR</p>
                                            </div>
                                            <div class="text-center p-2 rounded bg-gray-50 dark:bg-gray-800/50">
                                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stat->fours }}/{{ $stat->sixes }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">4s/6s</p>
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    {{-- Bowling --}}
                                    @if($stat->innings_bowled > 0)
                                    <div class="mb-3">
                                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Bowling</p>
                                        <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">
                                            <div class="text-center p-2 rounded bg-gray-50 dark:bg-gray-800/50">
                                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stat->wickets }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Wickets</p>
                                            </div>
                                            <div class="text-center p-2 rounded bg-gray-50 dark:bg-gray-800/50">
                                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stat->overs_bowled }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Overs</p>
                                            </div>
                                            <div class="text-center p-2 rounded bg-gray-50 dark:bg-gray-800/50">
                                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stat->runs_conceded }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Conceded</p>
                                            </div>
                                            <div class="text-center p-2 rounded bg-gray-50 dark:bg-gray-800/50">
                                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stat->bowling_average !== null ? number_format($stat->bowling_average, 2) : '-' }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Average</p>
                                            </div>
                                            <div class="text-center p-2 rounded bg-gray-50 dark:bg-gray-800/50">
                                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stat->economy_rate !== null ? number_format($stat->economy_rate, 2) : '-' }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Econ</p>
                                            </div>
                                            <div class="text-center p-2 rounded bg-gray-50 dark:bg-gray-800/50">
                                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stat->best_bowling ?: '-' }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Best</p>
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    {{-- Fielding --}}
                                    @if(($stat->catches + $stat->stumpings + $stat->run_outs) > 0)
                                    <div>
                                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Fielding</p>
                                        <div class="grid grid-cols-3 gap-3">
                                            <div class="text-center p-2 rounded bg-gray-50 dark:bg-gray-800/50">
                                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stat->catches }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Catches</p>
                                            </div>
                                            <div class="text-center p-2 rounded bg-gray-50 dark:bg-gray-800/50">
                                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stat->stumpings }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Stumpings</p>
                                            </div>
                                            <div class="text-center p-2 rounded bg-gray-50 dark:bg-gray-800/50">
                                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $stat->run_outs }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Run Outs</p>
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    {{-- No stats yet --}}
                                    @if(($stat->innings_batted ?? 0) === 0 && ($stat->innings_bowled ?? 0) === 0)
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No batting/bowling stats recorded yet.</p>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">No tournament statistics available.</p>
                    @endif
                </div>
            </div>
            @endif

            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="p-5 space-y-6 border-t border-gray-100 dark:border-gray-800 sm:p-6">
                    <form action="{{ route('admin.users.update', $user->id) }}" method="POST" class="space-y-6"
                        enctype="multipart/form-data">
                        @method('PUT')
                        @csrf
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div class="space-y-1">
                                <label for="name"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Full Name') }}</label>
                                <input type="text" name="name" id="name" required value="{{ $user->name }}"
                                    placeholder="{{ __('Enter Full Name') }}" class="form-control">
                            </div>
                            <div class="space-y-1">
                                <label for="email"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('User Email') }}</label>
                                <input type="email" name="email" id="email" required value="{{ $user->email }}"
                                    placeholder="{{ __('Enter Email') }}" class="form-control">
                            </div>
                            <div>
                                <x-inputs.password name="password" label="{{ __('Password (Optional)') }}"
                                    placeholder="{{ __('Enter Password') }}" />
                            </div>
                            <div>
                                <x-inputs.password name="password_confirmation"
                                    label="{{ __('Confirm Password (Optional)') }}"
                                    placeholder="{{ __('Confirm Password') }}" />
                            </div>
                            <div>
                                <x-inputs.combobox name="roles[]" label="{{ __('Assign Roles') }}"
                                    placeholder="{{ __('Select Roles') }}" :options="collect($roles)
                                        ->map(fn($name, $id) => ['value' => $name, 'label' => ucfirst($name)])
                                        ->values()
                                        ->toArray()" :selected="$user->roles->pluck('name')->toArray()"
                                    :multiple="true" :searchable="false" />
                            </div>
                            <div class="space-y-1">
                                <label for="username"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Username') }}</label>

                                <input type="text" name="username" id="username" required value="{{ $user->username }}"
                                    placeholder="{{ __('Enter Username') }}" class="form-control">
                            </div>
                            {!! ld_apply_filters('after_username_field', '', $user) !!}
                        </div>
                        <div class="mt-6">
                            <x-buttons.submit-buttons cancelUrl="{{ route('admin.users.index') }}" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
