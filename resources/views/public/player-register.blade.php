<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Complete | Sportzley</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">

    <div class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-blue-50 via-white to-green-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
        <div class="max-w-2xl w-full bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden">
            
            {{-- Header Section with Checkmark --}}
            <div class="p-8 text-center bg-green-500">
                <div class="flex justify-center items-center w-16 h-16 mx-auto bg-white/30 rounded-full mb-4 ring-4 ring-white/50">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-white">Registration Complete!</h1>
                <p class="text-green-100 mt-1">Welcome to the Sportzley community, [Player Name]!</p>
            </div>

            {{-- Main Content Section --}}
            <div class="p-8 space-y-6">
                
                {{-- "What's Next?" Section --}}
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">What Happens Next?</h2>
                    <div class="mt-3 bg-blue-50 dark:bg-blue-900/50 border-l-4 border-blue-500 p-4 rounded-r-lg text-blue-800 dark:text-blue-200">
                        <p class="font-semibold">Your Profile is Under Review</p>
                        <p class="text-sm mt-1">
                            An administrator will review your submitted details for verification. You will receive an email notification once your profile is approved and active.
                        </p>
                    </div>
                </div>

                {{-- Registration Summary --}}
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Your Submitted Information</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">A summary of your registration details has been sent to your email address.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        
                        {{-- This data would be filled dynamically from the previous step --}}
                        <div class="bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg">
                            <label class="font-medium text-gray-500 dark:text-gray-400">Full Name</label>
                            <p class="font-semibold text-gray-900 dark:text-white">[Player Name]</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg">
                            <label class="font-medium text-gray-500 dark:text-gray-400">Email Address</label>
                            <p class="font-semibold text-gray-900 dark:text-white">[player@example.com]</p>
                        </div>
                         <div class="bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg">
                            <label class="font-medium text-gray-500 dark:text-gray-400">Mobile Number</label>
                            <p class="font-semibold text-gray-900 dark:text-white">[+91-XXXXXXXXXX]</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg">
                            <label class="font-medium text-gray-500 dark:text-gray-400">Primary Role</label>
                            <p class="font-semibold text-gray-900 dark:text-white">[Batsman / Bowler / All-Rounder]</p>
                        </div>

                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="pt-6 border-t border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <a href="/" class="w-full sm:w-auto text-center px-6 py-2.5 text-sm font-semibold text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/50 rounded-lg transition">
                        Back to Homepage
                    </a>
                    <a href="/login" class="w-full sm:w-auto text-center px-6 py-2.5 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-md transition">
                        Go to Login
                    </a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>