@extends('backend.layouts.app')

@section('title')
    My Registration Details | {{ config('app.name') }}
@endsection

@section('admin-content')
    <div class="p-4 mx-auto max-w-5xl md:p-6">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

        <div class="space-y-6">
            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-5 space-y-6 sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">My Registration Details</h2>
                        <a href="{{ route('home') }}" class="text-sm font-medium text-brand-500 hover:text-brand-600">← Dashboard</a>
                    </div>

                    <form method="POST" action="{{ route('profileplayers.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        @if(($registrations?->count() ?? 0) === 0)
                            <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 text-amber-700 dark:text-amber-300 px-4 py-3 text-sm">
                                You are not registered for any tournament yet, so profile edits can't be submitted.
                            </div>
                        @else
                            {{-- Which tournament this update is for --}}
                            <div class="mb-5">
                                <label for="registration_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tournament this update is for <span class="text-red-500">*</span></label>
                                <select name="registration_id" id="registration_id" required class="form-control max-w-md"
                                    onchange="window.location='{{ route('profileplayers.edit') }}?registration_id=' + this.value">
                                    @foreach($registrations as $reg)
                                        <option value="{{ $reg->id }}" {{ ($selectedRegistration?->id === $reg->id) ? 'selected' : '' }}>
                                            {{ $reg->tournament->name ?? 'Tournament #' . $reg->tournament_id }}
                                            @if($reg->isApproved()) — Accepted @elseif(!empty($reg->pending_changes)) — Changes pending @endif
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Only <strong>un-verified</strong> fields can be edited. Changes are sent to this tournament's admin for approval before they reflect.</p>
                            </div>

                            @if(!empty($selectedRegistration?->pending_changes))
                                <div class="mb-5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-900/40 text-amber-800 dark:text-amber-300 px-4 py-3 text-sm">
                                    <strong>Changes pending approval.</strong> You submitted updates
                                    @if($selectedRegistration->pending_changes_submitted_at) on {{ $selectedRegistration->pending_changes_submitted_at->format('d M Y, H:i') }}@endif.
                                    They will reflect once an admin approves them.
                                </div>
                            @endif

                            @if($isLocked ?? false)
                                <div class="mb-5 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-300 dark:border-green-800 text-green-800 dark:text-green-300 px-4 py-3 text-sm">
                                    <strong>✔ Your registration has been accepted</strong> for
                                    <strong>{{ $selectedRegistration->tournament->name ?? 'this tournament' }}</strong>. These details are locked.
                                    To update your contact email or password, use your
                                    <a href="{{ route('profile.edit') }}" class="underline font-medium">Account settings</a>.
                                </div>
                            @endif

                            {{-- Player Name (read-only display) --}}
                            <div class="flex items-center gap-4 mb-4 p-4 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                @if($player->image_path)
                                    <img src="{{ asset('storage/' . $player->image_path) }}" alt="{{ $player->name }}" class="w-14 h-14 rounded-full object-cover border-2 border-gray-200 dark:border-gray-600">
                                @else
                                    <div class="w-14 h-14 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xl font-bold text-gray-500 dark:text-gray-400">
                                        {{ strtoupper(substr($player->name, 0, 1)) }}
                                    </div>
                                @endif
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $player->name }}</h3>
                                    @if($player->email)
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $player->email }}</p>
                                    @endif
                                </div>
                            </div>

                            <fieldset @if($isLocked ?? false) disabled @endif class="space-y-6">
                                @php $skip = ['name', 'terms_and_conditions']; @endphp
                                @foreach($layout as $section)
                                    @php
                                        $keys = array_values(array_filter($section['fields'], fn ($k) => ! in_array($k, $skip, true) && $k !== 'image'));
                                        $sectionCustom = $customFields->where('section', $section['key']);
                                        $isPhoto = ($section['key'] === 'Player Photo');
                                    @endphp
                                    @if(count($keys) || $sectionCustom->count() || $isPhoto)
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">{{ $section['title'] }}</h3>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                            @foreach($keys as $key)
                                                @php $locked = ($isLocked ?? false) || in_array($key, $verifiedKeys, true) || in_array($key, $lockedFieldKeys, true); @endphp
                                                @include('backend.pages.profileplayers.partials.field', ['key' => $key, 'locked' => $locked])
                                            @endforeach
                                            @foreach($sectionCustom as $cf)
                                                @php $cfv = $customValues['cf_' . $cf->id] ?? null; if ($cf->type === 'checkbox') { $cfv = ($cfv === '1') ? 'Yes' : (($cfv === '0') ? 'No' : $cfv); } @endphp
                                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                                                    <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $cf->label }} <span class="text-[9px] text-indigo-400">custom</span></h4>
                                                    <p class="mt-1 text-sm text-gray-900 dark:text-white break-words">{{ ($cfv === null || $cfv === '') ? '—' : $cfv }}</p>
                                                </div>
                                            @endforeach
                                        </div>

                                        @if($isPhoto)
                                            @php $photoLocked = ($isLocked ?? false) || in_array('image', $verifiedKeys, true); @endphp
                                            <div class="mt-3 max-w-xs">
                                                @unless($photoLocked)<input type="hidden" name="__present[]" value="image_path">@endunless
                                                <x-player-image-upload name="image_path" :existing-image="$player->image_path" :is-verified="$photoLocked" />
                                                @if($player->image_path && ! $photoLocked)
                                                    <label class="inline-flex items-center mt-2 space-x-2 text-sm text-gray-600 dark:text-gray-300">
                                                        <input type="checkbox" name="clear_image" value="1"> <span>Remove existing image</span>
                                                    </label>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    @endif
                                @endforeach
                            </fieldset>

                            {{-- Submit (hidden once accepted) --}}
                            @unless($isLocked ?? false)
                            <div class="mt-6">
                                <x-buttons.submit-buttons cancelUrl="{{ route('home') }}" />
                            </div>
                            @endunless
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
