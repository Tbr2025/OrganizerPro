@extends('backend.layouts.app')

@section('title')
    {{ $breadcrumbs['title'] }} | {{ config('app.name') }}
@endsection

@section('admin-content')
    <div class="p-4 mx-auto max-w-screen-xl md:p-6">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

        <div class="space-y-6">
            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-5 space-y-6 border-t border-gray-100 dark:border-gray-800 sm:p-6">
                    <form action="{{ route('admin.teams.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <label for="name" class="form-label">{{ __('Team Name') }}</label>
                                <input type="text" name="name" id="name" class="form-control" required>
                            </div>

                            <div>
                                <label for="short_name" class="form-label">{{ __('Short Name') }}</label>
                                <input type="text" name="short_name" id="short_name" class="form-control">
                            </div>



                            <div>
                                <label for="admin_id" class="form-label">{{ __('Team Admin (Organizer)') }}</label>
                                <select name="admin_id" id="admin_id" class="form-control">
                                    <option value="">— {{ __('None') }} —</option>
                                    @foreach ($admins as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="logo" class="form-label">{{ __('Team Logo') }}</label>
                                <input type="file" name="logo" id="logo" class="form-control" accept="image/*">
                            </div>
                        </div>

                        <div class="mt-6">
                            <x-buttons.submit-buttons cancelUrl="{{ route('admin.teams.index') }}" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
