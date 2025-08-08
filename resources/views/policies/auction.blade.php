@extends('layouts.frontend')

@section('content')
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="bg-blue-600 text-white text-center py-4 text-xl font-semibold">
                Auction & Player Commitment Policy
            </div>

            <div class="p-6 space-y-4 text-gray-800 text-base leading-relaxed">

                {{-- Logo --}}
                <div class="flex justify-center mb-4">
                   <img src="{{ asset('images/logo/landing.png') }}" alt="IPL Logo" class="mx-auto my-4 w-[250px] h-auto">
                </div>

                <p>
                    The auction will be conducted based on a <span class="font-semibold">virtual points system</span>.
                </p>

                <p>
                    <span class="font-semibold">Only Indian players</span> are eligible to participate in the auction.
                </p>

                <p>
                    Players must <span class="font-semibold">adhere to the rules and policies</span> set by the franchise
                    that selects them in the auction.
                </p>

                <p class="text-red-600 font-medium">
                    Match fees or payment demands are strictly prohibited for any player selected through the auction
                    process.
                </p>

                <p>
                    Only players who <span class="font-semibold">agree to all the above terms</span> will be considered
                    eligible for the auction.
                </p>

                <p class="text-red-600">
                    Any breach of commitment will result in <span class="italic">penalties and possible
                        disqualification</span> from future tournament participation.
                </p>

                <p class="text-right text-sm text-gray-500 mt-6">
                    Last updated: August 7, 2025
                </p>
            </div>
        </div>
    </div>
@endsection
