<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $player->name }} - Player Dashboard</title>

    <meta name="description" content="{{ $player->name }} - Cricket Player Statistics">
    <meta property="og:type" content="profile" />
    <meta property="og:title" content="{{ $player->name }}" />
    <meta property="og:description" content="View cricket statistics for {{ $player->name }}" />
    @if($player->image)
        <meta property="og:image" content="{{ Storage::url($player->image) }}" />
    @endif

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        body { font-family: 'Roboto', sans-serif; }
        h1, h2, h3 { font-family: 'Oswald', sans-serif; }
    </style>
</head>

<body class="bg-gray-900 text-gray-100 min-h-screen">
    {{-- Header --}}
    <header class="bg-gray-800 shadow-lg">
        <div class="max-w-5xl mx-auto px-4 py-4">
            <h1 class="text-xl font-bold">Player Dashboard</h1>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8">
        {{-- Player Profile Card --}}
        <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-xl overflow-hidden border border-gray-700 mb-8">
            <div class="md:flex">
                {{-- Player Image --}}
                <div class="md:w-1/3 p-6 flex items-center justify-center bg-gray-800">
                    @if($player->image)
                        <img src="{{ Storage::url($player->image) }}" alt="{{ $player->name }}"
                             class="w-48 h-48 rounded-full object-cover border-4 border-yellow-500 shadow-xl">
                    @else
                        <div class="w-48 h-48 rounded-full bg-gray-700 flex items-center justify-center border-4 border-yellow-500">
                            <i class="fas fa-user text-6xl text-gray-500"></i>
                        </div>
                    @endif
                </div>

                {{-- Player Info --}}
                <div class="md:w-2/3 p-6">
                    <h1 class="text-3xl font-bold mb-2">{{ $player->name }}</h1>
                    @if($player->jersey_name)
                        <p class="text-yellow-400 text-lg mb-4">"{{ $player->jersey_name }}"</p>
                    @endif

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        @if($player->playerType)
                            <div>
                                <span class="text-gray-400">Role:</span>
                                <span class="ml-2">{{ $player->playerType->name }}</span>
                            </div>
                        @endif
                        @if($player->battingProfile)
                            <div>
                                <span class="text-gray-400">Batting:</span>
                                <span class="ml-2">{{ $player->battingProfile->name }}</span>
                            </div>
                        @endif
                        @if($player->bowlingProfile)
                            <div>
                                <span class="text-gray-400">Bowling:</span>
                                <span class="ml-2">{{ $player->bowlingProfile->name }}</span>
                            </div>
                        @endif
                        @if($player->actualTeam)
                            <div>
                                <span class="text-gray-400">Team:</span>
                                <span class="ml-2">{{ $player->actualTeam->name }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Career Stats Overview --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-gray-800 rounded-xl p-4 text-center border border-gray-700">
                <p class="text-3xl font-bold text-yellow-400">{{ $careerStats['matches'] }}</p>
                <p class="text-gray-400 text-sm">Matches</p>
            </div>
            <div class="bg-gray-800 rounded-xl p-4 text-center border border-gray-700">
                <p class="text-3xl font-bold text-green-400">{{ $careerStats['runs'] }}</p>
                <p class="text-gray-400 text-sm">Runs</p>
            </div>
            <div class="bg-gray-800 rounded-xl p-4 text-center border border-gray-700">
                <p class="text-3xl font-bold text-blue-400">{{ $careerStats['wickets'] }}</p>
                <p class="text-gray-400 text-sm">Wickets</p>
            </div>
            <div class="bg-gray-800 rounded-xl p-4 text-center border border-gray-700">
                <p class="text-3xl font-bold text-purple-400">{{ $careerStats['catches'] }}</p>
                <p class="text-gray-400 text-sm">Catches</p>
            </div>
        </div>

        {{-- Detailed Stats --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            {{-- Batting Stats --}}
            <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700">
                <div class="bg-gray-700 px-4 py-3">
                    <h2 class="font-semibold flex items-center gap-2">
                        <i class="fas fa-baseball-ball text-yellow-400"></i> Batting
                    </h2>
                </div>
                <div class="p-4 space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Total Runs</span>
                        <span class="font-bold">{{ $careerStats['runs'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Highest Score</span>
                        <span class="font-bold">{{ $careerStats['highest_score'] ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Fifties</span>
                        <span class="font-bold">{{ $careerStats['fifties'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Hundreds</span>
                        <span class="font-bold">{{ $careerStats['hundreds'] }}</span>
                    </div>
                </div>
            </div>

            {{-- Bowling Stats --}}
            <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700">
                <div class="bg-gray-700 px-4 py-3">
                    <h2 class="font-semibold flex items-center gap-2">
                        <i class="fas fa-bowling-ball text-blue-400"></i> Bowling
                    </h2>
                </div>
                <div class="p-4 space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Total Wickets</span>
                        <span class="font-bold">{{ $careerStats['wickets'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Best Bowling</span>
                        <span class="font-bold">{{ $careerStats['best_bowling'] ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tournament-wise Statistics --}}
        @if($statistics->count() > 0)
            <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700 mb-8">
                <div class="bg-gray-700 px-4 py-3">
                    <h2 class="font-semibold">Tournament Statistics</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-750">
                            <tr>
                                <th class="px-4 py-3 text-left">Tournament</th>
                                <th class="px-4 py-3 text-left">Team</th>
                                <th class="px-4 py-3 text-center">M</th>
                                <th class="px-4 py-3 text-center">Runs</th>
                                <th class="px-4 py-3 text-center">HS</th>
                                <th class="px-4 py-3 text-center">Wkts</th>
                                <th class="px-4 py-3 text-center">BB</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @foreach($statistics as $stat)
                                <tr class="hover:bg-gray-700/50">
                                    <td class="px-4 py-3">{{ $stat->tournament?->name ?? 'Unknown' }}</td>
                                    <td class="px-4 py-3 text-gray-400">{{ $stat->team?->short_name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->matches }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->runs }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->highest_score ?? '-' }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->wickets }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->best_bowling ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Awards --}}
        @if($awards->count() > 0)
            <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700 mb-8">
                <div class="bg-gray-700 px-4 py-3">
                    <h2 class="font-semibold flex items-center gap-2">
                        <i class="fas fa-trophy text-yellow-400"></i> Awards
                    </h2>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($awards as $award)
                            <div class="bg-gray-700/50 rounded-lg p-4 flex items-center gap-4">
                                <div class="h-12 w-12 bg-yellow-500/20 rounded-full flex items-center justify-center">
                                    <i class="fas fa-medal text-yellow-400 text-xl"></i>
                                </div>
                                <div>
                                    <p class="font-medium">{{ $award->tournamentAward?->name ?? 'Award' }}</p>
                                    <p class="text-sm text-gray-400">
                                        {{ $award->match?->teamA?->short_name ?? '' }} vs {{ $award->match?->teamB?->short_name ?? '' }}
                                        @if($award->match?->tournament)
                                            - {{ $award->match->tournament->name }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Appreciations/Gallery --}}
        @if($appreciations->count() > 0)
            <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700 mb-8">
                <div class="bg-gray-700 px-4 py-3">
                    <h2 class="font-semibold flex items-center gap-2">
                        <i class="fas fa-images text-purple-400"></i> Gallery
                    </h2>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @foreach($appreciations as $appreciation)
                            @if($appreciation->image_path)
                                <a href="{{ Storage::url($appreciation->image_path) }}" target="_blank">
                                    <img src="{{ Storage::url($appreciation->image_path) }}" alt="Appreciation"
                                         class="w-full rounded-lg hover:opacity-90 transition">
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Share Button --}}
        <div class="text-center">
            <button onclick="shareProfile()" class="px-6 py-3 bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold rounded-lg transition">
                <i class="fas fa-share mr-2"></i> Share Profile
            </button>
        </div>
    </main>

    {{-- Footer --}}
    <footer class="bg-gray-800 mt-12 py-6 text-center">
        <p class="text-gray-500 text-sm">Powered by OrganizerPro</p>
    </footer>

    <script>
        function shareProfile() {
            if (navigator.share) {
                navigator.share({
                    title: '{{ $player->name }} - Player Statistics',
                    text: 'Check out the cricket statistics for {{ $player->name }}',
                    url: window.location.href
                });
            } else {
                navigator.clipboard.writeText(window.location.href);
                alert('Link copied to clipboard!');
            }
        }
    </script>
</body>

</html>
