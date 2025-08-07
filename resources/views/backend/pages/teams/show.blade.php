@extends('backend.layouts.app')

@section('title')
    {{ $breadcrumbs['title'] }} | {{ config('app.name') }}
@endsection

@section('admin-content')
    <div class="p-4 mx-auto max-w-screen-xl md:p-6">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

        <div class="space-y-6">
            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-5 space-y-6 sm:p-6">

                    {{-- Team Info --}}
                    <div class="text-xl font-semibold">{{ $team->name }}</div>
                    @if ($team->logo)
                        <img src="{{ asset('storage/' . $team->logo) }}" alt="Logo"
                            class="w-24 h-24 object-cover my-2 rounded border">
                    @endif

                    {{-- Add Player to Team --}}
                    <h2 class="text-lg font-bold mt-6">Add Player to Team</h2>

                    <form action="{{ route('admin.teams.addPlayer', $team->id) }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="tournament_id" value="{{ $team->tournament_id }}">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="player_id" class="form-label">Player</label>
                                <select name="player_id" id="player_id" class="form-control" required>
                                    <option value="">— Select —</option>
                                    @foreach ($availablePlayers as $player)
                                        <option value="{{ $player->id }}">
                                            {{ $player->name }} ({{ $player->mobile_no }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>


                            <div>
                                <label for="role" class="form-label">Role</label>
                                <select name="role" id="role" class="form-control" required>
                                    <option value="">— Select Role —</option>
                                    @foreach ($playerTypes as $type)
                                        <option value="{{ $type->slug }}">{{ $type->type }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="image" class="form-label">Player Image</label>
                                <input type="file" name="image" id="image" class="form-control" accept="image/*">
                            </div>
                        </div>

                        <div class="mt-4">
                            <x-buttons.submit-buttons cancelUrl="{{ route('admin.teams.index') }}" />
                        </div>
                    </form>
                </div>
            </div>

            {{-- Player Table --}}
            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-5 sm:p-6">
                    <h2 class="text-lg font-bold mb-4">Team Players</h2>

                    @if ($team->players->isEmpty())
                        <p class="text-gray-500">No players assigned to this team.</p>
                    @else
                        <table class="table-auto w-full border border-gray-200">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-4 py-2">Image</th>
                                    <th class="px-4 py-2">Name</th>
                                    <th class="px-4 py-2">Jersey</th>
                                    <th class="px-4 py-2">Mobile</th>
                                    <th class="px-4 py-2">Role</th>
                                    <th class="px-4 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                               
                                @foreach ($team->players as $player)
                                    <tr class="border-t">
                                        <td class="px-4 py-2">
                                            @if ($player->pivot->image_path)
                                                <img src="{{ asset('storage/' . $player->pivot->image_path) }}"
                                                    class="w-10 h-10 rounded-full">
                                            @else
                                                <span class="text-gray-400">N/A</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2">{{ $player->name }}</td>
                                        <td class="px-4 py-2">{{ $player->jersey_name }}</td>
                                        <td class="px-4 py-2">{{ $player->mobile_no }}</td>
                                        <td class="px-4 py-2 capitalize">{{ $player->pivot->role ?? '—' }}</td>
                                        <td class="px-4 py-2">
                                            <form action="{{ route('admin.teams.removePlayer', [$team, $player]) }}"
                                                method="POST"
                                                onsubmit="return confirm('Are you sure you want to remove this player?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:underline">Remove</button>
                                            </form>

                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
