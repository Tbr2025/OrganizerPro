@extends('backend.layouts.app')

@section('title', 'Live Scoring')

@section('admin-content')
    <div class="max-w-xl mx-auto p-4 bg-white shadow rounded">
        <form method="POST" action="{{ route('admin.background.remove') }}" enctype="multipart/form-data">
            @csrf
            <label class="block mb-2 font-semibold">Upload Image</label>
            <input type="file" name="image" accept="image/*" class="border p-2 w-full mb-4" required>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Remove Background</button>
        </form>

        @if (session('result_url'))
            <hr class="my-6">
            <h3 class="text-lg font-bold mb-2">Result:</h3>
            <img src="{{ asset('storage/processed/' . session('result_url')) }}" class="w-full max-w-sm">
        @endif
    </div>
@endsection
