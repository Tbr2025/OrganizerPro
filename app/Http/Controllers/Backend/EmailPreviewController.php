<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\Tournament;
use App\Models\TournamentTemplate;
use App\Services\Email\EmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailPreviewController extends Controller
{
    public function __construct(private EmailTemplateService $emails)
    {
        $this->middleware(function ($request, $next) {
            abort_unless(auth()->user()?->hasRole('Superadmin'), 403);

            return $next($request);
        });
    }

    /** Email preview + editor dashboard. */
    public function index(Request $request)
    {
        // Org scope + Superadmin-sees-all is handled by the global OrganizationScope.
        $tournaments = Tournament::forUser(auth()->user())->orderBy('name')->get(['id', 'name', 'slug']);

        $selectedId = (int) $request->query('tournament_id', (int) ($tournaments->first()->id ?? 0));
        $tournament = $selectedId > 0 ? Tournament::with('settings')->find($selectedId) : null;
        $selectedId = $tournament?->id ?? 0;

        $hasWelcomeTemplate = $tournament
            ? (bool) $tournament->getTemplate(TournamentTemplate::TYPE_WELCOME_CARD)
            : false;

        // Seed each editor with the raw template for the selected scope.
        $editors = [];
        foreach ($this->emails->types() as $type => $meta) {
            $raw = $this->emails->rawTemplate($type, $tournament);
            $editors[$type] = [
                'label' => $meta['label'],
                'placeholders' => $meta['placeholders'],
                'subject' => $raw['subject'],
                'body_html' => $raw['body_html'],
                'source' => $raw['source'], // tournament | global | default
            ];
        }

        return view('backend.pages.emails.preview', [
            'tournaments' => $tournaments,
            'selectedId' => $selectedId,
            'tournament' => $tournament,
            'editors' => $editors,
            'hasWelcomeTemplate' => $hasWelcomeTemplate,
            'brandName' => $this->emails->brandName(),
        ]);
    }

    /** Render a single email's HTML for an iframe (saved/effective version). */
    public function render(Request $request, string $type): Response
    {
        abort_unless(array_key_exists($type, $this->emails->types()), 404);

        $tournament = $this->tournamentFrom($request);

        try {
            $html = $this->emails->resolve($type, $tournament, null, null)['html'];
        } catch (\Throwable $e) {
            $html = $this->errorHtml($e->getMessage());
        }

        return response($html)->header('Content-Type', 'text/html');
    }

    /** Render an unsaved draft (live preview while editing) — no DB write. */
    public function previewDraft(Request $request, string $type): Response
    {
        abort_unless(array_key_exists($type, $this->emails->types()), 404);

        $data = $request->validate([
            'subject' => 'nullable|string',
            'body_html' => 'nullable|string',
        ]);

        $tournament = $this->tournamentFrom($request);

        try {
            $html = $this->emails->renderRaw(
                $type,
                $tournament,
                $data['subject'] ?? '',
                $data['body_html'] ?? ''
            )['html'];
        } catch (\Throwable $e) {
            $html = $this->errorHtml($e->getMessage());
        }

        return response($html)->header('Content-Type', 'text/html');
    }

    /** Save the global brand name used in emails ({brand_name}). */
    public function saveBrand(Request $request): JsonResponse
    {
        $data = $request->validate(['brand_name' => 'nullable|string|max:255']);
        $this->emails->setBrandName($data['brand_name'] ?? '');

        return response()->json(['success' => true]);
    }

    /** Save (create/update) a template override for a tournament or globally. */
    public function saveTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:' . implode(',', EmailTemplate::TYPES),
            'tournament_id' => 'nullable|integer|exists:tournaments,id',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
        ]);

        EmailTemplate::updateOrCreate(
            ['tournament_id' => $data['tournament_id'] ?: null, 'type' => $data['type']],
            ['subject' => $data['subject'], 'body_html' => $data['body_html'], 'is_active' => true],
        );

        return response()->json(['success' => true]);
    }

    /** Remove an override → fall back to global default, then built-in seed. */
    public function resetTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:' . implode(',', EmailTemplate::TYPES),
            'tournament_id' => 'nullable|integer|exists:tournaments,id',
        ]);

        EmailTemplate::where('type', $data['type'])
            ->where('tournament_id', $data['tournament_id'] ?: null)
            ->delete();

        $raw = $this->emails->rawTemplate(
            $data['type'],
            ($data['tournament_id'] ?? null) ? Tournament::find($data['tournament_id']) : null
        );

        return response()->json(['success' => true, 'subject' => $raw['subject'], 'body_html' => $raw['body_html'], 'source' => $raw['source']]);
    }

    private function tournamentFrom(Request $request): ?Tournament
    {
        $id = (int) $request->query('tournament_id', (int) $request->input('tournament_id', 0));

        return $id > 0 ? Tournament::with('settings')->find($id) : null;
    }

    private function errorHtml(string $message): string
    {
        return '<div style="font-family:sans-serif;padding:24px;color:#b91c1c;">'
            . '<strong>Preview failed.</strong><br>' . e($message) . '</div>';
    }
}
