<?php

declare(strict_types=1);

namespace App\Services\Blog;

use App\Models\MatchReport;
use App\Models\Matches;
use App\Services\Poster\MatchStatsTableData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Drafting a match blog post with OpenAI.
 *
 * The model is given two things: the text of the CricHeroes PDF, and a fact sheet built from
 * this database. The fact sheet matters — a PDF read by pdftotext is a column-mangled wall of
 * numbers, and a model left alone with it invents plausible scores. Everything the app already
 * knows for certain (teams, totals, result, award winners, top performers) is stated separately
 * and the model is told those win.
 *
 * The key is read from config at call time and never logged. Absent key is a first-class state,
 * reported to the organizer rather than surfacing as a 401 from api.openai.com.
 */
class BlogGenerationService
{
    public const TONES = [
        'report' => 'a straight match report, factual and neutral',
        'exciting' => 'an energetic fan-facing write-up that builds drama around the turning points',
        'analytical' => 'an analytical piece that explains why the result happened',
    ];

    public const LENGTHS = [
        'short' => '250-350 words, three or four short paragraphs',
        'standard' => '450-600 words with a couple of sub-headings',
        'detailed' => '800-1000 words with sub-headings for each innings and the key performers',
    ];

    public function __construct(private readonly AiSettings $settings) {}

    public function isConfigured(): bool
    {
        return $this->settings->hasKey();
    }

    public function model(): string
    {
        return $this->settings->model();
    }

    /**
     * List price in USD for a token count, or null when the model has no published price here.
     *
     * Unknown is a normal answer, not an error: model ids change constantly and the settings page
     * accepts any of them, so a missing price must never stop a post being written — it just
     * means the cost column shows a dash.
     */
    public function priceFor(string $model, int $promptTokens, int $completionTokens): ?float
    {
        /*
         * Indexed, not dot-notated.
         *
         * config('services.ai.pricing.' . $model) reads the dots in a model id as nested keys,
         * so "gemini-2.5-flash" resolved as pricing → gemini-2 → 5-flash and came back null.
         * Every dotted id — the whole Gemini range and gpt-5.6-* — silently had no price.
         */
        $rates = (array) (config('services.ai.pricing', [])[$model] ?? []);

        if (! isset($rates['input'], $rates['output'])) {
            return null;
        }

        return round(
            ($promptTokens / 1_000_000) * (float) $rates['input']
            + ($completionTokens / 1_000_000) * (float) $rates['output'],
            6
        );
    }

    /**
     * A rough per-post cost, shown BEFORE anything has been generated.
     *
     * A stated assumption rather than a measurement: a typical run is a couple of thousand tokens
     * of fact sheet and scorecard in, and a few hundred words of article out. Once real
     * generations exist the page shows their actual average instead.
     */
    public function estimatedCost(?string $model = null): ?float
    {
        return $this->priceFor($model ?? $this->model(), 2500, 800);
    }

    /**
     * @param  array{tone?: string, length?: string, instructions?: string}  $options
     * @return array{title: string, excerpt: string, content: string, model: string, prompt_tokens: int, completion_tokens: int, cost_usd: ?float}
     */
    public function generate(MatchReport $report, array $options = []): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException(
                'No API key is configured. Add one under Settings → AI & Blog.'
            );
        }

        $match = $report->match;
        if (! $match) {
            throw new \RuntimeException('This report is not attached to a match.');
        }

        $tone = self::TONES[$options['tone'] ?? 'report'] ?? self::TONES['report'];
        $length = self::LENGTHS[$options['length'] ?? 'standard'] ?? self::LENGTHS['standard'];
        $extra = trim((string) ($options['instructions'] ?? ''));

        $model = $this->model();
        $endpoint = rtrim($this->settings->baseUrl(), '/') . '/chat/completions';

        $response = Http::withToken((string) $this->settings->apiKey())
            ->timeout((int) config('services.ai.timeout', 120))
            ->acceptJson()
            ->post($endpoint, [
                'model' => $model,
                // JSON mode: parsing prose for "the title is..." is guesswork, and a malformed
                // draft would land in the database as the post body.
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.7,
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $this->userPrompt($match, $report, $tone, $length, $extra)],
                ],
            ]);

        if ($response->failed()) {
            // The body can echo request content; log the status and the API's own message only.
            $apiMessage = $response->json('error.message') ?? 'HTTP ' . $response->status();
            Log::warning('Blog generation failed', ['status' => $response->status(), 'endpoint' => $endpoint, 'model' => $model, 'message' => $apiMessage]);

            /*
             * The provider's own message always wins.
             *
             * A 404 here was assumed to mean a wrong base URL, and the real message was thrown
             * away to say so — which hid Google telling us, in plain words, "this model is no
             * longer available to new users, use models/gemini-3.6-flash". A guessed diagnosis
             * that discards the actual one is worse than no diagnosis.
             *
             * The base-URL hint is only added when the provider said nothing at all, which is
             * what a genuinely wrong path looks like: a 404 with an empty body.
             */
            if ($response->json('error.message') === null && $response->status() === 404) {
                throw new \RuntimeException(sprintf(
                    'The provider returned 404 for %s with no explanation, which usually means the API Base URL is wrong — check it in Settings → AI & Blog.',
                    $endpoint
                ));
            }

            throw new \RuntimeException(sprintf('%s (model %s)', $apiMessage, $model));
        }

        $decoded = $this->decodeDraft($response->json('choices.0.message.content'));

        if (! is_array($decoded) || empty($decoded['content'])) {
            throw new \RuntimeException('The model returned a response this app could not read. Try generating again.');
        }

        // What OpenAI says it charged for, so the dashboard reports a real average rather than
        // an estimate built from guessed prompt sizes.
        $promptTokens = (int) ($response->json('usage.prompt_tokens') ?? 0);
        $completionTokens = (int) ($response->json('usage.completion_tokens') ?? 0);

        return [
            'title' => $this->cleanLine($decoded['title'] ?? $this->fallbackTitle($match)),
            'excerpt' => $this->cleanLine($decoded['excerpt'] ?? ''),
            'content' => $this->sanitiseHtml((string) $decoded['content']),
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'cost_usd' => $this->priceFor($model, $promptTokens, $completionTokens),
        ];
    }

    /**
     * Read the JSON the model was asked for, tolerantly.
     *
     * OpenAI honours response_format and returns bare JSON. Other OpenAI-dialect providers —
     * Groq, Gemini's compatibility layer — treat it as a strong hint and quite often wrap the
     * object in a ```json fence or add a sentence either side. Insisting on a clean parse would
     * make the feature look broken on exactly the free providers it is meant to support.
     */
    private function decodeDraft(mixed $raw): ?array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $text = trim($raw);

        $direct = json_decode($text, true);
        if (is_array($direct)) {
            return $direct;
        }

        // ```json { ... } ```
        if (preg_match('/```(?:json)?\s*(.+?)```/is', $text, $m)) {
            $fenced = json_decode(trim($m[1]), true);
            if (is_array($fenced)) {
                return $fenced;
            }
        }

        // Last resort: the outermost {...} in the reply.
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $slice = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($slice)) {
                return $slice;
            }
        }

        return null;
    }

    private function systemPrompt(): string
    {
        return implode(' ', [
            'You are a cricket journalist writing for a tournament website.',
            'You will be given verified match facts from the tournament database and the raw text of a scorecard PDF.',
            'The verified facts are authoritative: where the PDF text disagrees with them, or is unreadable, follow the facts.',
            'Never invent a score, a name, a wicket or a statistic that does not appear in the material you are given.',
            'If something is not in the material, leave it out rather than guessing.',
            'Respond with a JSON object with exactly these keys: "title" (plain text, under 90 characters),',
            '"excerpt" (plain text, one or two sentences, under 200 characters),',
            'and "content" (the article as simple HTML using only <h2>, <h3>, <p>, <ul>, <li>, <strong> and <em> tags —',
            'no <html>, <head>, <body>, <script>, <style> or inline styles, and no markdown).',
        ]);
    }

    private function userPrompt(Matches $match, MatchReport $report, string $tone, string $length, string $extra): string
    {
        $sections = [];

        $sections[] = "VERIFIED MATCH FACTS (authoritative):\n" . $this->factSheet($match);

        $text = trim((string) $report->extracted_text);
        $sections[] = $text !== ''
            ? "SCORECARD PDF TEXT (may be column-mangled; use for detail, defer to the facts above):\n" . $text
            : 'SCORECARD PDF TEXT: none could be read from the upload. Write from the verified facts alone.';

        $sections[] = "STYLE: Write {$tone}. Length: {$length}.";

        if ($extra !== '') {
            $sections[] = "ADDITIONAL INSTRUCTIONS FROM THE EDITOR:\n" . $extra;
        }

        return implode("\n\n---\n\n", $sections);
    }

    /** Everything the database knows for certain, stated plainly. */
    private function factSheet(Matches $match): string
    {
        $match->loadMissing(['tournament', 'teamA', 'teamB', 'result', 'winner', 'ground', 'matchAwards.player', 'matchAwards.tournamentAward']);
        $result = $match->result;

        $lines = [];
        $add = function (string $label, $value) use (&$lines) {
            $value = trim((string) $value);
            if ($value !== '' && $value !== '0') {
                $lines[] = "- {$label}: {$value}";
            }
        };

        $add('Tournament', $match->tournament?->name);
        $add('Match', trim(($match->teamA?->name ?? 'Team A') . ' vs ' . ($match->teamB?->name ?? 'Team B')));
        $add('Stage', $match->stage_display ?? $match->stage);
        $add('Match number', $match->match_number);
        $add('Date', $match->match_date?->format('j F Y'));
        $add('Venue', $match->ground?->name ?? $match->venue ?? $match->location);
        $add('Result', $result?->result_text ?? $result?->result_summary ?? $match->result_text);
        $add('Winner', $match->winner?->name);

        if ($result) {
            $aFirst = $result->team_a_batting_first ?? true;
            $first = $aFirst ? $match->teamA : $match->teamB;
            $second = $aFirst ? $match->teamB : $match->teamA;
            $add('Batted first', $first?->name);
            $add('Batted second', $second?->name);

            if ($result->toss_winner_team_id || $match->toss_winner_team_id) {
                $tossWinner = $match->teamA?->id === ($result->toss_winner_team_id ?? $match->toss_winner_team_id)
                    ? $match->teamA?->name : $match->teamB?->name;
                $add('Toss', trim(($tossWinner ?? '') . ' won the toss and chose to ' . ($match->toss_decision ?? 'bat')));
            }
        }

        foreach ($match->matchAwards as $award) {
            $slug = $award->tournamentAward?->slug;
            $name = $award->player?->name;
            if ($name && $slug) {
                $add(ucwords(str_replace('-', ' ', $slug)), $name);
            }
        }

        // The same tables the summary poster draws, so the article and the graphic agree.
        $tables = MatchStatsTableData::build($match, [
            'a' => $match->teamA?->short_name ?? $match->teamA?->name,
            'b' => $match->teamB?->short_name ?? $match->teamB?->name,
        ]);

        foreach (['match_summary_table' => 'Innings summary', 'top_batting' => 'Top batting', 'top_bowling' => 'Top bowling'] as $key => $label) {
            if (! empty($tables[$key])) {
                // UNESCAPED_SLASHES matters: a cricket score is written 185/4, and json_encode
                // turns that into 185\/4 by default — noise the model has to see through.
                $lines[] = "- {$label}: " . json_encode(
                    array_slice($tables[$key], 0, 5),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            }
        }

        return $lines ? implode("\n", $lines) : '- No structured data is recorded for this match.';
    }

    private function fallbackTitle(Matches $match): string
    {
        return trim(($match->teamA?->name ?? 'Team A') . ' vs ' . ($match->teamB?->name ?? 'Team B'));
    }

    private function cleanLine(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($value)));
    }

    /**
     * Keep the article to the tags the front end renders.
     *
     * The body is stored and later echoed on a public page, so the model is not trusted to have
     * obeyed the tag list — a prompt is a request, not a constraint.
     */
    private function sanitiseHtml(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);
        $html = strip_tags($html, '<h2><h3><h4><p><ul><ol><li><strong><em><b><i><br><blockquote>');
        // Attributes are stripped wholesale: nothing in the allowed set needs one, and that
        // closes off onclick=, style= and href=javascript: in one move.
        $html = preg_replace('#<([a-z0-9]+)\s[^>]*>#i', '<$1>', $html);

        return trim($html);
    }
}
