{{-- resources/views/public/player-verified-success.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verified</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-green-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md mx-auto bg-white p-8 rounded-xl shadow-md text-center">
        <h1 class="text-3xl font-bold text-green-600 mb-4">✅ Email Verified!</h1>
        <p class="text-gray-700 mb-4">
            Your email has been successfully verified.
        </p>
        <p class="text-sm text-gray-600 mb-6">
            Please wait for approval before logging in. You will be notified once approved.
        </p>
        <a href="{{ url('/') }}"
           class="inline-block bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded transition">
            Return to Home Page
        </a>
    </div>
</body>
</html>
