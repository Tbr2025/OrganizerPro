<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Applies the global OrganizationScope and auto-fills organization_id on create
 * from the acting user (for non-Superadmins). Models using this trait must have
 * an `organization_id` column.
 *
 * Use Model::withoutOrganizationScope() for the rare legitimate cross-org query.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope());

        static::creating(function ($model) {
            if (! empty($model->organization_id)) {
                return;
            }

            if (! Auth::check()) {
                return;
            }

            $user = Auth::user();

            // Superadmin may create rows for any organization (chosen on the form),
            // so don't force their own org.
            if (method_exists($user, 'hasRole') && $user->hasRole('Superadmin')) {
                return;
            }

            if (! empty($user->organization_id)) {
                $model->organization_id = $user->organization_id;
            }
        });
    }

    /** Query without the organization global scope (cross-org / system use). */
    public static function withoutOrganizationScope(): Builder
    {
        return static::withoutGlobalScope(OrganizationScope::class);
    }
}
