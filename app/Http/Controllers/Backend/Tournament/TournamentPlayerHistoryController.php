<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\Tournament;
use App\Services\Auction\PlayerHistoryQuery;
use App\Support\LogoDataUri;
use App\Support\PdfBrowser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The tournament's player history — one row per player, saying how they were acquired, out of
 * which pool, for how much and when, in both IST and Dubai time.
 *
 * The auction module could already answer "who is in THIS pool" and "what did THIS auction sell".
 * What it could not answer without opening an auction, then a pool, then reading, was the whole
 * competition at once: search a name or an email, narrow to a pool or a team or a price band or an
 * evening, and hand the result to a club as a signed-off-looking PDF.
 */
class TournamentPlayerHistoryController extends Controller
{
    public function __construct(private readonly PlayerHistoryQuery $history)
    {
    }

    public function index(Tournament $tournament, Request $request): View
    {
        $this->checkAuthorization(Auth::user(), ['tournament.view']);

        $filters = $this->history->filters($request);
        $context = $this->context($tournament, $filters);

        $query = $this->history->build($tournament, $filters);
        $summary = $this->history->summary($query);

        $rows = $query->paginate(25)->appends($request->query());
        $this->history->decorate($rows);

        return view('backend.pages.tournaments.player-history.index', [
            'tournament' => $tournament,
            'rows' => $rows,
            'summary' => $summary,
            'filters' => $filters,
            'zones' => PlayerHistoryQuery::zones(),
            'filterKeys' => PlayerHistoryQuery::filterKeys(),
            'isFiltered' => $this->history->isFiltered($filters),
            'breadcrumbs' => [
                'title' => __('Player History'),
                'items' => [
                    ['label' => __('Tournaments'), 'url' => route('admin.tournaments.index')],
                    ['label' => $tournament->name, 'url' => route('admin.tournaments.dashboard', $tournament)],
                ],
            ],
        ] + $context);
    }

    /**
     * The same list, as a PDF branded with the tournament's and the auction's own logos.
     */
    public function pdf(Tournament $tournament, Request $request)
    {
        $this->checkAuthorization(Auth::user(), ['tournament.view']);

        $filters = $this->history->filters($request);
        $context = $this->context($tournament, $filters);

        $query = $this->history->build($tournament, $filters);
        $summary = $this->history->summary($query);

        /*
         * A cap, and it says so in the document. A full season across several auctions can run to
         * thousands of rows, and a PDF that silently stops at 2000 reads as "this is everyone" —
         * which is exactly the misreading a report handed to a club must not invite.
         */
        $total = (clone $query)->reorder()->count();
        $rows = $query->limit(PlayerHistoryQuery::PDF_ROW_CAP)->get();
        $this->history->decorate($rows);

        /*
         * The auction logo only makes sense when one auction is in scope. Across all of them
         * there is no single auction to brand the page with, so the tournament's logo stands
         * alone and the header says so rather than picking an auction arbitrarily.
         */
        $auction = $filters['auction_id']
            ? $context['auctions']->firstWhere('id', (int) $filters['auction_id'])
            : null;

        $generatedAt = now();

        $html = view('pdf.player-history', [
            'tournament' => $tournament,
            'auction' => $auction,
            'rows' => $rows,
            'summary' => $summary,
            'filters' => $filters,
            'zones' => PlayerHistoryQuery::zones(),
            'describe' => $this->history->describe(
                $filters,
                $context['auctions'],
                $context['pools'],
                $context['teams']
            ),
            'times' => $this->history->times($generatedAt),
            'omitted' => max(0, $total - $rows->count()),
            'total' => $total,
            'tournamentLogo' => LogoDataUri::from($tournament->settings?->logo ?: $tournament->logo),
            'auctionLogo' => $auction ? LogoDataUri::from($auction->auction_logo) : null,
        ])->render();

        $pdf = PdfBrowser::html($html)
            ->format('A4')
            ->landscape()
            ->showBackground()
            // The bottom margin has to clear the running footer, which Chrome draws outside the
            // page box: too small and the last table row prints on top of it.
            ->margins(10, 10, 18, 10)
            ->showBrowserHeaderAndFooter()
            // Chrome's own header is the URL and the date. Blanked, or it sits above ours.
            ->headerHtml('<span></span>')
            ->footerHtml(view('pdf.partials.player-history-footer')->render())
            ->pdf();

        $filename = 'player-history-' . ($tournament->slug ?: $tournament->id)
            . '-' . $generatedAt->format('Y-m-d') . '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * The dropdown data, and the pool list narrowed to whichever auctions are in scope.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, \Illuminate\Support\Collection<int, mixed>>
     */
    private function context(Tournament $tournament, array $filters): array
    {
        // Tournament has no auctions() relation; this is the lookup used everywhere else.
        $auctions = Auction::where('tournament_id', $tournament->id)
            ->orderByDesc('id')
            ->get(['id', 'name', 'auction_logo', 'amount_unit', 'amount_unit_label']);

        // Scoped to the chosen auction so the pool dropdown cannot offer a pool whose players
        // the auction filter has already excluded.
        $poolAuctionIds = $filters['auction_id']
            ? collect([(int) $filters['auction_id']])
            : $auctions->pluck('id');

        return [
            'auctions' => $auctions,
            'pools' => $this->history->pools($poolAuctionIds),
            'teams' => ActualTeam::forTournament($tournament->id)->orderBy('name')->get(['actual_teams.id', 'actual_teams.name']),
        ];
    }
}
