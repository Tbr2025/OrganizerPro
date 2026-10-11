<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Credits\CreditFeature;
use App\Models\Credits\CreditFeatureUnlock;
use App\Models\Credits\CreditTopupRequest;
use App\Models\Organization;
use App\Models\Tournament;
use App\Services\Credits\CreditService;
use App\Services\Credits\InsufficientCreditsException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * An organization's wallet: balance, history, prices, unlocks and top-up
 * requests — and the page that confirms an unlock before anything is spent.
 */
class CreditController extends Controller
{
    public function __construct(private readonly CreditService $credits)
    {
        $this->middleware('permission:credits.view|credits.manage');
    }

    public function index(Request $request): View
    {
        $org = $this->organization($request);
        $wallet = $this->credits->wallet($org);

        $features = CreditFeature::where('is_active', true)->orderBy('sort')->get()
            ->map(fn ($f) => ['feature' => $f, 'quote' => $this->credits->quote($org, $f->key)]);

        return view('backend.pages.credits.index', [
            'org' => $org,
            'wallet' => $wallet,
            'ledger' => $wallet->ledger()->latest('id')->paginate(25),
            'features' => $features,
            'unlocks' => CreditFeatureUnlock::where('organization_id', $org->id)->latest('id')->limit(50)->get(),
            'topups' => CreditTopupRequest::where('organization_id', $org->id)->latest('id')->limit(20)->get(),
            'credits' => $this->credits,
            'organizations' => Auth::user()->can('credits.manage') ? Organization::orderBy('name')->get() : collect(),
            'breadcrumbs' => ['title' => $this->credits->term()],
        ]);
    }

    /**
     * The balance for the header pill and the cost tips beside paid actions
     * (Generate & Save). Read on page load and again after anything is spent.
     */
    public function balance(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $org = $user->organization_id ? Organization::find($user->organization_id) : null;
        if (! $org || ! $org->usesCredits()) {
            return response()->json(['show' => false, 'manage_url' => $user->can('credits.manage') ? route('admin.credits.manage') : null]);
        }

        $balance = $this->credits->balance($org);
        $poster = $this->credits->quote($org, CreditFeature::POSTER);

        return response()->json([
            'show' => true,
            'balance' => $balance,
            'label' => $this->credits->format($balance),
            'charges' => $this->credits->charges($org),
            'poster_cost' => $poster['cost'],
            'poster_label' => $poster['cost'] > 0 ? $this->credits->format($poster['cost']) : null,
            'url' => route('admin.credits.index'),
        ]);
    }

    public function requestTopup(Request $request): RedirectResponse
    {
        $org = $this->organization($request);
        $data = $request->validate([
            'credits' => 'required|integer|min:1|max:1000000',
            'discount_code' => 'nullable|string|max:40',
            'payment_reference' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:500',
        ]);

        $topup = $this->credits->requestTopup(
            $org, (int) $data['credits'], $data['discount_code'] ?? null,
            $data['payment_reference'] ?? null, $data['note'] ?? null, $request->user()
        );

        return back()->with('success', "Request sent: {$topup->credits} " . $this->credits->term($topup->credits)
            . " for {$topup->currency} {$topup->price}. They are added once the payment is confirmed.");
    }

    /** What an unlock costs, before anything is spent. */
    public function confirmUnlock(Request $request): View
    {
        [$feature, $tournament, $org] = $this->unlockTarget($request);
        $quote = $this->credits->quote($org, $feature->key);
        $balance = $this->credits->balance($org);

        return view('backend.pages.credits.unlock', [
            'feature' => $feature,
            'tournament' => $tournament,
            'org' => $org,
            'quote' => $quote,
            'balance' => $balance,
            'enough' => $balance >= $quote['cost'],
            'return' => $this->safeReturn($request->query('return')),
            'status' => $request->query('status'),
            'credits' => $this->credits,
            'breadcrumbs' => ['title' => __('Confirm')],
        ]);
    }

    public function unlock(Request $request): RedirectResponse
    {
        [$feature, $tournament, $org] = $this->unlockTarget($request);
        $return = $this->safeReturn($request->input('return'));

        try {
            $this->credits->unlock($org, $feature->key, $tournament, $request->user());
        } catch (InsufficientCreditsException $e) {
            return redirect()->route('admin.credits.index')->with('error', $e->getMessage() . ' Request a top-up below.');
        }

        // Publishing: apply the status that was waiting for this.
        $status = $request->input('status');
        if ($feature->key === CreditFeature::TOURNAMENT_PUBLISH && in_array($status, ['registration', 'active', 'completed'], true)) {
            $tournament->update(['status' => $status]);
        }

        return redirect($return)->with('success', "{$feature->name} unlocked for {$tournament->name}.");
    }

    /** @return array{0: CreditFeature, 1: Tournament, 2: Organization} */
    private function unlockTarget(Request $request): array
    {
        $data = $request->validate([
            'feature' => ['required', Rule::exists('credit_features', 'key')->where('charge_trigger', 'unlock_once')],
            'tournament' => 'required|integer',
        ]);

        $tournament = Tournament::forUser(Auth::user())->findOrFail($data['tournament']);
        abort_unless(Auth::user()->can('tournament.view') || Auth::user()->can('auction.view') || Auth::user()->can('ledwall.operate'), 403);

        return [CreditFeature::where('key', $data['feature'])->firstOrFail(), $tournament, $tournament->organization()->firstOrFail()];
    }

    /** Only ever back into this application — never an open redirect. */
    private function safeReturn(?string $url): string
    {
        $fallback = route('admin.credits.index');
        if (! $url) {
            return $fallback;
        }
        $base = rtrim(url('/'), '/');

        return str_starts_with($url, $base . '/') || $url === $base || (str_starts_with($url, '/') && ! str_starts_with($url, '//'))
            ? $url
            : $fallback;
    }

    private function organization(Request $request): Organization
    {
        $user = Auth::user();
        if ($user->can('credits.manage')) {
            // Whoever runs credits sees every wallet: open the one picked,
            // else their own, else the first — the page has a switcher.
            $org = $request->filled('org')
                ? Organization::findOrFail($request->integer('org'))
                : Organization::find($user->organization_id) ?? Organization::orderBy('name')->first();
            if (! $org) {
                throw new HttpResponseException(redirect()->route('admin.credits.manage'));
            }

            return $org;
        }

        if (! $user->organization_id || ! ($org = Organization::find($user->organization_id))) {
            throw new HttpResponseException(redirect()->route('admin.dashboard')
                ->with('error', 'Your account is not linked to an organization yet, so it has no ' . strtolower($this->credits->term()) . ' wallet. Ask Sportzley to link it.'));
        }

        return $org;
    }
}
