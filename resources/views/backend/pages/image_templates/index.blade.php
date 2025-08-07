@extends('backend.layouts.app')

@section('title', 'Live Scoring')

@section('admin-content')
    <x-backend.card>
        <x-slot name="header">All Templates</x-slot>

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
                            <td>
                                <a href="{{ route('admin.image-templates.edit', $template) }}"
                                    class="btn btn-sm btn-secondary">✏️ Edit</a>
                                <a href="{{ route('admin.image-templates.show', $template) }}"
                                    class="btn btn-sm btn-success">👁️ Preview</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-slot>
    </x-backend.card>
@endsection
