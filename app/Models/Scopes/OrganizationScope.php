<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Auto-scopes a model's queries to the current user's organization so a Team
 * Organizer can never read another organization's rows.
 *
 * No-op for:
 *  - guests / unauthenticated contexts (public registration, console, seeders, tests)
 *  - Superadmin (sees everything)
 *
 * For an authenticated non-Superadmin WITH an organization_id, rows are filtered
 * to that organization. For a non-Superadmin WITHOUT an organization_id, no rows
 * are returned (mirrors the existing `whereRaw('1 = 0')` convention in
 * TournamentController) — they have no organization, so no organization data.
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! Auth::check()) {
            return;
        }

        $user = Auth::user();

        // Superadmin bypasses isolation entirely.
        if (method_exists($user, 'hasRole') && $user->hasRole('Superadmin')) {
            return;
        }

        $column = $model->getTable() . '.organization_id';

        if (! empty($user->organization_id)) {
            $builder->where($column, $user->organization_id);
            return;
        }

        // Authenticated, not Superadmin, but has no organization → no org data.
        $builder->whereRaw('1 = 0');
    }
}
