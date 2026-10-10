<?php

declare(strict_types=1);

namespace App\Services\Credits;

use App\Models\Credits\CreditFeature;
use App\Models\Tournament;
use Closure;
use Illuminate\Support\Str;

/**
 * Charges for posters, one per poster file a metered request (or job) saves.
 *
 * Thirty-odd controller actions and jobs produce posters through the poster
 * services; they all end in PosterGeneratorService::saveImage(). Rather than
 * thread a charge through every one, a route middleware (credits.posters)
 * opens a metered scope for the request's tournament and saveImage() reports
 * each file here. Outside a scope — or inside suspended() — nothing is charged,
 * which is how true on-screen previews stay free.
 *
 * The charge comes AFTER the file is written: a render that fails costs nothing.
 */
class PosterMeter
{
    private ?Tournament $tournament = null;
    private int $suspended = 0;
    private string $scopeKey = '';
    private int $count = 0;

    public function __construct(private readonly CreditService $credits) {}

    public function begin(Tournament $tournament): void
    {
        $this->tournament = $tournament;
        $this->scopeKey = (string) Str::uuid();
        $this->count = 0;
    }

    public function end(): void
    {
        $this->tournament = null;
    }

    public function active(): bool
    {
        return $this->tournament !== null && $this->suspended === 0;
    }

    /**
     * Run metered against a tournament — for posters made outside a metered
     * request (automatic welcome cards, scheduled match posters). Inside an
     * open scope it just runs, so nothing is charged twice.
     */
    public function within(Tournament $tournament, Closure $fn): mixed
    {
        if ($this->tournament !== null) {
            return $fn();
        }

        $this->begin($tournament);
        try {
            return $fn();
        } finally {
            $this->end();
        }
    }

    /** Run without charging (previews). */
    public function suspended(Closure $fn): mixed
    {
        $this->suspended++;
        try {
            return $fn();
        } finally {
            $this->suspended--;
        }
    }

    /**
     * Before a batch: refuse up front when the balance cannot cover all of it,
     * so a bulk run never stops half way.
     */
    public function ensureCanAfford(int $posters): void
    {
        if (! $this->tournament || $posters < 1) {
            return;
        }
        $org = $this->tournament->organization;
        if (! $org) {
            return;
        }
        $quote = $this->credits->quote($org, CreditFeature::POSTER, $posters);
        $balance = $this->credits->balance($org);
        if ($quote['cost'] > $balance) {
            throw new InsufficientCreditsException($quote['cost'], $balance, $this->credits->term());
        }
    }

    /** Called by PosterGeneratorService::saveImage() for every poster written. */
    public function saved(string $path): void
    {
        if (! $this->active() || ! ($org = $this->tournament->organization)) {
            return;
        }

        $this->count++;
        $this->credits->charge($org, CreditFeature::POSTER, $this->tournament, "poster:{$this->scopeKey}:{$this->count}");
    }
}
