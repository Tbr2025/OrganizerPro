@extends('backend.layouts.app')

@section('title', 'Live Scoring')

@section('admin-content')
    <x-backend.card>
        <x-slot name="header">All Templates</x-slot>
        @if (session('success'))
            <div class="bg-green-100 text-green-800 p-2 mb-4 rounded">
                {{ session('success') }}
            </div>
        @endif

        <x-slot name="body">
            <a href="{{ route('admin.image-templates.create') }}" class="btn btn-primary mb-4">+ Create Template</a>

            <table class="table-auto w-full text-sm text-left">
                <thead>
                    <tr class="bg-gray-100">
                        <th>Name</th>
                        <th>Canvas</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($templates as $template)
                        <tr>
                            <td>{{ $template->name }}</td>
                            <td>{{ $template->canvas_width }}x{{ $template->canvas_height }}</td>
                            <td class="space-x-1">
                                <a href="{{ route('admin.image-templates.show', $template) }}"
                                    class="btn btn-sm btn-success">👁️ Preview</a>

                                <a href="{{ route('admin.image-templates.edit', $template) }}"
                                    class="btn btn-sm btn-secondary">✏️ Edit</a>
                                <form action="{{ route('admin.image-templates.remove') }}" method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this template?');"
                                    style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="template_id" value="{{ $template->id }}">
                                    <button type="submit" class="btn btn-sm btn-danger">🗑️ Delete</button>
                                </form>



                            </td>

                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-slot>
    </x-backend.card>
@endsection
