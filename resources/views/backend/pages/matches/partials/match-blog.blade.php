{{-- CricHeroes match report -> AI-drafted blog post.

     Superadmin only. The route enforces it too — hiding a button is a UI choice, not a
     permission — but there is no point rendering a panel nobody else may use. --}}
@php
    $canGenerateBlog = auth()->user()?->hasRole('Superadmin');
    $matchReport = $canGenerateBlog ? \App\Models\MatchReport::with('post')->where('match_id', $match->id)->first() : null;
    $aiReady = $canGenerateBlog && app(\App\Services\Blog\BlogGenerationService::class)->isConfigured();
    $pdfReady = $canGenerateBlog && app(\App\Services\Blog\MatchReportPdfService::class)->isAvailable();
@endphp

@if($canGenerateBlog)
<div class="card rounded-2xl overflow-hidden" x-data="{ options: {{ $matchReport?->post ? 'true' : 'false' }} }">
    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-4 py-3 flex items-center justify-between">
        <h3 class="text-white font-bold flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
            Match Blog
        </h3>
        <span class="text-[10px] font-semibold uppercase tracking-wide bg-white/20 text-white px-2 py-0.5 rounded">Super Admin</span>
    </div>

    <div class="p-4 space-y-4">
        {{-- Environment problems come first: without these, nothing below can work, and the
             reason is a server setting rather than anything the organizer did. --}}
        @unless($pdfReady)
            <div class="text-xs rounded-lg bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 p-3">
                <strong>pdftotext is not available on this server.</strong>
                PDF upload will fail until poppler-utils is installed, or <span class="font-mono">PDFTOTEXT_PATH</span> is set in <span class="font-mono">.env</span>.
            </div>
        @endunless
        @unless($aiReady)
            <div class="text-xs rounded-lg bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 p-3">
                <strong>No OpenAI key is configured.</strong>
                Add <span class="font-mono">OPENAI_API_KEY</span> to the server's <span class="font-mono">.env</span> and clear the config cache.
            </div>
        @endunless

        {{-- 1. The source PDF --}}
        <div>
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">1. Match report PDF</p>

            @if($matchReport?->pdf_path)
                <div class="flex items-center justify-between gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <div class="min-w-0">
                        <p class="text-sm font-medium truncate">{{ $matchReport->pdf_name ?? 'report.pdf' }}</p>
                        <p class="text-xs {{ $matchReport->hasUsableText() ? 'text-green-600' : 'text-amber-600' }}">
                            @if($matchReport->hasUsableText())
                                {{ number_format(mb_strlen($matchReport->extracted_text)) }} characters read
                            @else
                                Almost no text could be read — probably a scan
                            @endif
                        </p>
                    </div>
                    <form action="{{ route('admin.matches.report.destroy', $match) }}" method="POST"
                          onsubmit="return confirm('Remove the uploaded PDF? Any blog post already generated is kept.')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs font-medium text-red-600 hover:text-red-700">Remove</button>
                    </form>
                </div>
            @endif

            <form action="{{ route('admin.matches.report.upload', $match) }}" method="POST" enctype="multipart/form-data" class="mt-2 flex items-center gap-2">
                @csrf
                <input type="file" name="report_pdf" accept="application/pdf" required
                       class="flex-1 min-w-0 text-xs text-gray-600 dark:text-gray-300
                              file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0
                              file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700
                              hover:file:bg-indigo-100 dark:file:bg-indigo-900/30 dark:file:text-indigo-300">
                <button type="submit" class="shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg bg-gray-800 text-white hover:bg-gray-900">
                    {{ $matchReport?->pdf_path ? 'Replace' : 'Upload' }}
                </button>
            </form>
            <p class="text-[11px] text-gray-400 mt-1.5">Export the scorecard as PDF from CricHeroes. Optional — a blog can be written from this site's own match data.</p>
        </div>

        {{-- 2. Generate --}}
        <form action="{{ route('admin.matches.report.generate', $match) }}" method="POST" class="border-t border-gray-200 dark:border-gray-700 pt-4">
            @csrf
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">2. Generate the blog</p>
                <button type="button" @click="options = !options" class="text-xs font-medium text-indigo-600 hover:text-indigo-700"
                        x-text="options ? 'Hide options' : 'Customize'"></button>
            </div>

            <div x-show="options" x-cloak class="space-y-3 mb-3">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Tone</label>
                        <select name="tone" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-xs">
                            <option value="report">Match report</option>
                            <option value="exciting">Exciting</option>
                            <option value="analytical">Analytical</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Length</label>
                        <select name="length" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-xs">
                            <option value="short">Short</option>
                            <option value="standard" selected>Standard</option>
                            <option value="detailed">Detailed</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Extra instructions <span class="text-gray-400 font-normal">— optional</span></label>
                    <textarea name="instructions" rows="2" maxlength="1000"
                              placeholder="e.g. Lead on the last-over finish, and mention the debutant."
                              class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-xs"></textarea>
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Save as</label>
                    <select name="status" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-xs">
                        <option value="draft">Draft — review before it goes live</option>
                        <option value="publish">Published straight away</option>
                    </select>
                </div>
            </div>

            <button type="submit" @disabled(! $aiReady)
                    class="w-full text-sm font-semibold px-4 py-2.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed transition">
                {{ $matchReport?->post ? 'Regenerate Blog' : 'Generate Blog' }}
            </button>
            @if($matchReport?->post)
                <p class="text-[11px] text-amber-600 dark:text-amber-400 mt-1.5">Regenerating overwrites the post's text. Its slug and published state are kept.</p>
            @endif
        </form>

        {{-- 3. The post --}}
        @if($matchReport?->post)
            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">3. The post</p>
                <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <p class="text-sm font-semibold">{{ $matchReport->post->title }}</p>
                    <p class="text-xs text-gray-500 font-mono mt-0.5">/blog/{{ $matchReport->post->slug }}</p>
                    <div class="flex items-center gap-2 mt-1.5">
                        <span class="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded {{ $matchReport->post->status === 'publish' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">
                            {{ $matchReport->post->status === 'publish' ? 'Published' : 'Draft' }}
                        </span>
                        @if($matchReport->generated_at)
                            <span class="text-[11px] text-gray-400">Written {{ $matchReport->generated_at->diffForHumans() }} by {{ $matchReport->model }}</span>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2 mt-3">
                        <a href="{{ route('admin.posts.edit', ['postType' => 'post', 'id' => $matchReport->post->id]) }}"
                           class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100">Edit post</a>
                        @if($matchReport->post->status === 'publish')
                            <a href="{{ route('public.blog.show', $matchReport->post->slug) }}" target="_blank"
                               class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200">View live</a>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endif
