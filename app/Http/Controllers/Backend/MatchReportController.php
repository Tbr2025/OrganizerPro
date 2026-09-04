<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\MatchReport;
use App\Models\Matches;
use App\Services\Blog\BlogGenerationService;
use App\Services\Blog\MatchBlogService;
use App\Services\Blog\MatchReportPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The CricHeroes match-report PDF, and the blog post drafted from it.
 *
 * Superadmin only, enforced here rather than only in the view: hiding a button is a UI choice,
 * not a permission, and these routes spend money at OpenAI.
 */
class MatchReportController extends Controller
{
    public function __construct(
        private readonly MatchReportPdfService $pdf,
        private readonly BlogGenerationService $ai,
        private readonly MatchBlogService $blog,
    ) {
        $this->middleware(function ($request, $next) {
            if (! auth()->user()?->hasRole('Superadmin')) {
                abort(403, 'Only Super Admin can generate match blogs.');
            }

            return $next($request);
        });
    }

    /** Store the PDF and read it, replacing whatever was there before. */
    public function upload(Matches $match, Request $request): RedirectResponse
    {
        $request->validate([
            'report_pdf' => 'required|file|mimes:pdf|max:10240',
        ], [
            'report_pdf.mimes' => 'The match report has to be a PDF — CricHeroes exports one from the scorecard screen.',
        ]);

        $report = MatchReport::firstOrNew(['match_id' => $match->id]);

        // Replacing the source: the old file is of no further use and is not referenced anywhere
        // else, unlike player photos which are shared between records.
        $this->pdf->delete($report->pdf_path);

        $report->fill($this->pdf->store($request->file('report_pdf')));
        $report->created_by = auth()->id();

        try {
            $report->extracted_text = $this->pdf->extractText($report->pdf_path);
        } catch (\Throwable $e) {
            $report->extracted_text = null;
            $report->save();

            return back()->with('error', $e->getMessage());
        }

        $report->save();

        if (! $report->hasUsableText()) {
            return back()->with('warning', 'The PDF uploaded, but almost no text could be read from it — it may be a scan or an image export. You can still generate a blog from the match data this site already holds.');
        }

        return back()->with('success', 'Match report uploaded and read. You can generate the blog now.');
    }

    /** Draft the post with OpenAI. */
    public function generate(Matches $match, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tone' => 'nullable|in:' . implode(',', array_keys(BlogGenerationService::TONES)),
            'length' => 'nullable|in:' . implode(',', array_keys(BlogGenerationService::LENGTHS)),
            'instructions' => 'nullable|string|max:1000',
            'status' => 'nullable|in:draft,publish',
        ]);

        $report = MatchReport::firstOrNew(['match_id' => $match->id]);
        if (! $report->exists) {
            // Generating with no PDF is supported — the fact sheet alone is enough for a short
            // report — but the row has to exist to hold the result.
            $report->created_by = auth()->id();
            $report->save();
        }

        $report->setRelation('match', $match);

        try {
            $draft = $this->ai->generate($report, $validated);
        } catch (\Throwable $e) {
            Log::warning('Match blog generation failed', ['match_id' => $match->id, 'message' => $e->getMessage()]);

            return back()->with('error', $e->getMessage());
        }

        $post = $this->blog->publish($report, $draft, $validated['status'] ?? 'draft');

        return back()->with('success', sprintf(
            'Blog %s: "%s". Edit it, or view it at /blog/%s.',
            $post->wasRecentlyCreated ? 'generated' : 'regenerated',
            $post->title,
            $post->slug
        ));
    }

    /** Remove the uploaded PDF. The post, once written, is the editor's to keep or delete. */
    public function destroy(Matches $match): RedirectResponse
    {
        $report = MatchReport::where('match_id', $match->id)->first();

        if ($report) {
            $this->pdf->delete($report->pdf_path);
            $report->update(['pdf_path' => null, 'pdf_name' => null, 'pdf_size' => null, 'extracted_text' => null]);
        }

        return back()->with('success', 'Match report PDF removed. The blog post, if one was generated, is untouched.');
    }
}
