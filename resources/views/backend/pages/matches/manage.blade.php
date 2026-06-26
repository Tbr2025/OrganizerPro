@extends('backend.layouts.app')

@section('title', 'Manage Match | ' . ($match->match_title ?? config('app.name')))

@section('admin-content')
@php
    $activeTab = request('tab') === 'summary'
        ? 'summary'
        : (request('tab') === 'result' ? 'result' : ($match->result ? 'summary' : 'result'));
    $publicUrl = $match->slug ? route('public.match.show', $match) : null;
@endphp

<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Matches', 'route' => route('admin.matches.index')],
    ['name' => $match->match_title ?? 'Match', 'route' => route('admin.matches.show', $match)],
    ['name' => 'Manage'],
]" />

{{-- Shared header: title, public-page link, tabs --}}
<div class="mb-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Manage Match</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $match->match_title ?? ($match->teamA?->name . ' vs ' . $match->teamB?->name) }}</p>
        </div>
        @if($publicUrl)
        <div class="flex items-center gap-2">
            <a href="{{ $publicUrl }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                View Public Page
            </a>
            <button type="button" onclick="navigator.clipboard.writeText('{{ $publicUrl }}').then(()=>{ if(typeof showToast==='function') showToast('Public link copied','success'); })"
                    class="inline-flex items-center gap-1.5 px-3 py-2 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition" title="Copy public link">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                Copy
            </button>
        </div>
        @endif
    </div>

    {{-- Single Scorecard PDF import (shared by both tabs) --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/40 p-4 mb-4">
        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Import Scorecard PDF</label>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Download the match scorecard PDF from CricHeroes and upload it here — scores, result, toss and the detailed scorecard fill in automatically.</p>
        <input type="file" id="manage-pdf-file" accept="application/pdf,.pdf"
               class="block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-teal-100 file:text-teal-700 hover:file:bg-teal-200 dark:file:bg-teal-900/40 dark:file:text-teal-300 cursor-pointer">
        <div class="flex items-center justify-between mt-3 gap-3 flex-wrap">
            <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer">
                <input type="checkbox" id="manage-pdf-swap" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                Swap teams (if Team A / Team B come out reversed)
            </label>
            <button type="button" id="manage-pdf-btn"
                    class="px-5 py-2 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-lg transition flex items-center whitespace-nowrap text-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Upload &amp; Import
            </button>
        </div>
        <div id="manage-pdf-status" class="text-sm mt-2"></div>
        @if($match->result?->scorecard_data)
            <div class="flex items-center justify-between p-3 mt-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                <div class="flex items-center gap-2 text-sm text-green-700 dark:text-green-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Detailed scorecard data imported
                </div>
                <form action="{{ route('admin.matches.result.clear-scorecard', $match) }}" method="POST"
                      onsubmit="return confirm('Clear imported scorecard data? The public scorecard page will show placeholders instead.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-3 py-1 text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400 border border-red-300 dark:border-red-700 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition">Clear Scorecard Data</button>
                </form>
            </div>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200 dark:border-gray-700 flex gap-1">
        <button type="button" data-tab-btn="result"
                class="manage-tab-btn px-5 py-2.5 text-sm font-semibold border-b-2 -mb-px transition {{ $activeTab === 'result' ? 'border-teal-600 text-teal-600 dark:text-teal-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
            Result
        </button>
        <button type="button" data-tab-btn="summary"
                class="manage-tab-btn px-5 py-2.5 text-sm font-semibold border-b-2 -mb-px transition {{ $activeTab === 'summary' ? 'border-teal-600 text-teal-600 dark:text-teal-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
            Summary &amp; Posters
        </button>
    </div>
</div>

<div data-tab-panel="result" class="{{ $activeTab === 'result' ? '' : 'hidden' }}">
    @include('backend.pages.matches.partials.result-form')
</div>
<div data-tab-panel="summary" class="{{ $activeTab === 'summary' ? '' : 'hidden' }}">
    @include('backend.pages.matches.partials.summary-content')
</div>
@endsection

@push('scripts')
<script>
// --- Tab switching ---
(function() {
    const btns = document.querySelectorAll('.manage-tab-btn');
    const panels = document.querySelectorAll('[data-tab-panel]');
    function activate(tab) {
        btns.forEach(b => {
            const on = b.dataset.tabBtn === tab;
            b.classList.toggle('border-teal-600', on);
            b.classList.toggle('text-teal-600', on);
            b.classList.toggle('dark:text-teal-400', on);
            b.classList.toggle('border-transparent', !on);
            b.classList.toggle('text-gray-500', !on);
        });
        panels.forEach(p => p.classList.toggle('hidden', p.dataset.tabPanel !== tab));
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url);
    }
    btns.forEach(b => b.addEventListener('click', () => activate(b.dataset.tabBtn)));
})();

// --- Single Scorecard PDF import (parse + apply, then reload) ---
(function() {
    const btn = document.getElementById('manage-pdf-btn');
    if (!btn) return;
    const fileInput = document.getElementById('manage-pdf-file');
    const swapInput = document.getElementById('manage-pdf-swap');
    const statusEl = document.getElementById('manage-pdf-status');

    btn.addEventListener('click', async () => {
        const file = fileInput?.files?.[0];
        if (!file) { statusEl.innerHTML = '<span class="text-red-500">Choose a scorecard PDF first.</span>'; return; }
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<svg class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg> Importing...';
        statusEl.innerHTML = '<span class="text-blue-500">Reading the scorecard PDF...</span>';
        try {
            const fd = new FormData();
            fd.append('scorecard_pdf', file);
            fd.append('swap_teams', swapInput?.checked ? '1' : '0');
            fd.append('_token', '{{ csrf_token() }}');
            const res = await fetch('{{ route("admin.matches.result.scorecard-pdf", $match) }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: fd,
            });
            const data = await res.json();
            if (!data.success) {
                statusEl.innerHTML = `<span class="text-red-500">${data.message || 'Import failed.'}</span>`;
                btn.disabled = false; btn.innerHTML = original;
                return;
            }
            const d = data.data;
            statusEl.innerHTML = `<span class="text-green-600 font-semibold">Imported: ${d.team_a_score} vs ${d.team_b_score} — ${d.result_summary || ''}. Reloading…</span>`;
            if (typeof showToast === 'function') showToast('Scorecard imported from PDF', 'success');
            setTimeout(() => window.location.reload(), 1200);
        } catch (err) {
            statusEl.innerHTML = `<span class="text-red-500">Error: ${err.message}</span>`;
            btn.disabled = false; btn.innerHTML = original;
        }
    });
})();
</script>
@endpush
