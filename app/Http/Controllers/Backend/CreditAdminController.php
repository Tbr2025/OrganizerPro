<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Credits\CreditFeature;
use App\Models\Credits\CreditLedgerEntry;
use App\Models\Credits\CreditTopupRequest;
use App\Models\Credits\CreditWallet;
use App\Models\Credits\Discount;
use App\Models\Organization;
use App\Services\Credits\CreditService;
use App\Services\Credits\InsufficientCreditsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Super Admin's credit controls: wallets and grants, top-up approvals, feature
 * prices, conversion rate and wording, billing mode per organization,
 * discounts, reports and the reconciliation check.
 */
class CreditAdminController extends Controller
{
    public function __construct(private readonly CreditService $credits)
    {
        $this->middleware('permission:credits.manage');
        $this->middleware('permission:discount.manage')->only(['storeDiscount', 'toggleDiscount']);
        $this->middleware('permission:feature.manage')->only(['updateFeature']);
    }

    public const TABS = ['overview', 'organizations', 'topups', 'prices', 'discounts', 'settings', 'activity'];

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'overview';
        $since = now()->subDays(30);

        $organizations = Organization::orderBy('name')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . $request->input('q') . '%'))
            ->get();
        $allOrganizations = Organization::orderBy('name')->get(['id', 'name', 'billing_mode']);
        $balances = CreditWallet::pluck('balance', 'organization_id');

        // Per organization: credits used in the last 30 days, and when anything last moved.
        $perOrg = CreditLedgerEntry::join('credit_wallets', 'credit_wallets.id', '=', 'credit_ledger.wallet_id')
            ->groupBy('credit_wallets.organization_id')
            ->selectRaw("credit_wallets.organization_id AS org,
                SUM(CASE WHEN credit_ledger.type = 'usage' AND credit_ledger.created_at >= ? THEN -credit_ledger.amount ELSE 0 END) AS used30,
                MAX(credit_ledger.created_at) AS last_at", [$since])
            ->get()->keyBy('org');

        $totals = CreditLedgerEntry::selectRaw("
            SUM(CASE WHEN amount > 0 AND type IN ('purchase') THEN amount ELSE 0 END) AS purchased,
            SUM(CASE WHEN amount > 0 AND type IN ('promo', 'adjustment') THEN amount ELSE 0 END) AS granted,
            SUM(CASE WHEN type = 'usage' THEN -amount ELSE 0 END) AS used,
            SUM(CASE WHEN type IN ('refund', 'reversal') THEN amount ELSE 0 END) AS refunded
        ")->first();

        $pending = CreditTopupRequest::with(['organization', 'requester', 'discount'])->where('status', 'pending')->oldest()->get();
        $creditOrgIds = $allOrganizations->pluck('id');

        // Last 14 days of spending, oldest first, for the overview bars.
        $daily = CreditLedgerEntry::where('type', 'usage')->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(created_at) AS d, SUM(-amount) AS credits')->groupBy('d')->pluck('credits', 'd');
        $days = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->toDateString())
            ->mapWithKeys(fn ($d) => [$d => (int) ($daily[$d] ?? 0)]);

        $activity = CreditLedgerEntry::with(['wallet.organization', 'creator'])
            ->when($request->filled('org'), fn ($q) => $q->whereHas('wallet', fn ($w) => $w->where('organization_id', $request->integer('org'))))
            ->when(in_array($request->query('type'), ['purchase', 'usage', 'refund', 'adjustment', 'promo', 'reversal'], true), fn ($q) => $q->where('type', $request->query('type')))
            ->latest('id')->paginate(25, ['*'], 'page')->withQueryString();

        $rate = (float) ($this->credits->setting('money_per_credit') ?: 0);

        return view('backend.pages.credits.manage', [
            'tab' => $tab,
            'organizations' => $organizations,
            'allOrganizations' => $allOrganizations,
            'balances' => $balances,
            'perOrg' => $perOrg,
            'pending' => $pending,
            'processed' => CreditTopupRequest::with(['organization', 'requester'])->where('status', '!=', 'pending')->latest('processed_at')->limit(20)->get(),
            'features' => CreditFeature::orderBy('sort')->get(),
            'discounts' => Discount::withCount('redemptions')->latest('id')->get(),
            'recent' => CreditLedgerEntry::with(['wallet.organization', 'creator'])->latest('id')->limit(8)->get(),
            'activity' => $activity,
            'usageByFeature' => CreditLedgerEntry::where('type', 'usage')->groupBy('feature_key')
                ->selectRaw('feature_key, COUNT(*) AS times, SUM(-amount) AS credits')->orderByDesc('credits')->get(),
            'days' => $days,
            'totals' => $totals,
            'kpis' => [
                'held' => (int) CreditWallet::whereIn('organization_id', $creditOrgIds)->sum('balance'),
                'used30' => (int) $perOrg->sum('used30'),
                'pending' => $pending->count(),
                'pendingValue' => (float) $pending->sum('price'),
                'creditOrgs' => $creditOrgIds->count(),
                'orgs' => $allOrganizations->count(),
                'received' => (float) CreditTopupRequest::where('status', 'approved')->sum('price'),
            ],
            'rate' => $rate,
            'currency' => (string) ($this->credits->setting('currency') ?: ''),
            'mismatches' => $this->credits->reconcile(),
            'settings' => collect(['money_per_credit', 'currency', 'term_singular', 'term_plural', 'welcome_credits', 'topup_instructions'])
                ->mapWithKeys(fn ($k) => [$k => $this->credits->setting($k)]),
            'credits' => $this->credits,
            'breadcrumbs' => ['title' => $this->credits->term() . ' — ' . __('Manage')],
        ]);
    }

    public function grant(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'amount' => 'required|integer|not_in:0|min:-1000000|max:1000000',
            'type' => 'required|in:adjustment,promo',
            'reason' => 'required|string|max:255',
        ]);

        try {
            $this->credits->grant(Organization::findOrFail($data['organization_id']), (int) $data['amount'], $data['type'], $data['reason'], $request->user());
        } catch (InsufficientCreditsException $e) {
            return back()->with('error', 'That would take the balance below zero.');
        }

        return back()->with('success', 'Balance updated.');
    }

    public function approveTopup(Request $request, CreditTopupRequest $topup): RedirectResponse
    {
        try {
            $this->credits->approveTopup($topup, $request->user(), $request->input('admin_note'));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$topup->credits} added to {$topup->organization->name}.");
    }

    public function rejectTopup(Request $request, CreditTopupRequest $topup): RedirectResponse
    {
        try {
            $this->credits->rejectTopup($topup, $request->user(), $request->input('admin_note'));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Request rejected.');
    }

    public function updateFeature(Request $request, CreditFeature $feature): RedirectResponse
    {
        $data = $request->validate([
            'cost' => 'required|integer|min:0|max:1000000',
            'is_active' => 'boolean',
        ]);
        $feature->update(['cost' => $data['cost'], 'is_active' => $request->boolean('is_active')]);

        return back()->with('success', "{$feature->name}: price saved.");
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'money_per_credit' => 'required|numeric|min:0.01|max:1000000',
            'currency' => 'required|string|max:8',
            'term_singular' => 'required|string|max:30',
            'term_plural' => 'required|string|max:30',
            'welcome_credits' => 'required|integer|min:0|max:1000000',
            'topup_instructions' => 'nullable|string|max:1000',
        ]);

        // Only changed values get a new row, so the history shows real changes.
        foreach ($data as $key => $value) {
            if ((string) $this->credits->setting($key) !== (string) $value) {
                $this->credits->setSetting($key, (string) $value, $request->user());
            }
        }

        return back()->with('success', 'Settings saved.');
    }

    public function storeDiscount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:40|alpha_dash|unique:discounts,code',
            'type' => 'required|in:percent,fixed',
            'value' => 'required|integer|min:1|max:1000000',
            'applies_to' => ['required', 'string', Rule::in(array_merge(['topup', 'features', 'feature:' . \App\Services\Poster\TemplateLibrary::PREMIUM_FEATURE], CreditFeature::pluck('key')->map(fn ($k) => 'feature:' . $k)->all()))],
            'organization_ids' => 'nullable|array',
            'organization_ids.*' => 'integer|exists:organizations,id',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'max_uses' => 'nullable|integer|min:1',
            'per_org_limit' => 'nullable|integer|min:1',
        ]);
        if ($data['type'] === 'percent' && $data['value'] > 100) {
            return back()->withInput()->withErrors(['value' => 'A percentage cannot be more than 100.']);
        }
        $data['code'] = ! empty($data['code']) ? strtoupper($data['code']) : null;
        $data['organization_ids'] = ! empty($data['organization_ids']) ? array_map('intval', $data['organization_ids']) : null;

        Discount::create($data + ['is_active' => true]);

        return back()->with('success', 'Discount created.');
    }

    public function toggleDiscount(Discount $discount): RedirectResponse
    {
        $discount->update(['is_active' => ! $discount->is_active]);

        return back()->with('success', $discount->is_active ? 'Discount switched on.' : 'Discount switched off.');
    }
}
