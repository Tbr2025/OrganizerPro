<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Submitted - {{ $team->name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    @php
        $primaryColor = $team->tournament?->settings?->primary_color ?? '#f59e0b';
        $secondaryColor = $team->tournament?->settings?->secondary_color ?? '#1f2937';
    @endphp
    <style>
        body { font-family: 'Roboto', sans-serif; }
        h1, h2, h3 { font-family: 'Oswald', sans-serif; }
        .brand-text { color: {{ $primaryColor }}; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-xl mx-auto px-4 py-16">
        <div class="bg-gray-800 rounded-xl p-8 text-center border border-gray-700">
            {{-- Success Icon --}}
            <div class="mb-6">
                <div class="w-20 h-20 bg-green-600 rounded-full mx-auto flex items-center justify-center">
                    <i class="fas fa-check text-4xl text-white"></i>
                </div>
            </div>

            {{-- Message --}}
            <h1 class="text-2xl font-bold mb-4 text-green-400">Registration Submitted!</h1>
            <p class="text-gray-400 mb-6">
                Thank you for registering to join <strong class="text-white">{{ $team->name }}</strong>
                @if($team->tournament)
                    for <strong class="text-white">{{ $team->tournament->name }}</strong>.
                @endif
            </p>

            {{-- What's Next --}}
            <div class="bg-gray-700 rounded-lg p-4 mb-6 text-left">
                <h2 class="font-semibold mb-3 brand-text">What happens next?</h2>
                <ul class="text-sm text-gray-300 space-y-2">
                    <li class="flex items-start gap-2">
                        <i class="fas fa-clock text-gray-500 mt-1"></i>
                        <span>Your registration is pending approval from the team manager.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-user-check text-gray-500 mt-1"></i>
                        <span>The team manager will review and verify your registration.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-envelope text-gray-500 mt-1"></i>
                        <span>You will receive an email once your registration is approved.</span>
                    </li>
                </ul>
            </div>

            @if($team->tournament?->slug)
                <a href="{{ route('public.tournament.show', $team->tournament->slug) }}"
                   class="inline-block bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg transition">
                    View Tournament
                </a>
            @endif
        </div>

        <div class="text-center mt-6">
            <p class="text-gray-600 text-sm">
                Powered by <span class="brand-text">{{ config('app.name') }}</span>
            </p>
        </div>
    </div>
</body>
</html>
