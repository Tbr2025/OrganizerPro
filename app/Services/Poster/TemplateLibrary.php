<?php

declare(strict_types=1);

namespace App\Services\Poster;

use App\Models\Tournament;
use App\Models\TournamentTemplate;
use App\Models\User;
use App\Services\Credits\PosterMeter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Template presets: poster templates kept in tournament_templates with no
 * tournament (tournament_id NULL), designed in the same drag editor.
 *
 * Two scopes. organization_id NULL is a GLOBAL preset — Super Admin's, offered
 * to every organization. organization_id X belongs to that organization: its
 * admins build and edit it, and only its tournaments are offered it.
 *
 * A tournament or season starts with COPIES of the presets ticked when it is
 * created (files included — see TemplateCopier), so a preset can be edited
 * freely without touching a tournament that already has a copy, and a
 * tournament can edit its copies without touching the preset.
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

    /** What an organization's tournaments are offered: the global presets plus its own. */
    public function offeredTo(?int $organizationId): Builder
    {
        return $this->query()->where(fn ($q) => $q->whereNull('organization_id')
            ->when($organizationId, fn ($q) => $q->orWhere('organization_id', $organizationId)));
    }

    /**
     * The presets a user may pick for a tournament (or, with no tournament, for a
     * new one in any organization they can create in): org first, then global.
     */
    public function pickerFor(User $user, ?Tournament $tournament = null): Collection
    {
        $query = $tournament
            ? $this->offeredTo($tournament->organization_id)
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
     * Copy presets into a tournament: the ids ticked, or (null) every preset
     * ticked by default. Anything not offered to the tournament's organization is
     * ignored, so a posted id can never pull another organization's design in.
     *
     * Each type the tournament has no default for gets one from the copies: the
     * organization's default first, then the global default, then the first copy
     * — so the automatic emails and summaries always have a template, and copying
     * into an existing tournament never changes the default it already uses.
     *
     * @param  array<int>|null  $ids
     * @return array<int, int> preset id => new template id
     */
    public function copyInto(Tournament $tournament, ?array $ids = null): array
    {
        $presets = $this->offeredTo($tournament->organization_id)
            ->when($ids === null, fn ($q) => $q->where('is_active', true))
            ->when($ids !== null, fn ($q) => $q->whereIn('id', array_map('intval', $ids)))
            ->orderBy('type')
            ->orderByRaw('organization_id IS NULL')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $hasDefault = $tournament->templates()->where('is_default', true)->pluck('type')->flip();
        $map = [];

        foreach ($presets as $preset) {
            $makeDefault = ! isset($hasDefault[$preset->type]);
            $copy = $this->copier->copy($preset, $tournament->id, null, keepDefault: false);
            $copy->forceFill(['organization_id' => null, 'is_default' => $makeDefault, 'is_active' => true])->save();
            $hasDefault[$preset->type] = true;
            $map[$preset->id] = $copy->id;
        }

        return $map;
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
        $copy->forceFill(['organization_id' => $organizationId, 'is_default' => false, 'is_active' => true])->save();

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
     * A picture of the preset drawn with sample data, cached on the public disk per
     * version (its updated_at), so the picker shows the design and not just its
     * background. Never charged.
     *
     * @return string|null path on the public disk
     */
    public function thumbnail(TournamentTemplate $preset): ?string
    {
        $disk = Storage::disk('public');
        $path = "preset-thumbs/library-{$preset->id}-{$preset->updated_at?->timestamp}.jpg";
        if ($disk->exists($path)) {
            return $path;
        }

        $renderer = app(TemplateRenderService::class);
        try {
            $png = app(PosterMeter::class)->suspended(
                fn () => $renderer->renderTemplate($preset, $renderer->getSampleData($preset->type, []), true)
            );
            $img = imagecreatefrompng($disk->path($png));
            $thumb = imagescale($img, 360, (int) round(360 * imagesy($img) / imagesx($img)));
            ob_start();
            imagejpeg($thumb, null, 82);
            // An older version's picture is never shown again.
            foreach ($disk->files('preset-thumbs') as $old) {
                if (str_starts_with(basename($old), "library-{$preset->id}-")) {
                    $disk->delete($old);
                }
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
