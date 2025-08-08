<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>You Selects - IPL - Player Registration</title>
    <meta name="title" content="You Selects - IPL - Player Registration" />
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="description"
        content="Register now for the IPL-style cricket tournament. Show your talent and get selected!" />

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://sportzley.com/player/register" />
    <meta property="og:title" content="You Selects - IPL - Player Registration" />
    <meta property="og:description"
        content="Register now for the IPL-style cricket tournament. Show your talent and get selected!" />
    <meta property="og:image" content="https://sportzley.com/images/logo/og.jpg" />

    <!-- X (Twitter) -->
    <meta property="twitter:card" content="summary_large_image" />
    <meta property="twitter:url" content="https://sportzley.com/player/register" />
    <meta property="twitter:title" content="You Selects - IPL - Player Registration" />
    <meta property="twitter:description"
        content="Register now for the IPL-style cricket tournament. Show your talent and get selected!" />
    <meta property="twitter:image" content="https://sportzley.com/images/logo/og.jpg" />


    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.0.0/css/flag-icons.min.css" />

    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }

        h1,
        h2 {
            font-family: 'Oswald', sans-serif;
        }

        /* Custom gradient for hero */

        /* Optional: Add a subtle pulse animation for the "Let the Battle Begins" text */
        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        /* Tooltip specific styles */
        .tooltip-container {
            position: relative;
            display: inline-block;
        }

        .tooltip-content {
            position: absolute;
            background-color: #2d3748;
            color: #fff;
            padding: 10px;
            border-radius: 0.375rem;
            z-index: 10;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            width: 550px;
            text-align: left;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #4a5568;
        }

        .tooltip-content::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #2d3748 transparent transparent transparent;
        }

        /* Custom checkbox style if @tailwindcss/forms is not used or to override default */
        input[type="checkbox"] {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            display: inline-block;
            vertical-align: middle;
            height: 1.25rem;
            width: 1.25rem;
            border-radius: 0.25rem;
            border: 2px solid #a0aec0;
            background-color: #2d3748;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }

        input[type="checkbox"]:checked {
            background-color: #f6e05e;
            border-color: #f6e05e;
            background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z'/%3e%3c/svg%3e");
            background-size: 100% 100%;
            background-position: center;
            background-repeat: no-repeat;
        }

        input[type="checkbox"]:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(252, 211, 77, 0.5);
        }

        /* Styles for the custom dropdown */
        .custom-select-container {
            position: relative;
            font-family: 'Roboto', sans-serif;
            width: 100%;
        }

        .custom-select-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #fff;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            height: 42px;
            color: #000;
        }

        .custom-select-trigger.focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 1px #3b82f6;
        }

        .custom-options-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background-color: #fff;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 20;
            max-height: 250px;
            overflow-y: auto;
            color: #000;
            margin-top: 4px;
        }

        .custom-option {
            display: flex;
            align-items: center;
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            color: #000;
        }

        .custom-option:hover {
            background-color: #f3f4f6;
        }

        .custom-option.selected {
            background-color: #e5e7eb;
            font-weight: 500;
        }

        .flag-icon {
            margin-right: 0.5rem;
            flex-shrink: 0;
        }

        .country-search-input {
            width: calc(100% - 1rem);
            /* Adjusted for padding */
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            margin: 0.5rem;
            color: #000;
        }


        @media screen and (max-width: 767px) {
            .tooltip-content {
                position: relative;
                width: 100%;
                max-width: 100%;
                padding: 1rem;
                /* Reduce padding slightly */
                font-size: 0.875rem;
            }
        }
    </style>
    <meta name="csrf-token" content="{{ csrf_token() }}">

</head>

<body class="bg-gray-900 text-gray-100">
    <div id="toast-success" x-data="{ show: false, message: '' }" x-show="show" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform scale-90" x-transition:enter-end="opacity-100 transform scale-100"
        x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-90"
        class="fixed top-5 right-5 z-50 flex items-center w-full max-w-xs p-4 mb-4 text-gray-500 bg-white rounded-lg shadow dark:text-gray-400 dark:bg-gray-800"
        role="alert">
        <div
            class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 text-green-500 bg-green-100 rounded-lg dark:bg-green-800 dark:text-green-200">
            <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor"
                viewBox="0 0 20 20">
                <path
                    d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
            </svg>
            <span class="sr-only">Success icon</span>
        </div>
        <div class="ml-3 text-sm font-normal" x-text="message"></div>
        <button type="button" @click="show = false"
            class="ml-auto -mx-1.5 -my-1.5 bg-white text-gray-400 hover:text-gray-900 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 hover:bg-gray-100 inline-flex items-center justify-center h-8 w-8 dark:text-gray-500 dark:hover:text-white dark:bg-gray-800 dark:hover:bg-gray-700"
            data-dismiss-target="#toast-success" aria-label="Close">
            <span class="sr-only">Close</span>
            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                viewBox="0 0 14 14">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
            </svg>
        </button>
    </div>

    @if (session('success'))
        <div id="session-toast" x-data="{ open: true }" x-show="open" x-init="setTimeout(() => { open = false }, 5000)"
            class="fixed top-5 right-5 z-50 flex items-center w-full max-w-xs p-4 mb-4 text-gray-500 bg-white rounded-lg shadow dark:text-gray-400 dark:bg-gray-800"
            role="alert">
            <div
                class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 text-green-500 bg-green-100 rounded-lg dark:bg-green-800 dark:text-green-200">
                <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor"
                    viewBox="0 0 20 20">
                    <path
                        d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z" />
                </svg>
                <span class="sr-only">Success icon</span>
            </div>
            <div class="ml-3 text-sm font-normal">{{ session('success') }}</div>
            <button type="button" @click="open = false"
                class="ml-auto -mx-1.5 -my-1.5 bg-white text-gray-400 hover:text-gray-900 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 hover:bg-gray-100 inline-flex items-center justify-center h-8 w-8 dark:text-gray-500 dark:hover:text-white dark:bg-gray-800 dark:hover:bg-gray-700"
                aria-label="Close">
                <span class="sr-only">Close</span>
                <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 14 14">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                </svg>
            </button>
        </div>
    @endif
    <section class="bg-cover bg-center h-screen relative"
        style="background-image: url('{{ asset('images/product/banner.jpg') }}');">

        <div class="absolute inset-0 hero-gradient flex items-center justify-center">
            <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-6 max-w-2xl mx-auto">
                <p class="text-lg md:text-xl font-bold uppercase tracking-widest text-yellow-400 mb-2 animate-pulse">
                    Let the Battle Begins
                </p>
                <img src="{{ asset('images/logo/landing.png') }}" alt="IPL Logo"
                    class="mx-auto my-4 w-[250px] h-auto">



                <p class="text-md md:text-lg lg:text-xl mt-4 text-gray-200 max-w-xl mx-auto">
                    The Ultimate Cricket Carnival to be Unleashed
                </p>

                <a href="#registration-form"
                    class="inline-block bg-gradient-to-r from-yellow-500 to-orange-600 hover:from-yellow-600 hover:to-orange-700 text-gray-900 font-bold text-lg px-8 py-4 rounded-full shadow-lg transform transition duration-300 hover:scale-105 mt-8">
                    Register as Player
                </a>
            </div>

        </div>
    </section>


    <section class="py-16 bg-gray-800 text-white">
        <div class="max-w-6xl mx-auto px-4 grid grid-cols-1 md:grid-cols-2 gap-8 text-center">
            <div class="bg-gray-700 p-8 rounded-xl shadow-lg border border-yellow-500">
                <h2 class="text-3xl font-bold mb-3 text-yellow-400">📅 Auction Date</h2>
                <p class="text-xl text-gray-200">15th August 2025</p>
            </div>
            <div class="bg-gray-700 p-8 rounded-xl shadow-lg border border-yellow-500">
                <h2 class="text-3xl font-bold mb-3 text-yellow-400">📅 Tournament Schedule</h2>
                <p class="text-xl text-gray-200">31st August 2025 to 2nd November 2025</p>
            </div>

        </div>
    </section>
    <section class="pb-16 bg-gray-800 text-white">
        <div class="max-w-6xl mx-auto px-4 grid grid-cols-1 md:grid-cols-1 gap-8 text-center">
            <div class="bg-gray-700 p-8 rounded-xl shadow-lg border border-yellow-500">
                <h2 class="text-3xl font-bold mb-3 text-yellow-400">📍 Venue</h2>
                <p class="text-xl text-gray-200">DCS YOU SELECTS ARENA, RAHMANIYAH, SHARJAH</p>
            </div>
        </div>
        </div>
    </section>

    {{-- <section class="py-20 bg-gray-900">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h2 class="text-4xl md:text-5xl font-bold mb-12 text-yellow-400">Why Join The League?</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div
                    class="bg-gray-800 shadow-xl rounded-lg p-7 border border-gray-700 transform hover:scale-105 transition duration-300">
                    <h3 class="text-2xl font-semibold mb-3 text-white">🏆 Player Awards</h3>
                    <p class="text-gray-300">Man of the Match, Best Bowler, Best Batsman & more!</p>
                </div>
                <div
                    class="bg-gray-800 shadow-xl rounded-lg p-7 border border-gray-700 transform hover:scale-105 transition duration-300">
                    <h3 class="text-2xl font-semibold mb-3 text-white">📸 Pro Coverage</h3>
                    <p class="text-gray-300">High-quality photos, live match updates, and full scoreboard sharing.
                    </p>
                </div>
                <div
                    class="bg-gray-800 shadow-xl rounded-lg p-7 border border-gray-700 transform hover:scale-105 transition duration-300">
                    <h3 class="text-2xl font-semibold mb-3 text-white">🤝 Expand Your Network</h3>
                    <p class="text-gray-300">Connect with fellow cricket enthusiasts and form powerful teams.</p>
                </div>
            </div>
        </div>
    </section> --}}

    {{-- Registration Form --}}
    <section
        class="max-w-5xl mx-auto bg-gray-800 p-8 md:p-12 mt-10 mb-20 rounded-xl shadow-2xl border border-yellow-600"
        id="registration-form">
        <h2 class="text-4xl md:text-5xl font-bold text-center mb-8 text-yellow-400">Player Registration</h2>

        @if (session('success'))
            <div
                class="mb-6 p-4 bg-green-700 text-white rounded-lg border border-green-500 text-center text-lg font-medium">
                {{ session('success') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="text-red-600 text-sm text-center">
                <ul id="error-scroll-target" class="list-disc pl-5 mb-4 text-red-600 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form method="POST" action="{{ route('player.register.store') }}"
            class="grid grid-cols-1 md:grid-cols-2 gap-6" id="main-registration-form" enctype="multipart/form-data">
            @csrf

            <div>
                <label for="name" class="block font-semibold mb-1">Full Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                    class="w-full px-3 py-2 border rounded text-black">
                @error('name')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Email Field --}}
            <div>
                <label for="email" class="block font-semibold mb-1">Email Address <span
                        class="text-red-500">*</span> </label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required
                    class="w-full px-3 py-2 border rounded text-black">
                @error('email')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="location_id" class="block font-semibold mb-1">Location <span
                        class="text-red-500">*</span></label>
                <select name="location_id" id="location_id" class="w-full px-3 py-2 border rounded text-black"
                    required>
                    <option value="">Select location</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>
                            {{ $location->name }}
                        </option>
                    @endforeach
                </select>
                @error('location_id')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            {{-- Team Selection Dropdown --}}
            <div x-data="{ selectedTeam: '{{ old('team_id') }}', newTeamName: '{{ old('team_name_ref') }}' }">
                <label for="team_id" class="block font-semibold mb-1">Select Team ( Currently Playing) <span
                        class="text-red-500">*</span> </label>
                <select name="team_id" id="team_id" class="w-full px-3 py-2 border rounded text-black"
                    x-model="selectedTeam" @change="newTeamName = ''" required>
                    <option value="">Select your team</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected(old('team_id') == $team->id)>{{ $team->name }}
                        </option>
                    @endforeach
                    <option value="1" {{ old('team_id') == 'others' ? 'selected' : '' }}>Others</option>
                </select>
                @error('team_id')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div x-show="selectedTeam === '1'" x-transition class="mt-4">
                    <label for="team_name_ref" class="block font-semibold mb-1">Team Name <span
                            class="text-red-500">*</span></label>
                    <input type="text" name="team_name_ref" id="team_name_ref" x-model="newTeamName"
                        class="w-full px-3 py-2 border rounded text-black"
                        x-bind:required="selectedTeam === 'others'" placeholder="Enter your team name">
                    @error('team_name_ref')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>


            <div class="block">
                {{-- Leather Ball Stats + Location Dropdown --}}
                <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                    <label for="team_id" class="block font-semibold mb-1">Leather Ball Profile Stats (as of
                        registration date)
                        <span class="text-red-500">*</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="total_matches" class="block font-semibold mb-1">Total Matches</label>
                        <input type="number" name="total_matches" id="total_matches"
                            class="w-full px-3 py-2 border rounded text-black" value="{{ old('total_matches') }}"
                            min="0" placeholder="0" required>
                        @error('total_matches')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="total_runs" class="block font-semibold mb-1">Total Runs</label>
                        <input type="number" name="total_runs" id="total_runs"
                            class="w-full px-3 py-2 border rounded text-black" value="{{ old('total_runs') }}"
                            min="0" placeholder="0" required>
                        @error('total_runs')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="total_wickets" class="block font-semibold mb-1">Total Wickets</label>
                        <input type="number" name="total_wickets" id="total_wickets"
                            class="w-full px-3 py-2 border rounded text-black" value="{{ old('total_wickets') }}"
                            min="0" placeholder="0" required>
                        @error('total_wickets')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>
            {{-- Player Type Dropdown --}}
            <div>
                <label for="player_type_id" class="block font-semibold mb-8">Player Type <span
                        class="text-red-500">*</span> </label>
                <select name="player_type_id" id="player_type_id" class="w-full px-3 py-2 border rounded text-black">
                    <option value="">Select Player Type</option>
                    @foreach ($playerTypes as $type)
                        <option value="{{ $type->id }}" @selected(old('player_type_id') == $type->id)>{{ $type->type }}
                        </option>
                    @endforeach
                </select>
                @error('player_type_id')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Player Image Upload --}}
            <div class="flex flex-col space-y-2">
                {{-- Label --}}
                <label for="image" class="block   font-semibold mb-1">
                    Player Image <span class="text-red-500">*</span>

                    <div x-data="{ showTooltip: false }" class="relative ml-2 tooltip-container">
                        <i class="fas fa-info-circle text-gray-400 hover:text-yellow-400 cursor-pointer"
                            @mouseclick="showTooltip = true" @click="showTooltip = !showTooltip"></i>

                        <div x-show="showTooltip" @click.outside="showTooltip = false"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="tooltip-content absolute z-50 bg-white border border-gray-300 rounded-lg shadow-lg p-4 w-80 max-w-[90vw] text-sm text-gray-800"
                            style="display: none;">
                            <p class="mb-2">For best results, please upload an image with:</p>
                            <ul class="list-disc list-inside mb-3 space-y-1">
                                <li><span class="font-semibold">3:4 Aspect Ratio</span> (e.g., 300px width x 400px
                                    height minimum)</li>
                                <li><span class="font-semibold">Average Quality</span> (Max 6MB)</li>
                                <li><span class="font-semibold">Only *.jpg/ *.jpeg files</li>
                            </ul>
                            <p class="font-semibold mb-2">Example:</p>
                            <img src="{{ asset('images/logo/player.png') }}" alt="Example Player Image"
                                class="w-24 h-auto rounded border border-gray-600 mx-auto sm:mx-0">
                            <p class="text-xs text-gray-500 mt-3 text-center sm:text-left">
                                Ensure the player's face is clearly visible.
                            </p>
                        </div>
                    </div>

                </label>

                {{-- Upload & Preview --}}
                <div x-data="{
                    previewUrl: '',
                    handleFileChange(event) {
                        const file = event.target.files[0];
                        if (file && file.type.startsWith('image/')) {
                            this.previewUrl = URL.createObjectURL(file);
                        } else {
                            this.previewUrl = '';
                        }
                    },
                    dropHandler(event) {
                        event.preventDefault();
                        const file = event.dataTransfer.files[0];
                        if (file && file.type.startsWith('image/')) {
                            this.$refs.fileInput.files = event.dataTransfer.files;
                            this.previewUrl = URL.createObjectURL(file);
                        }
                    }
                }" @drop.prevent="dropHandler($event)" @dragover.prevent
                    class="relative w-full border-2 border-dashed border-gray-300 hover:border-blue-500 bg-gray-50 p-4 rounded-lg cursor-pointer text-center focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @click="$refs.fileInput.click()">

                    <input type="file" name="image" id="image" accept="image/png,image/jpeg"
                        class="absolute w-0 h-0 opacity-0" x-ref="fileInput" @change="handleFileChange" required>

                    <template x-if="previewUrl">
                        <img :src="previewUrl"
                            class="mx-auto mb-2 h-48 object-contain rounded border border-gray-300" />
                    </template>

                    <p x-show="!previewUrl" class="text-gray-600 text-sm">
                        Drag & drop or click to upload image (JPG/JPEG, max 6MB)
                    </p>
                </div>

                {{-- Validation Error --}}
                @error('image')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>





            {{-- Mobile Number with Custom Country Code Dropdown --}}
            <div>
                <label for="mobile_number_national" class="block font-bold mb-3">Mobile Number <span
                        class="text-red-500">*</span> </label>
                <div class="flex items-center">
                    {{-- Mobile Country Code Dropdown (Custom) --}}
                    <div class="custom-select-container w-24 mr-2" x-data="countrySelect('mobile', '{{ old('mobile_country_code', '971') }}')">
                        <div class="custom-select-trigger" @click="open = !open" @keydown.escape="open = false"
                            :class="{ 'focus-within': open }">
                            <span class="flex items-center">
                                <span class="flag-icon" :class="`fi fi-${selectedCountryIso.toLowerCase()}`"></span>
                                <span x-text="`+${selectedDialCode}`"></span>
                            </span>
                            <i class="fas fa-chevron-down text-gray-400 text-xs ml-2"></i>
                        </div>

                        <div class="custom-options-list" x-show="open"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95">
                            <input type="text" x-model="search" @input="filterCountries"
                                placeholder="Search country..." class="country-search-input"
                                @keydown.down.prevent="focusNextOption()" @keydown.up.prevent="focusPrevOption()"
                                @keydown.enter.prevent="selectFocusedOption()">
                            <div x-ref="countryList" class="max-h-48 overflow-y-auto">
                                <template x-for="(country, index) in filteredCountries" :key="country.iso">
                                    <div class="custom-option" @click="selectCountry(country.dialCode, country.iso)"
                                        :class="{
                                            'selected': country.dialCode === selectedDialCode,
                                            'bg-gray-200': focusedIndex === index
                                        }"
                                        x-ref="option_${index}" @mouseenter="focusedIndex = index">
                                        <span class="flag-icon" :class="`fi fi-${country.iso.toLowerCase()}`"></span>
                                        <span x-text="`${country.name} (+${country.dialCode})`"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        {{-- Hidden input for the actual dial code --}}
                        <input type="hidden" name="mobile_country_code" x-model="selectedDialCode"
                            id="mobile_country_code_hidden">
                    </div>

                    {{-- National Number Input --}}
                    <input type="tel" id="mobile_number_national" name="mobile_number_national_display"
                        value="{{ old('mobile_national_number') }}" required
                        class="px-3 py-2 border rounded text-black flex-grow" placeholder="e.g., 501234567">
                </div>
                <p id="mobile_number_error" class="text-sm text-red-600 mt-1" style="display: none;">Please enter
                    a
                    valid mobile number.</p>
                <input type="hidden" name="mobile_national_number" id="mobile_national_number_hidden"
                    value="{{ old('mobile_national_number') }}">
                @error('mobile_country_code')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('mobile_national_number')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Cricheroes Number with Custom Country Code Dropdown and Copy Option --}}
            <div x-data="{ copyMobileNumber: {{ old('copyMobileNumber') ? 'true' : 'false' }} }">
                <label for="cricheroes_number_national" class="block font-semibold mb-1">Cricheroes Number
                    <span class="text-red-500">*</span> </label>
                <div class="flex items-center">
                    {{-- Cricheroes Country Code Dropdown (Custom) --}}
                    <div class="custom-select-container mr-2" x-data="countrySelect('cricheroes', '{{ old('cricheroes_country_code', '971') }}', copyMobileNumber)"
                        x-ref="cricheroesCountrySelectContainer">
                        <div class="custom-select-trigger" @click="if(!isDisabled) open = !open"
                            @keydown.escape="open = false"
                            :class="{ 'focus-within': open, 'bg-gray-200 cursor-not-allowed': isDisabled }"
                            :aria-disabled="isDisabled">
                            <span class="flex items-center">
                                <span class="flag-icon" :class="`fi fi-${selectedCountryIso.toLowerCase()}`"></span>
                                <span x-text="`+${selectedDialCode}`"></span>
                            </span>
                            <i class="fas fa-chevron-down text-gray-400 text-xs ml-2" x-show="!isDisabled"></i>
                        </div>

                        <div class="custom-options-list" x-show="open && !isDisabled"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95">
                            <input type="text" x-model="search" @input="filterCountries"
                                placeholder="Search country..." class="country-search-input"
                                @keydown.down.prevent="focusNextOption()" @keydown.up.prevent="focusPrevOption()"
                                @keydown.enter.prevent="selectFocusedOption()">
                            <div x-ref="countryList" class="max-h-48 overflow-y-auto">
                                <template x-for="(country, index) in filteredCountries" :key="country.iso">
                                    <div class="custom-option" @click="selectCountry(country.dialCode, country.iso)"
                                        :class="{
                                            'selected': country.dialCode === selectedDialCode,
                                            'bg-gray-200': focusedIndex === index
                                        }"
                                        x-ref="option_${index}" @mouseenter="focusedIndex = index">
                                        <span class="flag-icon" :class="`fi fi-${country.iso.toLowerCase()}`"></span>
                                        <span x-text="`${country.name} (+${country.dialCode})`"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        {{-- Hidden input for the actual dial code --}}
                        <input type="hidden" name="cricheroes_country_code" x-model="selectedDialCode"
                            id="cricheroes_country_code_hidden">
                    </div>

                    {{-- National Number Input --}}
                    <input type="tel" id="cricheroes_number_national" name="cricheroes_number_national_display"
                        value="{{ old('cricheroes_national_number') }}"
                        class="px-3 py-2 border rounded text-black flex-grow" placeholder="e.g., 501234567"
                        x-bind:disabled="copyMobileNumber"
                        x-bind:class="{ 'bg-gray-200 cursor-not-allowed': copyMobileNumber }">
                </div>
                <p id="cricheroes_number_error" class="text-sm text-red-600 mt-1" style="display: none;">
                    Please
                    enter a valid Cricheroes number or leave it empty.</p>

                <input type="hidden" name="cricheroes_national_number" id="cricheroes_national_number_hidden"
                    value="{{ old('cricheroes_national_number') }}">

                <label class="inline-flex items-center text-sm cursor-pointer mt-2">
                    <input type="checkbox" x-model="copyMobileNumber" name="copyMobileNumber"
                        id="copyMobileNumberCheckbox" @change="handleCopyMobileNumberChange()"
                        class="accent-yellow-500">
                    <span class="ml-2 text-gray-300">Same as Mobile Number</span>
                </label>
                @error('cricheroes_country_code')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('cricheroes_national_number')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>


            {{-- Jersey Name --}}
            <div>
                <label for="jersey_name" class="block font-semibold mb-1">Jersey Name <span
                        class="text-red-500">*</span> </label>
                <input type="text" name="jersey_name" id="jersey_name" value="{{ old('jersey_name') }}"
                    class="w-full px-3 py-2 border rounded text-black">
                @error('jersey_name')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Jersey Size --}}
            <div>
                <label for="kit_size_id" class="block font-semibold mb-1">Jersey Size <span
                        class="text-red-500">*</span> </label>
                <select name="kit_size_id" id="kit_size_id" class="w-full px-3 py-2 border rounded text-black">
                    <option value="">Select Jersey Size</option>
                    @foreach ($kitSizes as $kit)
                        <option value="{{ $kit->id }}" @selected(old('kit_size_id') == $kit->id)>{{ $kit->size }}
                        </option>
                    @endforeach
                </select>
                @error('kit_size_id')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Batting Profile --}}
            <div>
                <label for="batting_profile_id" class="block font-semibold mb-1">Batting Profile <span
                        class="text-red-500">*</span> </label>
                <select name="batting_profile_id" id="batting_profile_id"
                    class="w-full px-3 py-2 border rounded text-black">
                    <option value="">Select Batting Profile</option>
                    @foreach ($battingProfiles as $bat)
                        <option value="{{ $bat->id }}" @selected(old('batting_profile_id') == $bat->id)>{{ $bat->style }}
                        </option>
                    @endforeach
                </select>
                @error('batting_profile_id')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Bowling Profile --}}
            <div>
                <label for="bowling_profile_id" class="block font-semibold mb-1">Bowling Profile <span
                        class="text-red-500">*</span> </label>
                <select name="bowling_profile_id" id="bowling_profile_id"
                    class="w-full px-3 py-2 border rounded text-black">
                    <option value="">Select Bowling Profile</option>
                    @foreach ($bowlingProfiles as $bowl)
                        <option value="{{ $bowl->id }}" @selected(old('bowling_profile_id') == $bowl->id)>{{ $bowl->style }}
                        </option>
                    @endforeach
                </select>
                @error('bowling_profile_id')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Transportation and Wicket Keeper Checkboxes --}}
            <div>

                <div class="md:col-span-2 flex gap-6 mt-8">
                    <label class="inline-flex items-center">
                        <!-- Hidden input ensures a default value is sent if checkbox is unchecked -->
                        <input type="hidden" name="wicket_keeper" value="0">
                        <input type="checkbox" name="wicket_keeper" value="1"
                            {{ old('wicket_keeper') ? 'checked' : '' }} class="accent-yellow-500">
                        <span class="ml-2">Wicket Keeper</span>
                    </label>

                    <label class="inline-flex items-center">
                        <input type="hidden" name="need_transportation" value="0">
                        <input type="checkbox" name="need_transportation" value="1"
                            {{ old('need_transportation') ? 'checked' : '' }} class="accent-yellow-500">
                        <span class="ml-2">Need Transportation</span>
                    </label>
                </div>

            </div>



            <div class="md:col-span-2 flex flex-col gap-4 mt-4" x-data="{
                noTravel: {{ old('no_travel_plan', false) ? 'true' : 'false' }},
                from: '{{ old('travel_date_from') }}',
                to: '{{ old('travel_date_to') }}',
                today: (new Date()).toISOString().split('T')[0]
            }">

                <!-- Ensure a value is always sent for no_travel -->
                <input type="hidden" name="no_travel_plan" value="0">

                <label class="inline-flex items-center">
                    <input type="checkbox" name="no_travel_plan" value="1" x-model="noTravel"
                        class="accent-yellow-500">
                    <span class="ml-2">No Travel Plan</span>
                </label>

                <div x-show="!noTravel" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Travel From Date -->
                    <div>
                        <label for="travel_date_from" class="block font-semibold mb-1">Travel Date From</label>
                        <input type="date" name="travel_date_from" id="travel_date_from" x-model="from"
                            :min="today" :required="!noTravel"
                            class="w-full px-3 py-2 border rounded text-black" placeholder="Select start date">
                        @error('travel_date_from')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Travel To Date -->
                    <div>
                        <label for="travel_date_to" class="block font-semibold mb-1">Travel Date To</label>
                        <input type="date" name="travel_date_to" id="travel_date_to" x-model="to"
                            :min="from || today" :required="!noTravel"
                            class="w-full px-3 py-2 border rounded text-black" placeholder="Select end date">
                        @error('travel_date_to')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>


            <div class="md:col-span-2 space-y-4 mt-6">

                <label class="inline-flex items-start space-x-2">
                    <input type="checkbox" name="accept_availability" required class="mt-0 accent-yellow-500">
                    <span class="text-sm text-gray-200">
                        I have read and agree to the
                        <a href="{{ route('policies.availability') }}" target="_blank"
                            class="underline text-yellow-400 hover:text-yellow-300">
                            Player Availability Policy
                        </a>
                    </span>
                </label>

                <label class="inline-flex items-start space-x-2">
                    <input type="checkbox" name="accept_auction_commitment" required class="mt-0 accent-yellow-500">
                    <span class="text-sm text-gray-200">
                        I understand and accept the
                        <a href="{{ route('policies.auction') }}" target="_blank"
                            class="underline text-yellow-400 hover:text-yellow-300">
                            Auction & Player Commitment Policy
                        </a>
                    </span>
                </label>


                @error('accept_availability')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('accept_auction_commitment')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <div class="md:col-span-2 mt-6">
                    <button type="submit"
                        class="w-full bg-gradient-to-r from-yellow-500 to-orange-600 hover:from-yellow-600 hover:to-orange-700 text-gray-900 font-bold text-xl px-4 py-4 rounded-lg shadow-md hover:shadow-lg transform transition duration-300 hover:scale-100">
                        Submit Registration
                    </button>
                </div>
            </div>
        </form>
    </section>

    <footer class="text-center text-gray-500 text-sm mt-10 py-6 bg-gray-900">
        &copy; {{ now()->year }} Sportzley Powered by TBR
    </footer>

</body>


<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/libphonenumber-js@1.10.16/bundle/libphonenumber-min.js"></script>

<script>
    // Constants and helper functions
    const DEFAULT_COUNTRY_CODE_ISO = 'AE'; // UAE as fallback
    let DEFAULT_COUNTRY_CODE_DIAL;

    function getCountryISOsByDialCode(dialCode) {
        if (!libphonenumber || !libphonenumber.getCountries) {
            console.warn("libphonenumber-js not loaded yet.");
            return [];
        }
        return libphonenumber.getCountries().filter(iso => {
            try {
                return libphonenumber.getCountryCallingCode(iso) === dialCode;
            } catch {
                return false;
            }
        });
    }

    document.addEventListener('alpine:init', () => {
        if (typeof libphonenumber !== 'undefined') {
            DEFAULT_COUNTRY_CODE_DIAL = libphonenumber.getCountryCallingCode(DEFAULT_COUNTRY_CODE_ISO);
        }

        Alpine.data('countrySelect', (type, initialDialCode, isInputDisabled = false) => ({
            open: false,
            search: '',
            countries: [],
            filteredCountries: [],
            selectedDialCode: initialDialCode,
            selectedCountryIso: DEFAULT_COUNTRY_CODE_ISO,
            isDisabled: isInputDisabled,
            focusedIndex: -1,
            type: type,

            init() {
                if (typeof libphonenumber === 'undefined') {
                    console.error('libphonenumber-js is not loaded.');
                    return;
                }

                this.countries = libphonenumber.getCountries().map(iso => {
                    try {
                        return {
                            iso,
                            name: new Intl.DisplayNames(['en'], {
                                type: 'region'
                            }).of(iso) || iso,
                            dialCode: libphonenumber.getCountryCallingCode(iso),
                        };
                    } catch {
                        return null;
                    }
                }).filter(Boolean).sort((a, b) => a.name.localeCompare(b.name));

                const possibleISOs = getCountryISOsByDialCode(initialDialCode);
                const resolvedIso = possibleISOs.length > 0 ? possibleISOs[0] :
                    DEFAULT_COUNTRY_CODE_ISO;

                this.selectedDialCode = initialDialCode || DEFAULT_COUNTRY_CODE_DIAL;
                this.selectedCountryIso = resolvedIso;

                this.filterCountries();
                this.updateHiddenInputs();

                this.$watch('isDisabled', value => {
                    if (value) this.open = false;
                });

                this.$nextTick(() => {
                    window.validateInput(this.type);
                });
            },

            updateHiddenInputs() {
                const countryCodeHidden = document.getElementById(
                    `${this.type}_country_code_hidden`);
                const nationalNumberHidden = document.getElementById(
                    `${this.type}_national_number_hidden`);
                const nationalNumberInput = document.getElementById(`${this.type}_number_national`);

                if (countryCodeHidden) {
                    countryCodeHidden.value = this.selectedDialCode;
                }
                if (nationalNumberHidden && nationalNumberInput) {
                    nationalNumberHidden.value = nationalNumberInput.value.trim();
                }
            },

            filterCountries() {
                const searchTerm = this.search.toLowerCase();
                this.focusedIndex = -1;
                this.filteredCountries = this.countries.filter(country =>
                    country.name.toLowerCase().includes(searchTerm) ||
                    country.dialCode.includes(searchTerm)
                );
            },

            selectCountry(dialCode, iso) {
                if (this.isDisabled) return;
                this.selectedDialCode = dialCode;
                this.selectedCountryIso = iso;
                this.open = false;
                this.search = '';
                this.filterCountries();
                this.focusedIndex = -1;

                this.updateHiddenInputs();
                window.validateInput(this.type);
            },

            focusNextOption() {
                if (!this.filteredCountries.length) return;
                this.focusedIndex = (this.focusedIndex + 1) % this.filteredCountries.length;
                this.scrollToFocusedOption();
            },
            focusPrevOption() {
                if (!this.filteredCountries.length) return;
                this.focusedIndex = (this.focusedIndex - 1 + this.filteredCountries.length) % this
                    .filteredCountries.length;
                this.scrollToFocusedOption();
            },
            selectFocusedOption() {
                if (this.focusedIndex >= 0 && this.focusedIndex < this.filteredCountries.length) {
                    const country = this.filteredCountries[this.focusedIndex];
                    this.selectCountry(country.dialCode, country.iso);
                }
            },
            scrollToFocusedOption() {
                this.$nextTick(() => {
                    const el = this.$refs[`option_${this.focusedIndex}`];
                    if (el) el.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest'
                    });
                });
            }
        }));
    });

    const mobileNumberNationalInput = document.getElementById('mobile_number_national');
    const mobileCountryCodeHidden = document.getElementById('mobile_country_code_hidden');
    const mobileNationalNumberHidden = document.getElementById('mobile_national_number_hidden');
    const mobileNumberError = document.getElementById('mobile_number_error');

    const cricheroesNumberNationalInput = document.getElementById('cricheroes_number_national');
    const cricheroesCountryCodeHidden = document.getElementById('cricheroes_country_code_hidden');
    const cricheroesNationalNumberHidden = document.getElementById('cricheroes_national_number_hidden');
    const cricheroesNumberError = document.getElementById('cricheroes_number_error');

    const copyMobileNumberCheckbox = document.getElementById('copyMobileNumberCheckbox');
    const registrationForm = document.getElementById('main-registration-form');

    // New constants for the image upload field and its error message
    const profileImageInput = document.getElementById('image_path');
    const profileImageError = document.getElementById('image_path_error');

    // New validation function for the image field
    window.validateImage = function() {
        if (!profileImageInput) {
            console.warn("Player image is required.");
            return true;
        }

        if (profileImageInput.files.length === 0) {
            // Updated error message here
            profileImageError.textContent = 'Profile image is required.';
            profileImageError.style.display = 'block';
            profileImageInput.classList.add('border-red-500');
            profileImageInput.classList.remove('border-gray-300');
            return false;
        } else {
            profileImageError.style.display = 'none';
            profileImageInput.classList.remove('border-red-500');
            profileImageInput.classList.add('border-gray-300');
            return true;
        }
    };

    window.validateInput = function(type) {
        let countryCodeHidden, nationalNumberInput, nationalNumberHidden, errorElement;
        let isOptional = false;

        if (type === 'mobile') {
            [countryCodeHidden, nationalNumberInput, nationalNumberHidden, errorElement] = [mobileCountryCodeHidden,
                mobileNumberNationalInput, mobileNationalNumberHidden, mobileNumberError
            ];
        } else if (type === 'cricheroes') {
            [countryCodeHidden, nationalNumberInput, nationalNumberHidden, errorElement] = [
                cricheroesCountryCodeHidden, cricheroesNumberNationalInput, cricheroesNationalNumberHidden,
                cricheroesNumberError
            ];
            isOptional = true;
        } else {
            console.error('Unknown input type:', type);
            return false;
        }

        const nationalNumber = nationalNumberInput.value.trim();
        const selectedDialCode = countryCodeHidden.value;
        const isoList = getCountryISOsByDialCode(selectedDialCode);
        const iso = isoList.length > 0 ? isoList[0] : undefined;

        console.log(`Validating ${type} input:`, {
            nationalNumber,
            selectedDialCode,
            iso
        });

        if (nationalNumber !== '' && !selectedDialCode) {
            errorElement.textContent = 'Please select a country code.';
            errorElement.style.display = 'block';
            nationalNumberInput.classList.add('border-red-500');
            nationalNumberInput.classList.remove('border-gray-300');
            nationalNumberHidden.value = '';
            return false;
        }

        if (nationalNumber === '' && !isOptional) {
            errorElement.textContent = 'Please enter your mobile number.';
            errorElement.style.display = 'block';
            nationalNumberInput.classList.add('border-red-500');
            nationalNumberInput.classList.remove('border-gray-300');
            nationalNumberHidden.value = '';
            return false;
        }

        if (nationalNumber === '' && isOptional) {
            errorElement.style.display = 'none';
            nationalNumberInput.classList.remove('border-red-500');
            nationalNumberInput.classList.add('border-gray-300');
            nationalNumberHidden.value = '';
            return true;
        }

        try {
            const phoneNumber = libphonenumber.parsePhoneNumberFromString(nationalNumber, iso);
            if (phoneNumber && phoneNumber.isValid()) {
                nationalNumberHidden.value = phoneNumber.nationalNumber;
                errorElement.style.display = 'none';
                nationalNumberInput.classList.remove('border-red-500');
                nationalNumberInput.classList.add('border-gray-300');
                return true;
            } else {
                throw new Error('Invalid phone');
            }
        } catch (e) {
            errorElement.textContent = `Invalid phone number for ${iso ? iso : 'the selected country'}.`;
            errorElement.style.display = 'block';
            nationalNumberInput.classList.add('border-red-500');
            nationalNumberInput.classList.remove('border-gray-300');
            nationalNumberHidden.value = '';
            console.error(`Validation error for ${type}:`, e);
            return false;
        }
    };

    window.handleCopyMobileNumberChange = function() {
        const cricheroesAlpineData = Alpine.$data(cricheroesCountryCodeHidden.parentElement);

        if (copyMobileNumberCheckbox.checked) {
            const mobileAlpineData = Alpine.$data(mobileCountryCodeHidden.parentElement);
            const mobileDialCode = mobileAlpineData.selectedDialCode;
            const mobileIso = mobileAlpineData.selectedCountryIso;
            const mobileNationalNum = mobileNumberNationalInput.value;

            if (cricheroesAlpineData) {
                cricheroesAlpineData.isDisabled = true;
                cricheroesAlpineData.selectedDialCode = mobileDialCode;
                cricheroesAlpineData.selectedCountryIso = mobileIso;
            }

            cricheroesNumberNationalInput.value = mobileNationalNum;
            cricheroesNumberNationalInput.setAttribute('disabled', 'disabled');
            cricheroesNumberNationalInput.classList.add('bg-gray-200', 'cursor-not-allowed');
        } else {
            if (cricheroesAlpineData) {
                cricheroesAlpineData.isDisabled = false;
            }
            cricheroesNumberNationalInput.value = '';
            cricheroesNumberNationalInput.removeAttribute('disabled');
            cricheroesNumberNationalInput.classList.remove('bg-gray-200', 'cursor-not-allowed');
        }

        window.validateInput('cricheroes');
    };

    registrationForm.addEventListener('submit', function(event) {
        if (copyMobileNumberCheckbox.checked) {
            cricheroesNationalNumberInput.value = mobileNumberNationalInput.value;
        }

        const isMobileValid = window.validateInput('mobile');
        const isCricheroesValid = window.validateInput('cricheroes');
        const isImageValid = window.validateImage(); // Call the new image validation function

        // Prevent form submission if any field is invalid
        if (!isMobileValid || !isCricheroesValid || !isImageValid) {
            event.preventDefault();

            // Always scroll to the top of the registration form
            const formHeader = document.querySelector('#registration-form');
            formHeader.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

            alert('Please correct the errors in the form before submitting.');
        }
    });


    document.addEventListener('DOMContentLoaded', () => {

        const errorBlock = document.getElementById('error-messages');

        if (errorBlock) {
            errorBlock.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

            // Optionally focus on first input with error styling
            const errorInput = document.querySelector('.border-red-500, .is-invalid');
            if (errorInput) {
                setTimeout(() => errorInput.focus(), 400);
            }
        }

        function setCountryCodeForInputs(iso) {
            if (typeof libphonenumber !== 'undefined') {
                const dialCode = libphonenumber.getCountryCallingCode(iso);
                if (dialCode) {
                    const mobileAlpine = Alpine.$data(mobileCountryCodeHidden.parentElement);
                    const cricheroesAlpine = Alpine.$data(cricheroesCountryCodeHidden.parentElement);

                    if (mobileAlpine) {
                        mobileAlpine.selectedDialCode = dialCode;
                        mobileAlpine.selectedCountryIso = iso;
                        mobileAlpine.updateHiddenInputs();
                    }
                    if (cricheroesAlpine) {
                        cricheroesAlpine.selectedDialCode = dialCode;
                        cricheroesAlpine.selectedCountryIso = iso;
                        cricheroesAlpine.updateHiddenInputs();
                    }
                    window.validateInput('mobile');
                    window.validateInput('cricheroes');
                }
            }
        }
        // New validation function for the image field

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.address && data.address.country_code) {
                            setCountryCodeForInputs(data.address.country_code.toUpperCase());
                        }
                    })
                    .catch(() => {
                        setCountryCodeForInputs('AE');
                    });
            }, function() {
                setCountryCodeForInputs('AE');
            });
        } else {
            setCountryCodeForInputs('AE');
        }

        if (!mobileCountryCodeHidden.value) {
            const dialCode = libphonenumber.getCountryCallingCode(DEFAULT_COUNTRY_CODE_ISO);
            mobileCountryCodeHidden.value = dialCode;
            cricheroesCountryCodeHidden.value = dialCode;
        }

        if (copyMobileNumberCheckbox.checked) {
            window.handleCopyMobileNumberChange();
        }

        mobileNumberNationalInput.addEventListener('input', () => {
            const mobileAlpine = Alpine.$data(mobileCountryCodeHidden.parentElement);
            if (mobileAlpine) mobileAlpine.updateHiddenInputs();
            window.validateInput('mobile');
        });

        cricheroesNumberNationalInput.addEventListener('input', () => {
            const cricheroesAlpine = Alpine.$data(cricheroesCountryCodeHidden.parentElement);
            if (cricheroesAlpine) cricheroesAlpine.updateHiddenInputs();
            window.validateInput('cricheroes');
        });

        // Add a listener for the image field to validate on change
        // Validate the image field on initial load and on change
        if (profileImageInput) {
            window.validateImage(); // Validate on initial load
            profileImageInput.addEventListener('change', () => window.validateImage());
        }

    });


    document.addEventListener('DOMContentLoaded', function() {
        const errorList = document.querySelector('ul.list-disc.pl-5.mb-4');

        if (errorList && errorList.offsetParent !== null) {
            // Element exists and is visible
            errorList.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });


    const fileInput = document.getElementById('image_path');
    const previewDiv = document.getElementById('remove-bg-preview');
    const errorDiv = document.getElementById('remove-bg-error');
</script>


</html>
