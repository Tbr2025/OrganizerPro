<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Registration Closed - YSIPL Exhibition</title>
    <meta name="title" content="Registration Closed - YSIPL Exhibition" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description"
        content="Registration for the YSIPL Exhibition Tournament is now closed. Stay tuned for the player auction and match updates!" />

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://sportzley.com/player/register" />
    <meta property="og:title" content="Registration Closed - YSIPL Exhibition" />
    <meta property="og:description"
        content="Registration for the YSIPL Exhibition Tournament is now closed. Stay tuned for the player auction and match updates!" />
    <meta property="og:image" content="https://sportzley.com/images/logo/og.jpg" />

    <!-- X (Twitter) -->
    <meta property="twitter:card" content="summary_large_image" />
    <meta property="twitter:url" content="https://sportzley.com/player/register" />
    <meta property="twitter:title" content="Registration Closed - YSIPL Exhibition" />
    <meta property="twitter:description"
        content="Registration for the YSIPL Exhibition Tournament is now closed. Stay tuned for the player auction and match updates!" />
    <meta property="twitter:image" content="https://sportzley.com/images/logo/og.jpg" />

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }

        h1,
        h2 {
            font-family: 'Oswald', sans-serif;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        .animate-pulse-custom {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        .logo-img {
            display: block;
            margin: 1rem auto;
            width: auto;
            object-fit: contain;
        }

        @media (min-width: 640px) {
            .logo-img {
                width: 200px;
            }
        }

        @media (min-width: 768px) {
            .logo-img {
                width: 300px;
            }
        }

        @media (min-width: 1024px) {
            .logo-img {
                width: 450px;
            }
        }

        .whatsapp-float {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: #25D366;
            color: white;
            padding: 10px 15px;
            border-radius: 30px;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            font-family: Arial, sans-serif;
            font-size: 16px;
            z-index: 9999;
            transition: transform 0.3s ease;
        }

        .whatsapp-float:hover {
            transform: scale(1.05);
        }

        .whatsapp-icon {
            width: 24px;
            height: 24px;
        }

        @media (max-width: 640px) {
            .whatsapp-text {
                display: none;
            }

            .whatsapp-float {
                padding: 10px;
                border-radius: 50%;
            }
        }
    </style>
</head>

<body class="bg-gray-900 text-gray-100">

    <!-- Toast Notifications (Unchanged) -->
    @if (session('success') || $errors->any())
        <!-- Your existing toast notification HTML can remain here -->
    @endif

    <!-- FIXED: Hero Section now reflects the "Registration Closed" status -->
    <section class="bg-cover bg-center h-screen relative"
        style="background-image: url('{{ asset('images/product/banner.jpg') }}');">
        <div class="absolute inset-0 bg-black bg-opacity-60"></div>
        <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-6 max-w-3xl mx-auto">
            <p class="text-lg md:text-xl font-bold uppercase tracking-widest text-yellow-400 mb-2 animate-pulse-custom">
                The Rosters are Set!
            </p>
            <div class="mx-auto ">
                <img src="{{ asset('images/logo/landing.png') }}" alt="YSIPL Logo" class="logo-img" />
            </div>
            <div class="max-w-4xl mx-auto px-4">
                <div class="flex justify-center items-center gap-8 flex-wrap">
                    <div
                        class="bg-white rounded-full p-4 shadow-md transition-transform duration-300 hover:scale-110 hover:shadow-xl">
                        <img src="{{ asset('images/logo/dcs.png') }}" alt="dcs" class="h-12 w-12 object-contain" />
                    </div>
                    <div
                        class="bg-white rounded-full p-4 shadow-md transition-transform duration-300 hover:scale-110 hover:shadow-xl">
                        <img src="{{ asset('images/logo/you-selects.png') }}" alt="you-selects"
                            class="h-12 w-12 object-contain" />
                    </div>
                    <div
                        class="bg-white rounded-full p-4 shadow-md transition-transform duration-300 hover:scale-110 hover:shadow-xl">
                        <img src="{{ asset('images/logo/uniformly.png') }}" alt="uniformly"
                            class="h-12 w-12 object-contain" />
                    </div>
                </div>
            </div>
           
            <p class="text-md md:text-lg lg:text-xl mt-6 text-gray-200 max-w-xl mx-auto">
                Thank you for the overwhelming response! Get ready to see who will join the ultimate cricket carnival.
            </p>
            <!-- The "Register as Player" button has been removed -->
        </div>
    </section>

    <!-- Key Dates Section (Unchanged) -->
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
    </section>

    <!-- "Registration Closed" Widget with Countdown Timer -->
    <section
        class="max-w-5xl mx-auto bg-gray-800 p-8 md:p-12 mt-10 mb-20 rounded-xl shadow-2xl border border-yellow-600"
        id="registration-closed">
        <div class="text-center">
            <div class="mx-auto mb-6">
                <i class="far fa-clock fa-5x text-yellow-500"></i> <!-- Changed icon to a gavel for auction -->
            </div>
            <h2 class="text-4xl md:text-5xl font-bold text-center mb-4 text-yellow-400">
                The Auction is Coming
            </h2>
            <p class="text-lg text-gray-300 max-w-2xl mx-auto">
                The next big event is just around the corner. Don't miss the excitement as teams bid for the best
                talent!
            </p>
            <hr class="my-10 border-t border-gray-700 max-w-sm mx-auto">

            <!-- Countdown Timer Component -->
            <div x-data="countdownTimer('2025-08-15T19:00:00')" x-init="init()" class="max-w-2xl mx-auto">
                <h3 class="text-3xl font-semibold text-white mb-6">
                    Auction Starts In
                </h3>
                <div x-show="!expired" class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 text-white">
                    <div class="bg-gray-700/50 p-4 rounded-lg border border-gray-600 text-center">
                        <span x-text="days" class="text-4xl lg:text-5xl font-bold text-yellow-400"></span>
                        <span class="block text-xs uppercase tracking-widest mt-1 text-gray-400">Days</span>
                    </div>
                    <div class="bg-gray-700/50 p-4 rounded-lg border border-gray-600 text-center">
                        <span x-text="hours" class="text-4xl lg:text-5xl font-bold text-yellow-400"></span>
                        <span class="block text-xs uppercase tracking-widest mt-1 text-gray-400">Hours</span>
                    </div>
                    <div class="bg-gray-700/50 p-4 rounded-lg border border-gray-600 text-center">
                        <span x-text="minutes" class="text-4xl lg:text-5xl font-bold text-yellow-400"></span>
                        <span class="block text-xs uppercase tracking-widest mt-1 text-gray-400">Minutes</span>
                    </div>
                    <div class="bg-gray-700/50 p-4 rounded-lg border border-gray-600 text-center">
                        <span x-text="seconds" class="text-4xl lg:text-5xl font-bold text-yellow-400"></span>
                        <span class="block text-xs uppercase tracking-widest mt-1 text-gray-400">Seconds</span>
                    </div>
                </div>
                <div x-show="expired" x-cloak class="bg-green-700 p-6 rounded-lg border border-green-500 text-center">
                    <h3 class="text-4xl font-bold text-white animate-pulse">The Auction is LIVE!</h3>
                    <p class="text-green-200 mt-2">Follow our social channels for real-time updates!</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer & WhatsApp (Unchanged) -->
    <footer class="text-center text-gray-500 text-sm mt-10 py-6 bg-gray-900">
        &copy; {{ now()->year }} Sportzley Powered by TBR
    </footer>
    <a href="https://wa.me/971553131009" target="_blank" class="whatsapp-float">
        <img src="{{ asset('images/icons/whatsapp.svg') }}" alt="WhatsApp" class="whatsapp-icon">
        <span class="whatsapp-text">Need Support?</span>
    </a>

    <!-- FIXED: Cleaned up JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('countdownTimer', (targetDateString) => ({
                targetDate: new Date(targetDateString).getTime(),
                days: '00', // Corrected initial state
                hours: '00',
                minutes: '00',
                seconds: '00',
                expired: false,
                interval: null,

                init() {
                    this.updateTimer();
                    this.interval = setInterval(() => {
                        this.updateTimer();
                    }, 1000);
                },

                updateTimer() {
                    const now = new Date().getTime();
                    const distance = this.targetDate - now;

                    if (distance <= 0) {
                        this.expired = true;
                        this.days = '00';
                        this.hours = '00';
                        this.minutes = '00';
                        this.seconds = '00';
                        clearInterval(this.interval);
                        return;
                    }

                    this.days = Math.floor(distance / (1000 * 60 * 60 * 24)).toString().padStart(2,
                    '0');
                    this.hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
                        .toString().padStart(2, '0');
                    this.minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60)).toString()
                        .padStart(2, '0');
                    this.seconds = Math.floor((distance % (1000 * 60)) / 1000).toString().padStart(2,
                        '0');
                }
            }));
        });
    </script>
</body>

</html>
