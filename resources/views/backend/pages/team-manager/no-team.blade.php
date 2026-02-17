@extends('backend.layouts.app')

@section('title', 'No Team Assigned')

@section('admin-content')
<div class="p-4 mx-auto max-w-2xl md:p-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 text-center">
        <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
        </svg>
        <h2 class="mt-4 text-xl font-semibold text-gray-900 dark:text-white">No Team Assigned</h2>
        <p class="mt-2 text-gray-500 dark:text-gray-400">
            You are not currently assigned to any team. Please contact your tournament administrator to be assigned to a team.
        </p>
        <div class="mt-6">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">
                Go to Dashboard
            </a>
        </div>
    </div>
</div>
@endsection
