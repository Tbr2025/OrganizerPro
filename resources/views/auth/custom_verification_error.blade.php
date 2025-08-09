@extends('layouts.frontend')

@section('content')
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="bg-red-600 text-white text-center py-4 text-xl font-semibold">
                Waiting for the approval!
            </div>

            <div class="p-6 space-y-4 text-gray-800 text-base leading-relaxed">

                {{-- Logo --}}
                <div class="flex justify-center mb-6">
                    <img src="{{ asset('images/logo/landing.png') }}" alt="Tournament Logo" class="mx-auto w-[250px] h-auto">
                </div>

                <p>
                    Please wait for the approval.
                </p>

                <p>
                    If you think this is an error, please <span class="font-semibold">contact support</span>.
                </p>

               
            </div>
        </div>
    </div>
@endsection
