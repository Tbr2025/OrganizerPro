<?php

declare(strict_types=1);

namespace App\Services\Poster;

use App\Models\Credits\CreditLedgerEntry;
use App\Models\Tournament;
use App\Models\TournamentTemplate;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Credits\PosterMeter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Template presets: poster templates kept in tournament_templates with no
 * tournament (tournament_id NULL), designed in the same drag editor.
 *
 * Only Super Admin builds them. Two scopes: organization_id NULL is a GLOBAL
 * preset, offered to every organization or only to those in `offered_to`;
 * organization_id X is made for that organization alone.
 *
 * Free or Premium: a premium preset costs its `price` in credits, once per
 * tournament. A copy of a FREE preset is `bg_only` — its organizers can change
 * its background (a paid action, CreditFeature `template_background`) and
 * nothing else. Auction poster types are offered to auction tournaments only.
 *
 * A tournament or season starts with COPIES of the presets ticked when it is
 * created (files included — see TemplateCopier). Each copy remembers its preset
 * and the preset's design_version; when the preset is changed, syncCopies()
 * brings the copies nobody has edited up to date (a free copy keeps a background
 * it paid for), and edited ones show "update available".
 *
 * `is_active` means "ticked by default" in the picker. `is_default` means "the
 * default for this type" within its own scope, and is carried onto the copy.
 */
class TemplateLibrary
{
    public function __construct(private readonly TemplateCopier $copier) {}

    public function query(): Builder
    {
        return TournamentTemplate::query()->whereNull('tournament_id');
    }

    /** One scope only: the global presets (null) or one organization's. */
    public function scope(?int $organizationId): Builder
    {
        return $this->query()->where(fn ($q) => $organizationId === null
            ? $q->whereNull('organization_id')
            : $q->where('organization_id', $organizationId));
    }

    /**
     * What an organization's tournaments are offered: the global presets offered to
     * everyone or to it, plus its own. With a tournament type, auction poster types
     * are left out unless it is an auction.
     */
    public function offeredTo(?int $organizationId, ?string $tournamentType = null): Builder
    {
        return $this->query()
            ->where(fn ($q) => $q
                ->where(fn ($g) => $g->whereNull('organization_id')->where(fn ($o) => $o->whereNull('offered_to')
                    ->when($organizationId, fn ($o) => $o->orWhereJsonContains('offered_to', $organizationId))))
                ->when($organizationId, fn ($q) => $q->orWhere('organization_id', $organizationId)))
            ->when($tournamentType !== null && $tournamentType !== 'auction',
                fn ($q) => $q->whereNotIn('type', TournamentTemplate::AUCTION_TYPES));
    }

    /**
     * The presets a user may pick for a tournament (or, with no tournament, for a
     * new one in any organization they can create in): org first, then global.
     */
    public function pickerFor(User $user, ?Tournament $tournament = null): Collection
    {
        $query = $tournament
            ? $this->offeredTo($tournament->organization_id, $tournament->type ?? 'open')
            : ($user->hasRole('Superadmin') ? $this->query() : $this->offeredTo($user->organization_id));

        return $query->with('organization:id,name')
            ->orderByRaw('organization_id IS NULL')->orderBy('type')->orderByDesc('is_default')->orderBy('name')
            ->get();
    }

    /** May this user change this preset? Super Admin: any. Others: their organization's own. */
    public function canEdit(User $user, TournamentTemplate $preset): bool
    {
        if ($preset->tournament_id !== null) {
            return false;
        }
        if ($user->hasRole('Superadmin')) {
            return true;
        }

        return $preset->organization_id !== null && (int) $preset->organization_id === (int) $user->organization_id;
    }

    /**
     * The presets these ids would pull into a tournament: only those offered to its
     * organization and its type (a posted id never pulls another organization's
     * design in), or (null) every preset ticked by default.
     */
    public function selection(Tournament $tournament, ?array $ids = null): Collection
    {
        return $this->offeredTo($tournament->organization_id, $tournament->type ?? 'open')
            ->when($ids === null, fn ($q) => $q->where('is_active', true))
            ->when($ids !== null, fn ($q) => $q->whereIn('id', array_map('intval', $ids)))
            ->orderBy('type')
            ->orderByRaw('organization_id IS NULL')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }

    /**
     * Credits the premium presets among these ids would cost a tournament of this
     * organization and type: each premium preset once per tournament, so ones the
     * tournament already paid for cost nothing. A Superadmin pays nothing.
     */
    public function premiumCost(?int $organizationId, ?string $tournamentType, ?array $ids, ?Tournament $tournament = null, ?User $actor = null): int
    {
        $actor ??= auth()->user();
        if (! $ids || ($actor && $actor->hasRole('Superadmin'))) {
            return 0;
        }

        return (int) $this->offeredTo($organizationId, $tournamentType)
            ->whereIn('id', array_map('intval', $ids))
            ->where('tier', TournamentTemplate::TIER_PREMIUM)
            ->get(['id', 'price'])
            ->reject(fn ($p) => $tournament && CreditLedgerEntry::where('idempotency_key', $this->premiumKey($p->id, $tournament))->exists())
            ->sum('price');
    }

    /**
     * Copy presets into a tournament: the ids ticked, or (null) every preset
     * ticked by default (see selection()).
     *
     * Premium presets are paid for first — all of them or none: not enough
     * credits throws InsufficientCreditsException before anything is copied.
     *
     * Each type the tournament has no default for gets one from the copies: the
     * organization's default first, then the global default, then the first copy
     * — so the automatic emails and summaries always have a template, and copying
     * into an existing tournament never changes the default it already uses.
     *
     * @param  array<int>|null  $ids
     * @return array<int, int> preset id => new template id
     *
     * @throws InsufficientCreditsException
     */
    public function copyInto(Tournament $tournament, ?array $ids = null, ?User $actor = null): array
    {
        $actor ??= auth()->user();
        $presets = $this->selection($tournament, $ids);
        $this->chargePremium($tournament, $presets, $actor);

        $hasDefault = $tournament->templates()->where('is_default', true)->pluck('type')->flip();
        $map = [];

        foreach ($presets as $preset) {
            $makeDefault = ! isset($hasDefault[$preset->type]);
            $copy = $this->copier->copy($preset, $tournament->id, null, keepDefault: false);
            $copy->forceFill([
                'organization_id' => null,
                'is_default' => $makeDefault,
                'is_active' => true,
                'tier' => TournamentTemplate::TIER_FREE,
                'price' => 0,
                'offered_to' => null,
                'design_version' => 1,
                'source_preset_id' => $preset->id,
                'source_version' => $preset->design_version,
                // A free design keeps its look: only its background can change.
                'bg_only' => ! $preset->isPremium(),
                'customized' => false,
                'bg_changed' => false,
            ])->save();
            $hasDefault[$preset->type] = true;
            $map[$preset->id] = $copy->id;
        }

        return $map;
    }

    /** Pay for the premium presets in a selection, once per tournament each; all or nothing. */
    private function chargePremium(Tournament $tournament, Collection $presets, ?User $actor): void
    {
        $premium = $presets->filter(fn (TournamentTemplate $p) => $p->isPremium());
        $org = $tournament->organization;
        if ($premium->isEmpty() || ! $org) {
            return;
        }

        $credits = app(CreditService::class);
        $due = $this->premiumCost($tournament->organization_id, $tournament->type ?? 'open', $premium->pluck('id')->all(), $tournament, $actor);
        if ($due > 0 && $credits->balance($org) < $due) {
            throw new InsufficientCreditsException($due, $credits->balance($org), $credits->term());
        }

        DB::transaction(function () use ($premium, $tournament, $org, $credits, $actor) {
            foreach ($premium as $preset) {
                $credits->chargeAmount($org, (int) $preset->price, 'premium_preset', $preset, $this->premiumKey($preset->id, $tournament), $actor);
            }
        });
    }

    private function premiumKey(int $presetId, Tournament $tournament): string
    {
        return "premium-preset:{$presetId}:tournament:{$tournament->id}";
    }

    /**
     * Bring tournaments' copies of a preset up to its current design. A copy nobody
     * edited takes the whole new design; a free (background-only) copy whose
     * background was changed keeps that background and takes the rest. A copy
     * someone edited is left alone — it shows "update available" instead.
     *
     * @return array{updated:int, skipped:int}
     */
    public function syncCopies(TournamentTemplate $preset): array
    {
        $updated = 0;
        $skipped = 0;

        $preset->copies()->where(fn ($q) => $q->whereNull('source_version')->orWhere('source_version', '<', $preset->design_version))
            ->each(function (TournamentTemplate $copy) use ($preset, &$updated, &$skipped) {
                if ($copy->customized || ($copy->bg_changed && ! $copy->bg_only)) {
                    $skipped++;

                    return;
                }
                $this->applyPreset($copy, $preset, keepBackground: $copy->bg_changed);
                $updated++;
            });

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /** Replace a copy's design with its preset's current one ("Use the preset's new version"). */
    public function applyPreset(TournamentTemplate $copy, TournamentTemplate $preset, bool $keepBackground = false): void
    {
        $copy->forceFill($this->copier->design($preset, withBackground: ! $keepBackground) + [
            'source_version' => $preset->design_version,
            'customized' => false,
            'bg_changed' => $keepBackground,
        ])->save();
    }

    /** How many tournaments use a preset, and how many of those copies are behind it. */
    public function usage(TournamentTemplate $preset): array
    {
        $copies = $preset->copies();

        return [
            'copies' => (clone $copies)->count(),
            'behind' => (clone $copies)->where(fn ($q) => $q->whereNull('source_version')->orWhere('source_version', '<', $preset->design_version))->count(),
        ];
    }

    /** A tournament's template, copied in as a preset of the given scope (never as its default). */
    public function saveFromTournament(TournamentTemplate $template, ?int $organizationId): TournamentTemplate
    {
        return $this->copyAsPreset($template, $organizationId, $template->name);
    }

    /** A copy of a template or preset as a preset of the given scope. */
    public function copyAsPreset(TournamentTemplate $source, ?int $organizationId, string $name): TournamentTemplate
    {
        $copy = $this->copier->copy($source, null, $this->uniqueName($name, $organizationId));
        $copy->forceFill([
            'organization_id' => $organizationId, 'is_default' => false, 'is_active' => true,
            'tier' => $source->tournament_id === null ? $source->tier : TournamentTemplate::TIER_FREE,
            'price' => $source->tournament_id === null ? $source->price : 0,
            'offered_to' => null, 'design_version' => 1,
            'source_preset_id' => null, 'source_version' => null,
            'bg_only' => false, 'customized' => false, 'bg_changed' => false,
        ])->save();

        return $copy;
    }

    /** Make a preset the default of its type, within its own scope only. */
    public function makeDefault(TournamentTemplate $preset): void
    {
        $this->scope($preset->organization_id)->where('type', $preset->type)->where('id', '!=', $preset->id)
            ->update(['is_default' => false]);
        $preset->update(['is_default' => true]);
    }

    /** Whether a scope has no default yet for a type (the first preset of a type becomes it). */
    public function needsDefault(?int $organizationId, string $type): bool
    {
        return ! $this->scope($organizationId)->where('type', $type)->where('is_default', true)->exists();
    }

    public function uniqueName(string $name, ?int $organizationId): string
    {
        $base = preg_replace('/\s*\(\d+\)\s*$/', '', $name);
        $candidate = $base;
        $i = 2;
        while ($this->scope($organizationId)->where('name', $candidate)->exists()) {
            $candidate = $base . ' (' . $i++ . ')';
        }

        return $candidate;
    }

    /**
     * A picture of the template drawn with sample data, cached on the public disk
     * per version — a preset's design_version, a tournament template's updated_at —
     * so the picker shows the design and not just its background. Settings that do
     * not change the design (default, ticked, tier) never redraw it. Never charged.
     *
     * @return string|null path on the public disk
     */
    public function thumbnail(TournamentTemplate $template): ?string
    {
        $disk = Storage::disk('public');
        $version = $template->tournament_id === null ? 'v' . (int) $template->design_version : (string) $template->updated_at?->timestamp;
        $path = "preset-thumbs/library-{$template->id}-{$version}.jpg";
        if ($disk->exists($path)) {
            return $path;
        }

        $renderer = app(TemplateRenderService::class);
        try {
            $png = app(PosterMeter::class)->suspended(
                fn () => $renderer->renderTemplate($template, $renderer->getSampleData($template->type, []), true)
            );
            $img = imagecreatefrompng($disk->path($png));
            $thumb = imagescale($img, 360, (int) round(360 * imagesy($img) / imagesx($img)));
            ob_start();
            imagejpeg($thumb, null, 82);
            // An older version's picture is never shown again.
            foreach (glob($disk->path("preset-thumbs/library-{$template->id}-*.jpg")) ?: [] as $old) {
                @unlink($old);
            }
            $disk->put($path, (string) ob_get_clean());
            imagedestroy($img);
            imagedestroy($thumb);
            $disk->delete($png);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        return $path;
    }
}
