<?php

declare(strict_types=1);

namespace App\Services\Poster;

use App\Models\Tournament;
use App\Models\TournamentTemplate;
use Illuminate\Database\Eloquent\Builder;

/**
 * The global template library: Super Admin's own poster templates, kept in
 * tournament_templates with no tournament (tournament_id NULL).
 *
 * New tournaments and seasons start with COPIES of the active library templates
 * (files included — see TemplateCopier), so the library can be edited freely
 * without touching a tournament that already exists, and every tournament can
 * edit its own copies without touching the library.
 *
 * `is_default` in the library means "the default for this type in a new
 * tournament", and is carried onto the copy.
 */
class TemplateLibrary
{
    public function __construct(private readonly TemplateCopier $copier) {}

    public function query(): Builder
    {
        return TournamentTemplate::query()->whereNull('tournament_id');
    }

    /** Active library templates — the ones a new tournament receives. */
    public function count(): int
    {
        return $this->query()->where('is_active', true)->count();
    }

    /**
     * Copy every active library template into a tournament.
     *
     * A library default becomes the tournament's default for that type only when
     * the tournament has none yet, so copying into an existing tournament never
     * changes which template its automatic emails and summaries already use.
     *
     * @return array<int, int> library template id => new template id
     */
    public function copyInto(Tournament $tournament): array
    {
        $hasDefault = $tournament->templates()->where('is_default', true)->pluck('type')->flip();
        $map = [];

        foreach ($this->query()->where('is_active', true)->orderBy('type')->orderByDesc('is_default')->orderBy('id')->get() as $template) {
            $makeDefault = $template->is_default && ! isset($hasDefault[$template->type]);
            $copy = $this->copier->copy($template, $tournament->id, null, keepDefault: $makeDefault);
            if ($makeDefault) {
                $hasDefault[$template->type] = true;
            }
            $map[$template->id] = $copy->id;
        }

        return $map;
    }

    /** A tournament's template, copied into the library (never as the default). */
    public function saveFromTournament(TournamentTemplate $template): TournamentTemplate
    {
        return $this->copier->copy($template, null, $this->uniqueName($template->name));
    }

    public function uniqueName(string $name): string
    {
        $base = preg_replace('/\s*\(\d+\)\s*$/', '', $name);
        $candidate = $base;
        $i = 2;
        while ($this->query()->where('name', $candidate)->exists()) {
            $candidate = $base . ' (' . $i++ . ')';
        }

        return $candidate;
    }
}
