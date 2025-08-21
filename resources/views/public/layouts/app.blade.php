<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Auction')</title>
    <link rel="stylesheet" href="https://cdn.tailwindcss.com">
</head>

<body class="bg-black text-white">
    {{-- Header --}}
    @include('public.layouts.header')

    {{-- Main content --}}
    <main>
        @yield('content')
    </main>

    {{-- Scripts --}}
    @yield('scripts')
</body>

</html>
