@extends('layouts.frontend')

@section('content')
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="bg-green-600 text-white text-center py-4 text-xl font-semibold">
                Player Availability Policy
            </div>

            <div class="p-6 space-y-4 text-gray-800 text-base leading-relaxed">

                {{-- Logo --}}
                <div class="flex justify-center mb-4">
                    <img src="{{ asset('images/logo/landing.png') }}" alt="Tournament Logo" class="mx-auto my-4 w-[250px] h-auto">
                </div>

                <p>
                    All registered players must ensure their availability for the entire duration of the tournament unless otherwise approved by the organizer.
                </p>

                <p>
                    Players are expected to <span class="font-semibold">attend all scheduled matches</span> for their team. Consistent absence may lead to disqualification.
                </p>

                <p>
                    In case of emergencies, players must <span class="font-semibold">notify the team manager or organizer</span> in advance.
                </p>

                <p class="text-red-600 font-medium">
                    Players missing key matches without prior notice may face suspension from future tournaments.
                </p>

                <p>
                    Availability confirmation must be provided during the registration process. <span class="font-semibold">Changes should be communicated immediately</span> if any scheduling conflicts arise.
                </p>

                <p class="text-red-600">
                    <span class="italic">False commitment or last-minute dropout</span> can impact the integrity of the tournament and will result in blacklisting.
                </p>

                <p class="text-right text-sm text-gray-500 mt-6">
                    Last updated: August 7, 2025
                </p>
            </div>
        </div>
    </div>
@endsection
