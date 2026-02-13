@extends('backend.layouts.app')

@section('title', 'Create Auction Template | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">

    {{-- Header --}}
    <div class="mb-8">
        <a href="{{ route('admin.auction-templates.index') }}" class="text-blue-600 hover:text-blue-800 text-sm flex items-center gap-1 mb-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Templates
        </a>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Create Auction Template</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Configure a new LED wall display template</p>
    </div>

    @if($errors->any())
        <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded-lg">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.auction-templates.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('backend.pages.auction-templates._form')
    </form>
</div>
@endsection
