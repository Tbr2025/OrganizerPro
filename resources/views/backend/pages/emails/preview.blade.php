@extends('backend.layouts.app')

@section('title')
{{ __('Email Preview') }} | {{ config('app.name') }}
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/quill/quill.min.css') }}">
<style>
    .ql-editor { min-height: 180px; max-height: 350px; overflow-y: auto; }
    .dark .ql-toolbar.ql-snow { border-color: #374151; background: #1f2937; }
    .dark .ql-toolbar .ql-stroke { stroke: #9ca3af !important; }
    .dark .ql-toolbar .ql-fill { fill: #9ca3af !important; }
    .dark .ql-toolbar .ql-picker-label { color: #9ca3af !important; }
    .dark .ql-toolbar .ql-picker-options { background: #1f2937; border-color: #374151; }
    .dark .ql-container.ql-snow { border-color: #374151; background: #111827; }
    .dark .ql-editor { color: #e5e7eb; }
    .dark .ql-editor.ql-blank::before { color: #6b7280; }
</style>
@endpush

@section('admin-content')
<div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6" x-data="emailEditors()">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <x-breadcrumbs :breadcrumbs="[
        ['name' => __('Home'), 'url' => route('admin.dashboard')],
        ['name' => __('Email Preview'), 'url' => route('admin.emails.preview'), 'active' => true],
    ]" />

    <div class="space-y-6">
        {{-- Header + tournament selector + brand name --}}
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ __('Email Preview & Editor') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Edit the full HTML of each email. Per-tournament edits override the global default; nothing is sent from here.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <form method="GET" action="{{ route('admin.emails.preview') }}" class="flex items-end gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Editing scope') }}</label>
                        <select name="tournament_id" onchange="this.form.submit()"
                                class="border rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white min-w-64">
                            <option value="0" {{ (int) $selectedId === 0 ? 'selected' : '' }}>{{ __('— Global default (all tournaments) —') }}</option>
                            @foreach($tournaments as $t)
                                <option value="{{ $t->id }}" {{ (int) $selectedId === (int) $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('Brand name') }}</label>
                    <div class="flex items-center gap-2">
                        <input type="text" x-model="brandName"
                               class="border rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white w-48">
                        <button @click="saveBrand()" type="button"
                                class="text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 hover:bg-gray-200">{{ __('Save') }}</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Scope hint --}}
        <div class="rounded-lg border px-4 py-2 text-sm border-indigo-200 bg-indigo-50 text-indigo-800 dark:bg-indigo-900/20 dark:text-indigo-300">
            @if($selectedId === 0)
                {{ __('You are editing the GLOBAL default used by every tournament that has not customized its emails.') }}
            @else
                {{ __('You are editing emails for') }} <strong>{{ $tournament->name }}</strong>. {{ __('Unsaved types fall back to the global default.') }}
            @endif
        </div>

        {{-- Welcome card template note --}}
        <div class="rounded-lg border px-4 py-3 text-sm {{ $hasWelcomeTemplate ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300' : 'border-amber-200 bg-amber-50 text-amber-800 dark:bg-amber-900/20 dark:text-amber-300' }}">
            @if($hasWelcomeTemplate)
                {{ __('This tournament has a welcome_card poster template — the welcome email will include the generated poster as an attachment.') }}
            @elseif($tournament)
                {{ __('This tournament has no welcome_card poster template — the welcome email is still sent, but without the poster attachment.') }}
                <a href="{{ route('admin.tournaments.templates.create', $tournament) }}?type=welcome_card" class="underline font-medium">{{ __('Create one') }}</a>.
            @endif
        </div>

        {{-- Email cards --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            @foreach($editors as $type => $e)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden flex flex-col"
                     x-data="{ editing: false, showVars: false }"
                     x-effect="if (editing && modes['{{ $type }}'] === 'visual') $nextTick(() => initQuill('{{ $type }}'))">
                    <div class="px-4 py-3 border-b dark:border-gray-700 flex items-start justify-between gap-2">
                        <div>
                            <h2 class="font-semibold text-sm text-gray-900 dark:text-white">{{ $e['label'] }}</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ __('Source') }}:
                                <span class="font-medium">
                                    @if($e['source'] === 'tournament') {{ __('This tournament') }}
                                    @elseif($e['source'] === 'global') {{ __('Global default') }}
                                    @else {{ __('Built-in default') }} @endif
                                </span>
                            </p>
                        </div>
                        <button @click="editing = !editing" type="button"
                                class="shrink-0 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded px-2 py-1 hover:bg-gray-200"
                                x-text="editing ? '{{ __('Close') }}' : '{{ __('Edit') }}'"></button>
                    </div>

                    {{-- Editor --}}
                    <div x-show="editing" x-cloak class="p-3 border-b dark:border-gray-700 space-y-2 bg-gray-50 dark:bg-gray-900/40">
                        <div>
                            <label class="block text-[11px] font-medium text-gray-500 mb-1">{{ __('Subject') }}</label>
                            <input type="text" x-model="t['{{ $type }}'].subject"
                                   class="w-full border rounded px-2 py-1.5 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-[11px] font-medium text-gray-500">{{ __('HTML body') }}</label>
                                <div class="flex items-center gap-2">
                                    <div class="inline-flex rounded overflow-hidden border dark:border-gray-600 text-[11px]">
                                        <button type="button" @click="switchMode('{{ $type }}', 'visual')"
                                                :class="modes['{{ $type }}'] === 'visual' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300'"
                                                class="px-2 py-0.5">{{ __('Visual') }}</button>
                                        <button type="button" @click="switchMode('{{ $type }}', 'code')"
                                                :class="modes['{{ $type }}'] === 'code' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300'"
                                                class="px-2 py-0.5">{{ __('Code') }}</button>
                                    </div>
                                    <button type="button" @click="showVars = !showVars" class="text-[11px] text-indigo-600 hover:underline">{{ __('Placeholders') }}</button>
                                </div>
                            </div>
                            <div x-show="showVars" x-cloak class="mb-2 p-2 rounded bg-white dark:bg-gray-800 border dark:border-gray-700 text-[11px] text-gray-600 dark:text-gray-300 flex flex-wrap gap-1">
                                @foreach($e['placeholders'] as $ph)
                                    <code class="px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-700 cursor-pointer"
                                          @click="insertVar('{{ $type }}', '{{ $ph }}')">{{ $ph }}</code>
                                @endforeach
                            </div>
                            {{-- Quill WYSIWYG editor (Visual mode) --}}
                            <div x-show="modes['{{ $type }}'] === 'visual'" class="rounded border dark:border-gray-600 overflow-hidden">
                                <div id="quill-{{ $type }}"></div>
                            </div>
                            {{-- Raw HTML textarea (Code mode) --}}
                            <textarea x-show="modes['{{ $type }}'] === 'code'" x-model="t['{{ $type }}'].body" rows="12" spellcheck="false"
                                      class="w-full border rounded px-2 py-1.5 text-xs font-mono dark:bg-gray-900 dark:border-gray-600 dark:text-gray-100"></textarea>
                        </div>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <button type="button" @click="previewDraft('{{ $type }}')"
                                    class="text-xs bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-100 rounded px-3 py-1.5 hover:bg-gray-300">{{ __('Preview draft') }}</button>
                            <button type="button" @click="save('{{ $type }}')"
                                    class="text-xs bg-indigo-600 text-white rounded px-3 py-1.5 hover:bg-indigo-700">{{ __('Save') }}</button>
                            <button type="button" @click="reset('{{ $type }}')"
                                    class="text-xs bg-red-100 text-red-700 rounded px-3 py-1.5 hover:bg-red-200">{{ __('Reset to default') }}</button>
                        </div>
                    </div>

                    {{-- Live preview --}}
                    <div class="bg-gray-100 dark:bg-gray-900 p-2">
                        <iframe :id="'iframe-{{ $type }}'"
                                src="{{ route('admin.emails.preview.render', $type) }}?tournament_id={{ $selectedId }}"
                                class="w-full bg-white rounded" style="height: 620px; border: 0;"
                                title="{{ $e['label'] }}"></iframe>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('vendor/quill/quill.min.js') }}"></script>
<script>
function emailEditors() {
    return {
        selectedId: {{ $selectedId }},
        brandName: @json($brandName),
        t: {
            @foreach($editors as $type => $e)
            '{{ $type }}': { subject: @json($e['subject']), body: @json($e['body_html']) },
            @endforeach
        },
        modes: {
            @foreach($editors as $type => $e)
            '{{ $type }}': 'visual',
            @endforeach
        },
        quills: {},
        token: document.querySelector('meta[name="csrf-token"]').content,

        _req(url, body, method = 'POST') {
            return fetch(url, {
                method,
                headers: { 'X-CSRF-TOKEN': this.token, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: body ? JSON.stringify(body) : null,
            });
        },

        initQuill(type) {
            if (this.quills[type]) return;
            const el = document.getElementById('quill-' + type);
            if (!el) return;
            const quill = new Quill(el, {
                theme: 'snow',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ color: [] }, { background: [] }],
                        [{ header: [1, 2, 3, false] }],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        [{ align: [] }],
                        ['link'],
                        ['clean'],
                    ],
                },
            });
            quill.root.innerHTML = this.t[type].body;
            this.quills[type] = quill;
        },

        switchMode(type, mode) {
            if (this.modes[type] === mode) return;
            if (mode === 'visual') {
                this.modes[type] = mode;
                this.$nextTick(() => {
                    this.initQuill(type);
                    if (this.quills[type]) {
                        this.quills[type].root.innerHTML = this.t[type].body;
                    }
                });
            } else {
                this.syncFromQuill(type);
                this.modes[type] = mode;
            }
        },

        syncFromQuill(type) {
            if (this.quills[type]) {
                this.t[type].body = this.quills[type].root.innerHTML;
            }
        },

        insertVar(type, ph) {
            if (this.modes[type] === 'visual' && this.quills[type]) {
                const quill = this.quills[type];
                const range = quill.getSelection(true);
                quill.insertText(range.index, ph);
            } else {
                this.t[type].body += ph;
            }
        },

        async previewDraft(type) {
            this.syncFromQuill(type);
            const url = `{{ url('admin/emails/preview') }}/${type}/draft?tournament_id=${this.selectedId}`;
            const res = await this._req(url, { subject: this.t[type].subject, body_html: this.t[type].body });
            const html = await res.text();
            document.getElementById('iframe-' + type).srcdoc = html;
        },

        async save(type) {
            this.syncFromQuill(type);
            const res = await this._req(`{{ route('admin.emails.templates.save') }}`, {
                type,
                tournament_id: this.selectedId || null,
                subject: this.t[type].subject,
                body_html: this.t[type].body,
            });
            const data = await res.json().catch(() => ({}));
            if (data.success) {
                this._refresh(type);
                this._toast('Saved.');
            } else {
                this._toast('Save failed.', true);
            }
        },

        async reset(type) {
            if (!confirm('Remove this override and fall back to the default?')) return;
            const res = await this._req(`{{ route('admin.emails.templates.reset') }}`, {
                type, tournament_id: this.selectedId || null,
            }, 'DELETE');
            const data = await res.json().catch(() => ({}));
            if (data.success) {
                this.t[type].subject = data.subject;
                this.t[type].body = data.body_html;
                if (this.quills[type]) {
                    this.quills[type].root.innerHTML = data.body_html;
                }
                this._refresh(type);
                this._toast('Reset to default.');
            }
        },

        async saveBrand() {
            const res = await this._req(`{{ route('admin.emails.brand.save') }}`, { brand_name: this.brandName });
            const data = await res.json().catch(() => ({}));
            this._toast(data.success ? 'Brand name saved.' : 'Failed.', !data.success);
            if (data.success) Object.keys(this.t).forEach(tp => this._refresh(tp));
        },

        _refresh(type) {
            const f = document.getElementById('iframe-' + type);
            f.removeAttribute('srcdoc');
            f.src = `{{ url('admin/emails/preview') }}/${type}/render?tournament_id=${this.selectedId}&_=${Date.now()}`;
        },

        _toast(msg, isError = false) {
            if (window.toastr) { isError ? toastr.error(msg) : toastr.success(msg); }
            else { console.log(msg); }
        },
    };
}
</script>
@endpush
@endsection
