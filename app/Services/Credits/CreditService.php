<?php

declare(strict_types=1);

namespace App\Services\Credits;

use App\Models\Credits\CreditFeature;
use App\Models\Credits\CreditFeatureUnlock;
use App\Models\Credits\CreditLedgerEntry;
use App\Models\Credits\CreditSetting;
use App\Models\Credits\CreditTopupRequest;
use App\Models\Credits\CreditWallet;
use App\Models\Credits\Discount;
use App\Models\Credits\DiscountRedemption;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only code that moves a credit balance.
 *
 * Every change is a ledger row written in the SAME transaction as the balance
 * update, under a row lock on the wallet, so concurrent requests cannot both
 * spend the last credit and the balance always equals the sum of its ledger.
 * An idempotency key makes a retried charge a no-op.
 *
 * Who pays:
 *  - organizations on billing_mode 'credits' pay the feature's price;
 *  - organizations on 'package' (everyone before credits existed) pay nothing
 *    here — their old limits still apply;
 *  - a Superadmin acting on a client's tournament never spends the client's
 *    credits (support work), though unlocks are still recorded.
 * A feature at cost 0 or switched off is free.
 */
class CreditService
{
    // ---- settings & terminology ---------------------------------------------

    public function setting(string $key, ?string $default = null): ?string
    {
        return Cache::remember("credit-setting:{$key}", 60, fn () => CreditSetting::where('key', $key)
            ->where('effective_from', '<=', now())->orderByDesc('effective_from')->orderByDesc('id')->value('value')) ?? $default;
    }

    public function setSetting(string $key, ?string $value, ?User $by = null): void
    {
        CreditSetting::create(['key' => $key, 'value' => $value, 'effective_from' => now(), 'changed_by' => $by?->id]);
        Cache::forget("credit-setting:{$key}");
    }

    /** "1 Credit", "5 Credits" — the word is Super Admin's to rename. */
    public function term(int $n = 2): string
    {
        return abs($n) === 1 ? (string) $this->setting('term_singular', 'Credit') : (string) $this->setting('term_plural', 'Credits');
    }

    public function format(int $n): string
    {
        return number_format($n) . ' ' . $this->term($n);
    }

    // ---- reading -------------------------------------------------------------

    public function wallet(Organization $org): CreditWallet
    {
        return CreditWallet::firstOrCreate(['organization_id' => $org->id], ['balance' => 0]);
    }

    public function balance(Organization $org): int
    {
        return (int) ($org->wallet?->balance ?? 0);
    }

    public function feature(string $key): ?CreditFeature
    {
        return CreditFeature::where('key', $key)->first();
    }

    /** Does this organization pay for features at all (and is this actor paying)? */
    public function charges(Organization $org, ?User $actor = null): bool
    {
        $actor ??= Auth::user();

        return $org->usesCredits() && ! ($actor && $actor->hasRole('Superadmin'));
    }

    /**
     * What this would cost now, after the best automatic discount.
     *
     * @return array{cost:int, base:int, discount:?Discount, saved:int, free:bool}
     */
    public function quote(Organization $org, string $featureKey, int $qty = 1, ?User $actor = null): array
    {
        $feature = $this->feature($featureKey);
        $base = ($feature && $feature->is_active) ? $feature->cost * max(1, $qty) : 0;

        if ($base === 0 || ! $this->charges($org, $actor)) {
            return ['cost' => 0, 'base' => $base, 'discount' => null, 'saved' => 0, 'free' => true];
        }

        $best = null;
        $saved = 0;
        foreach (Discount::whereNull('code')->where('is_active', true)->get() as $d) {
            if ($d->appliesTo($org->id, $featureKey) && $d->reduction($base) > $saved) {
                $best = $d;
                $saved = $d->reduction($base);
            }
        }

        return ['cost' => $base - $saved, 'base' => $base, 'discount' => $best, 'saved' => $saved, 'free' => false];
    }

    public function hasUnlock(Organization $org, string $featureKey, Model $scope): bool
    {
        return CreditFeatureUnlock::where('organization_id', $org->id)->where('feature_key', $featureKey)
            ->where('scope_type', $scope->getMorphClass())->where('scope_id', $scope->getKey())->exists();
    }

    /** Can this action go ahead without a charge (free, legacy, already unlocked)? */
    public function isUnlocked(Organization $org, string $featureKey, Model $scope, ?User $actor = null): bool
    {
        return $this->hasUnlock($org, $featureKey, $scope) || $this->quote($org, $featureKey, 1, $actor)['cost'] === 0;
    }

    // ---- spending --------------------------------------------------------------

    /**
     * Charge for a feature. Returns the ledger row, or null when it was free.
     *
     * @throws InsufficientCreditsException with nothing changed
     */
    public function charge(Organization $org, string $featureKey, ?Model $reference = null, ?string $idempotencyKey = null, int $qty = 1, ?User $actor = null): ?CreditLedgerEntry
    {
        $actor ??= Auth::user();
        $quote = $this->quote($org, $featureKey, $qty, $actor);
        if ($quote['cost'] === 0) {
            return null;
        }

        return DB::transaction(function () use ($org, $featureKey, $reference, $idempotencyKey, $qty, $actor, $quote) {
            if ($idempotencyKey && ($done = CreditLedgerEntry::where('idempotency_key', $idempotencyKey)->first())) {
                return $done;
            }

            $wallet = CreditWallet::whereKey($this->wallet($org)->id)->lockForUpdate()->first();
            if ($wallet->balance < $quote['cost']) {
                throw new InsufficientCreditsException($quote['cost'], $wallet->balance, $this->term());
            }

            $entry = $this->write($wallet, 'usage', -$quote['cost'], [
                'feature_key' => $featureKey,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'idempotency_key' => $idempotencyKey,
                'discount_id' => $quote['discount']?->id,
                'description' => ($this->feature($featureKey)?->name ?? $featureKey) . ($qty > 1 ? " × {$qty}" : ''),
                'meta' => ['qty' => $qty, 'base' => $quote['base'], 'saved' => $quote['saved']],
                'created_by' => $actor?->id,
            ]);

            if ($quote['discount']) {
                DiscountRedemption::create(['discount_id' => $quote['discount']->id, 'organization_id' => $org->id, 'ledger_id' => $entry->id, 'saved' => $quote['saved']]);
            }

            return $entry;
        });
    }

    /**
     * Charge a price set somewhere other than the feature catalogue (a premium design
     * carries its own). Same rules as charge(): only credits organizations pay, never a
     * Superadmin, one row per idempotency key, nothing changes when short.
     */
    public function chargeAmount(Organization $org, int $amount, string $featureKey, ?Model $reference = null, ?string $idempotencyKey = null, ?User $actor = null): ?CreditLedgerEntry
    {
        $actor ??= Auth::user();
        if ($amount <= 0 || ! $this->charges($org, $actor)) {
            return null;
        }

        return DB::transaction(function () use ($org, $amount, $featureKey, $reference, $idempotencyKey, $actor) {
            if ($idempotencyKey && ($done = CreditLedgerEntry::where('idempotency_key', $idempotencyKey)->first())) {
                return $done;
            }

            $wallet = CreditWallet::whereKey($this->wallet($org)->id)->lockForUpdate()->first();
            if ($wallet->balance < $amount) {
                throw new InsufficientCreditsException($amount, $wallet->balance, $this->term());
            }

            return $this->write($wallet, 'usage', -$amount, [
                'feature_key' => substr($featureKey, 0, 40),
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'idempotency_key' => $idempotencyKey,
                'description' => $featureKey,
                'created_by' => $actor?->id,
            ]);
        });
    }

    /**
     * Pay once for a feature on a scope (e.g. auction access on a tournament).
     * Already unlocked → nothing happens. Free → recorded without a charge.
     */
    public function unlock(Organization $org, string $featureKey, Model $scope, ?User $actor = null): CreditFeatureUnlock
    {
        $actor ??= Auth::user();

        return DB::transaction(function () use ($org, $featureKey, $scope, $actor) {
            $existing = CreditFeatureUnlock::where('organization_id', $org->id)->where('feature_key', $featureKey)
                ->where('scope_type', $scope->getMorphClass())->where('scope_id', $scope->getKey())->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $entry = $this->charge($org, $featureKey, $scope, "unlock:{$org->id}:{$featureKey}:{$scope->getMorphClass()}:{$scope->getKey()}", 1, $actor);

            return CreditFeatureUnlock::create([
                'organization_id' => $org->id,
                'feature_key' => $featureKey,
                'scope_type' => $scope->getMorphClass(),
                'scope_id' => $scope->getKey(),
                'ledger_id' => $entry?->id,
                'unlocked_by' => $actor?->id,
            ]);
        });
    }

    /** Give back a charge in full (e.g. the action failed after paying). */
    public function refund(CreditLedgerEntry $entry, string $reason, ?User $by = null): CreditLedgerEntry
    {
        if ($entry->amount >= 0) {
            throw new InvalidArgumentException('Only a charge can be refunded.');
        }

        return DB::transaction(function () use ($entry, $reason, $by) {
            if ($done = CreditLedgerEntry::where('idempotency_key', "refund:{$entry->id}")->first()) {
                return $done;
            }
            $wallet = CreditWallet::whereKey($entry->wallet_id)->lockForUpdate()->first();

            return $this->write($wallet, 'refund', -$entry->amount, [
                'feature_key' => $entry->feature_key,
                'reference_type' => $entry->reference_type,
                'reference_id' => $entry->reference_id,
                'idempotency_key' => "refund:{$entry->id}",
                'description' => 'Refund: ' . $reason,
                'meta' => ['refund_of' => $entry->id],
                'created_by' => $by?->id,
            ]);
        });
    }

    // ---- adding ----------------------------------------------------------------

    /**
     * Super Admin: add (or, with a negative amount, remove) credits with a reason.
     * type: adjustment | promo
     */
    public function grant(Organization $org, int $amount, string $type, string $reason, ?User $by = null): CreditLedgerEntry
    {
        if ($amount === 0 || ! in_array($type, ['adjustment', 'promo'], true)) {
            throw new InvalidArgumentException('A grant needs a non-zero amount and a type of adjustment or promo.');
        }

        return DB::transaction(function () use ($org, $amount, $type, $reason, $by) {
            $wallet = CreditWallet::whereKey($this->wallet($org)->id)->lockForUpdate()->first();
            if ($wallet->balance + $amount < 0) {
                throw new InsufficientCreditsException(-$amount, $wallet->balance, $this->term());
            }

            return $this->write($wallet, $type, $amount, ['description' => $reason, 'created_by' => $by?->id]);
        });
    }

    /** The price of a top-up in money, after a top-up discount code if one applies. */
    public function topupPrice(Organization $org, int $credits, ?string $code = null): array
    {
        $rate = (float) $this->setting('money_per_credit', '10');
        $price = round($credits * $rate, 2);
        $discount = null;
        $saved = 0.0;

        if ($code) {
            $discount = Discount::where('code', $code)->first();
            if (! $discount || ! $discount->appliesTo($org->id, 'topup')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['discount_code' => 'That code is not valid for a top-up.']);
            }
            $saved = (float) $discount->reduction($price);
        }

        return ['credits' => $credits, 'rate' => $rate, 'currency' => (string) $this->setting('currency', 'INR'), 'price' => round($price - $saved, 2), 'saved' => $saved, 'discount' => $discount];
    }

    public function requestTopup(Organization $org, int $credits, ?string $code, ?string $paymentReference, ?string $note, User $by): CreditTopupRequest
    {
        $p = $this->topupPrice($org, $credits, $code);

        return CreditTopupRequest::create([
            'organization_id' => $org->id, 'credits' => $credits, 'price' => $p['price'], 'currency' => $p['currency'],
            'rate' => $p['rate'], 'discount_id' => $p['discount']?->id, 'payment_reference' => $paymentReference,
            'note' => $note, 'status' => 'pending', 'requested_by' => $by->id,
        ]);
    }

    /** Super Admin confirms the payment arrived: the credits land. */
    public function approveTopup(CreditTopupRequest $request, User $by, ?string $adminNote = null): CreditLedgerEntry
    {
        return DB::transaction(function () use ($request, $by, $adminNote) {
            $request = CreditTopupRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($request->status !== 'pending') {
                throw new InvalidArgumentException('This request was already ' . $request->status . '.');
            }

            $wallet = CreditWallet::whereKey($this->wallet($request->organization)->id)->lockForUpdate()->first();
            $entry = $this->write($wallet, 'purchase', $request->credits, [
                'idempotency_key' => "topup:{$request->id}",
                'reference_type' => $request->getMorphClass(),
                'reference_id' => $request->id,
                'discount_id' => $request->discount_id,
                'description' => "Top-up: {$request->credits} for {$request->currency} {$request->price}",
                'meta' => ['price' => (string) $request->price, 'currency' => $request->currency, 'rate' => (string) $request->rate, 'payment_ref' => $request->payment_reference],
                'created_by' => $by->id,
            ]);

            if ($request->discount_id) {
                $full = round($request->credits * (float) $request->rate, 2);
                DiscountRedemption::create(['discount_id' => $request->discount_id, 'organization_id' => $request->organization_id, 'ledger_id' => $entry->id, 'topup_request_id' => $request->id, 'saved' => (int) round($full - (float) $request->price)]);
            }

            $request->update(['status' => 'approved', 'processed_by' => $by->id, 'processed_at' => now(), 'ledger_id' => $entry->id, 'admin_note' => $adminNote]);

            return $entry;
        });
    }

    public function rejectTopup(CreditTopupRequest $request, User $by, ?string $adminNote = null): void
    {
        if ($request->status !== 'pending') {
            throw new InvalidArgumentException('This request was already ' . $request->status . '.');
        }
        $request->update(['status' => 'rejected', 'processed_by' => $by->id, 'processed_at' => now(), 'admin_note' => $adminNote]);
    }

    /** New organization on credits: the welcome credits Super Admin has set. */
    public function welcome(Organization $org): void
    {
        $amount = (int) $this->setting('welcome_credits', '0');
        $this->wallet($org);
        if ($amount > 0 && ! CreditLedgerEntry::where('idempotency_key', "welcome:{$org->id}")->exists()) {
            DB::transaction(function () use ($org, $amount) {
                $wallet = CreditWallet::whereKey($this->wallet($org)->id)->lockForUpdate()->first();
                $this->write($wallet, 'promo', $amount, ['idempotency_key' => "welcome:{$org->id}", 'description' => 'Welcome credits']);
            });
        }
    }

    /**
     * Wallets whose balance does not equal the sum of their ledger.
     * Should always be empty; anything here is investigated, never auto-fixed.
     *
     * @return array<int, array{wallet_id:int, organization:string, balance:int, ledger_sum:int}>
     */
    public function reconcile(): array
    {
        return DB::table('credit_wallets as w')
            ->leftJoin('credit_ledger as l', 'l.wallet_id', '=', 'w.id')
            ->join('organizations as o', 'o.id', '=', 'w.organization_id')
            ->groupBy('w.id', 'w.balance', 'o.name')
            ->selectRaw('w.id as wallet_id, o.name as organization, w.balance, COALESCE(SUM(l.amount), 0) as ledger_sum')
            ->havingRaw('w.balance <> COALESCE(SUM(l.amount), 0)')
            ->get()->map(fn ($r) => (array) $r)->all();
    }

    private function write(CreditWallet $wallet, string $type, int $amount, array $extra): CreditLedgerEntry
    {
        $wallet->balance += $amount;
        $wallet->save();

        return CreditLedgerEntry::create(array_merge($extra, [
            'wallet_id' => $wallet->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $wallet->balance,
        ]));
    }
}
