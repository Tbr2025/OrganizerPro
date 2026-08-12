@extends('backend.layouts.app')

@section('title', 'Create Auction Template | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">
    <x-breadcrumbs :breadcrumbs="['title' => 'Create Template', 'items' => [['label' => 'Auction Templates', 'url' => route('admin.auction-templates.index')]]]" />

    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Create Auction Template</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Configure a new LED wall display template</p>
    </div>

    <form action="{{ route('admin.auction-templates.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('backend.pages.auction-templates._form')
    </form>
</div>
@endsection
