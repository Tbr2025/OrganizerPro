<?php

declare(strict_types=1);

namespace Tests\Feature\Blog;

use App\Models\MatchReport;
use App\Models\MatchResult;
use App\Models\Matches;
use App\Models\Post;
use App\Services\Blog\BlogGenerationService;
use App\Services\Blog\MatchBlogService;
use App\Services\Blog\MatchReportPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Uploading a CricHeroes report and drafting a blog post from it.
 *
 * OpenAI is always faked here: a test that reaches the real API costs money, needs a key in CI,
 * and fails for reasons that have nothing to do with this code.
 */
class MatchBlogGenerationTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function scenario(): array
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org, 'open');
        $alpha = $this->makeTeam($org, 'Royal Strikers', $tournament);
        $beta = $this->makeTeam($org, 'Thunder Kings', $tournament);

        $match = Matches::create([
            'tournament_id' => $tournament->id,
            'name' => 'Royal Strikers vs Thunder Kings',
            'slug' => 'rs-v-tk-' . uniqid(),
            'team_a_id' => $alpha->id,
            'team_b_id' => $beta->id,
            'status' => 'completed',
            'stage' => 'league',
            'match_date' => '2026-09-04',
        ]);

        MatchResult::create([
            'match_id' => $match->id,
            'team_a_batting_first' => true,
            'scorecard_data' => ['innings' => [
                ['team_name' => 'Royal Strikers', 'total_runs' => 185, 'total_wickets' => 4, 'overs_played' => '20.0',
                 'batting' => [['name' => 'Aaron Blake', 'runs' => 88, 'balls' => 44, 'fours' => 9, 'sixes' => 4, 'how_out' => 'c Ali b Nair']],
                 'bowling' => [['name' => 'Rohan Nair', 'overs' => '4', 'runs' => 28, 'wickets' => 2]]],
                ['team_name' => 'Thunder Kings', 'total_runs' => 172, 'total_wickets' => 8, 'overs_played' => '20.0',
                 'batting' => [['name' => 'Imran Ali', 'runs' => 64, 'balls' => 40, 'fours' => 5, 'sixes' => 3, 'how_out' => 'b Blake']],
                 'bowling' => [['name' => 'Aaron Blake', 'overs' => '3.4', 'runs' => 19, 'wickets' => 4]]],
            ]],
        ]);

        // The match page is permission-gated independently of the Superadmin role, so both users
        // need match.view to get as far as the panel this feature adds.
        $viewMatches = Role::firstOrCreate(['name' => 'Match Viewer ' . uniqid(), 'guard_name' => 'web']);
        $viewMatches->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('match.view', 'web'));

        $superadmin = $this->makeSuperadmin($org);
        $superadmin->assignRole($viewMatches);

        $organizer = $this->makeAuctionOperator($org);
        $organizer->assignRole(Role::firstOrCreate(['name' => 'Organizer', 'guard_name' => 'web']));
        $organizer->assignRole($viewMatches);

        return [$match, $superadmin, $organizer];
    }

    /** The shape OpenAI returns: a JSON string inside choices[0].message.content. */
    private function draftBody(array $payload = [], array $usage = ['prompt_tokens' => 2000, 'completion_tokens' => 700]): array
    {
        return ['usage' => $usage, 'choices' => [['message' => ['content' => json_encode(array_merge([
            'title' => 'Blake blitz sends Royal Strikers past Thunder Kings',
            'excerpt' => 'An 88 off 44 set up a 13-run win.',
            'content' => '<h2>A one-sided chase</h2><p>Royal Strikers made <strong>185/4</strong>.</p>',
        ], $payload))]]]];
    }

    private function fakeOpenAi(array $payload = []): void
    {
        config(['services.openai.key' => 'sk-test-not-a-real-key', 'services.openai.model' => 'gpt-4o-mini']);

        Http::fake(['api.openai.com/*' => Http::response($this->draftBody($payload), 200)]);
    }

    #[Test]
    public function a_superadmin_uploads_a_pdf_and_its_text_is_read(): void
    {
        Storage::fake('local');
        [$match, $superadmin] = $this->scenario();

        $this->actingAs($superadmin)
            ->post(route('admin.matches.report.upload', $match), ['report_pdf' => $this->realPdf()])
            ->assertRedirect();

        $report = MatchReport::where('match_id', $match->id)->firstOrFail();
        $this->assertNotNull($report->pdf_path);
        Storage::assertExists($report->pdf_path);

        // The PDF must land on the PRIVATE disk — an unpublished report is nobody's business.
        $this->assertStringStartsWith(MatchReportPdfService::DIRECTORY . '/', $report->pdf_path);
        $this->assertStringContainsString('Royal Strikers', (string) $report->extracted_text);
    }

    #[Test]
    public function generating_writes_a_post_at_the_match_name_and_date_slug(): void
    {
        Storage::fake('local');
        $this->fakeOpenAi();
        [$match, $superadmin] = $this->scenario();

        $this->actingAs($superadmin)
            ->post(route('admin.matches.report.generate', $match), ['tone' => 'report', 'length' => 'standard'])
            ->assertRedirect();

        $post = Post::firstOrFail();
        $this->assertSame('royal-strikers-vs-thunder-kings-2026-09-04', $post->slug);
        $this->assertSame('post', $post->post_type);
        $this->assertSame('draft', $post->status, 'A generated post must not publish itself.');
        $this->assertSame($match->id, $post->meta['match_id']);
        $this->assertSame('cricheroes_pdf', $post->meta['source']);
        $this->assertStringContainsString('185/4', $post->content);
    }

    #[Test]
    public function the_model_is_given_the_verified_facts_not_only_the_pdf(): void
    {
        Storage::fake('local');
        $this->fakeOpenAi();
        [$match, $superadmin] = $this->scenario();

        $this->actingAs($superadmin)->post(route('admin.matches.report.generate', $match));

        Http::assertSent(function ($request) {
            $prompt = collect($request['messages'])->firstWhere('role', 'user')['content'];

            // Without the fact sheet the model is left guessing from a column-mangled PDF.
            $this->assertStringContainsString('VERIFIED MATCH FACTS', $prompt);
            $this->assertStringContainsString('Royal Strikers', $prompt);
            $this->assertStringContainsString('Innings summary', $prompt);
            $this->assertStringContainsString('185/4', $prompt);

            return true;
        });
    }

    #[Test]
    public function custom_options_reach_the_prompt(): void
    {
        Storage::fake('local');
        $this->fakeOpenAi();
        [$match, $superadmin] = $this->scenario();

        $this->actingAs($superadmin)->post(route('admin.matches.report.generate', $match), [
            'tone' => 'exciting',
            'length' => 'detailed',
            'instructions' => 'Lead on the last over.',
            'status' => 'publish',
        ]);

        Http::assertSent(function ($request) {
            $prompt = collect($request['messages'])->firstWhere('role', 'user')['content'];
            $this->assertStringContainsString('energetic fan-facing', $prompt);
            $this->assertStringContainsString('800-1000 words', $prompt);
            $this->assertStringContainsString('Lead on the last over.', $prompt);

            return true;
        });

        $this->assertSame('publish', Post::firstOrFail()->status);
    }

    #[Test]
    public function regenerating_overwrites_the_post_instead_of_making_a_second_one(): void
    {
        Storage::fake('local');
        [$match, $superadmin] = $this->scenario();

        /*
         * One stub, two queued responses.
         *
         * Http::fake() MERGES stubs rather than replacing them, and the first stub matching a URL
         * keeps answering — so faking a second time here would silently replay the first draft.
         */
        config(['services.openai.key' => 'sk-test', 'services.openai.model' => 'gpt-4o-mini']);
        Http::fake(['api.openai.com/*' => Http::sequence()
            ->push($this->draftBody(['title' => 'First draft', 'content' => '<p>First.</p>']))
            ->push($this->draftBody(['title' => 'A second take', 'content' => '<p>Rewritten.</p>']))]);

        $this->actingAs($superadmin)->post(route('admin.matches.report.generate', $match), ['status' => 'publish']);
        $first = Post::firstOrFail();
        $this->assertSame('First draft', $first->title);

        $this->actingAs($superadmin)->post(route('admin.matches.report.generate', $match));

        $this->assertSame(1, Post::count(), 'Regenerating left a duplicate post behind.');

        $first->refresh();
        $this->assertSame('A second take', $first->title);
        $this->assertSame('royal-strikers-vs-thunder-kings-2026-09-04', $first->slug, 'The public URL must not move.');
        $this->assertSame('publish', $first->status, 'Regenerating must not silently unpublish a live page.');
    }

    #[Test]
    public function the_article_html_is_stripped_of_anything_dangerous(): void
    {
        Storage::fake('local');
        $this->fakeOpenAi([
            'content' => '<p onclick="steal()">Hi</p><script>alert(1)</script><a href="javascript:x()">link</a><img src=x onerror=y>',
        ]);
        [$match, $superadmin] = $this->scenario();

        $this->actingAs($superadmin)->post(route('admin.matches.report.generate', $match));

        $content = Post::firstOrFail()->content;
        $this->assertStringNotContainsString('script', $content);
        $this->assertStringNotContainsString('onclick', $content);
        $this->assertStringNotContainsString('javascript:', $content);
        $this->assertStringNotContainsString('onerror', $content);
        $this->assertStringContainsString('<p>Hi</p>', $content);
    }

    #[Test]
    public function a_missing_key_is_reported_rather_than_failing_at_the_api(): void
    {
        Storage::fake('local');
        Http::fake();
        config(['services.openai.key' => null]);
        [$match, $superadmin] = $this->scenario();

        $this->actingAs($superadmin)
            ->post(route('admin.matches.report.generate', $match))
            ->assertRedirect()
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertSame(0, Post::count());
    }

    #[Test]
    public function an_api_failure_leaves_no_half_written_post(): void
    {
        Storage::fake('local');
        config(['services.openai.key' => 'sk-test']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Rate limit reached']], 429)]);
        [$match, $superadmin] = $this->scenario();

        $this->actingAs($superadmin)
            ->post(route('admin.matches.report.generate', $match))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Post::count());
    }

    #[Test]
    public function only_a_superadmin_can_upload_or_generate(): void
    {
        Storage::fake('local');
        Http::fake();
        [$match, , $organizer] = $this->scenario();

        $this->actingAs($organizer)
            ->post(route('admin.matches.report.upload', $match), ['report_pdf' => $this->realPdf()])
            ->assertForbidden();

        $this->actingAs($organizer)
            ->post(route('admin.matches.report.generate', $match))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    #[Test]
    public function a_non_pdf_upload_is_refused(): void
    {
        Storage::fake('local');
        [$match, $superadmin] = $this->scenario();

        $this->actingAs($superadmin)
            ->post(route('admin.matches.report.upload', $match), ['report_pdf' => UploadedFile::fake()->image('scorecard.png')])
            ->assertSessionHasErrors('report_pdf');
    }

    #[Test]
    public function a_draft_wrapped_in_a_markdown_fence_is_still_read(): void
    {
        Storage::fake('local');
        config(['services.openai.key' => 'sk-test']);

        /*
         * What Groq and Gemini's compatibility layer actually return.
         *
         * They treat response_format as a strong hint rather than a guarantee, so the object
         * arrives inside a ```json fence with prose either side. Insisting on a clean parse
         * would make this look broken on exactly the free providers it is meant to support.
         */
        $fenced = "Here is the article you asked for:\n\n```json\n"
            . json_encode(['title' => 'Fenced title', 'excerpt' => 'x', 'content' => '<p>Fenced body.</p>'])
            . "\n```\nHope that helps!";

        Http::fake(['api.openai.com/*' => Http::response([
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            'choices' => [['message' => ['content' => $fenced]]],
        ])]);

        [$match, $superadmin] = $this->scenario();
        $this->actingAs($superadmin)->post(route('admin.matches.report.generate', $match));

        $post = Post::firstOrFail();
        $this->assertSame('Fenced title', $post->title);
        $this->assertStringContainsString('Fenced body.', $post->content);
    }

    #[Test]
    public function a_reply_that_is_not_json_at_all_is_reported_not_stored(): void
    {
        Storage::fake('local');
        config(['services.openai.key' => 'sk-test']);
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'I am afraid I cannot help with that.']]],
        ])]);

        [$match, $superadmin] = $this->scenario();

        $this->actingAs($superadmin)
            ->post(route('admin.matches.report.generate', $match))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Post::count());
    }

    #[Test]
    public function what_a_generation_cost_is_recorded_from_openais_own_usage(): void
    {
        Storage::fake('local');
        config(['services.openai.key' => 'sk-test']);
        add_setting('ai_model_openai', 'gpt-4o-mini');

        Http::fake(['api.openai.com/*' => Http::response(
            $this->draftBody([], ['prompt_tokens' => 2_000_000, 'completion_tokens' => 1_000_000])
        )]);

        [$match, $superadmin] = $this->scenario();
        $this->actingAs($superadmin)->post(route('admin.matches.report.generate', $match));

        $report = MatchReport::where('match_id', $match->id)->firstOrFail();
        $this->assertSame(2_000_000, $report->prompt_tokens);
        $this->assertSame(1_000_000, $report->completion_tokens);

        // gpt-4o-mini: $0.15/1M in + $0.60/1M out => 2 * 0.15 + 1 * 0.60 = $0.90
        $this->assertEqualsWithDelta(0.90, $report->cost_usd, 0.000001);

        $summary = MatchReport::spendSummary();
        $this->assertSame(1, $summary['count']);
        $this->assertEqualsWithDelta(0.90, $summary['average'], 0.000001);
    }

    #[Test]
    public function the_saved_model_is_the_one_that_gets_called(): void
    {
        Storage::fake('local');
        $this->fakeOpenAi();
        config(['services.openai.model' => 'gpt-4o-mini']);
        add_setting('ai_model_openai', 'gpt-5.6-luna');

        [$match, $superadmin] = $this->scenario();
        $this->actingAs($superadmin)->post(route('admin.matches.report.generate', $match));

        Http::assertSent(fn ($request) => $request['model'] === 'gpt-5.6-luna');
        $this->assertSame('gpt-5.6-luna', MatchReport::where('match_id', $match->id)->firstOrFail()->model);
    }

    #[Test]
    public function any_model_id_is_accepted_and_an_unpriced_one_still_generates(): void
    {
        Storage::fake('local');
        config(['services.openai.key' => 'sk-test']);

        /*
         * Model ids change constantly and differ by what a key is entitled to — gemini-3.8-flash
         * exists and a free-tier key is still told it does not. So any id is accepted, and a
         * model with no published price must still write a post: the cost is simply unknown
         * rather than the generation failing or recording a wrong number.
         */
        add_setting('ai_model_openai', 'some-brand-new-model');
        Http::fake(['api.openai.com/*' => Http::response($this->draftBody())]);

        [$match, $superadmin] = $this->scenario();
        $this->actingAs($superadmin)->post(route('admin.matches.report.generate', $match));

        Http::assertSent(fn ($request) => $request['model'] === 'some-brand-new-model');

        $report = MatchReport::where('match_id', $match->id)->firstOrFail();
        $this->assertSame('some-brand-new-model', $report->model);
        $this->assertNull($report->cost_usd, 'An unpriced model records no cost, rather than a wrong one.');
        $this->assertSame(1, Post::count());
    }

    #[Test]
    public function the_panel_renders_on_the_match_page_for_a_superadmin(): void
    {
        Storage::fake('local');
        [$match, $superadmin] = $this->scenario();

        // The permission boundary itself is covered at the route, in
        // only_a_superadmin_can_upload_or_generate — hiding a panel is not a permission. This
        // asserts the panel is actually reachable, which a blade error would otherwise hide.
        $this->actingAs($superadmin)
            ->get(route('admin.matches.show', $match))
            ->assertOk()
            ->assertSee('Match Blog')
            ->assertSee('Generate Blog')
            ->assertSee('Match report PDF')
            ->assertSee('estimated');
    }

    /** A tiny but genuinely valid PDF, so pdftotext has something real to read. */
    private function realPdf(): UploadedFile
    {
        $body = "BT /F1 12 Tf 40 700 Td (Royal Strikers 185/4 beat Thunder Kings 172/8 by 13 runs) Tj ET";
        $objects = [
            "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj",
            "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj",
            "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Resources<</Font<</F1 5 0 R>>>>/Contents 4 0 R>>endobj",
            "4 0 obj<</Length " . strlen($body) . ">>stream\n{$body}\nendstream endobj",
            "5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object . "\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer<</Size " . (count($objects) + 1) . "/Root 1 0 R>>\nstartxref\n{$xref}\n%%EOF";

        $path = tempnam(sys_get_temp_dir(), 'rep') . '.pdf';
        file_put_contents($path, $pdf);

        return new UploadedFile($path, 'match-report.pdf', 'application/pdf', null, true);
    }
}
