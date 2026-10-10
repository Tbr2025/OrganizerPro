<?php

namespace App\Policies;

use App\Models\Player;
use App\Models\User;

/**
 * Model-level checks for Player.
 *
 * Decided by PERMISSION, never by role name. The previous version tested
 * lowercase names ('superadmin', 'team_manager', …) that are never seeded —
 * Spatie compares role names case-sensitively, so every branch was dead.
 * Roles get their permissions in RolesService::createPredefinedRoles().
 */
class PlayerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('player.view');
    }

    public function view(User $user, Player $player): bool
    {
        return $user->can('player.view') || $this->isOwnProfile($user, $player);
    }

    public function create(User $user): bool
    {
        return $user->can('player.create');
    }

    public function update(User $user, Player $player): bool
    {
        return $user->can('player.edit') || $this->isOwnProfile($user, $player);
    }

    public function delete(User $user, Player $player): bool
    {
        return $user->can('player.delete');
    }

    public function restore(User $user, Player $player): bool
    {
        return $user->can('player.delete');
    }

    public function forceDelete(User $user, Player $player): bool
    {
        return $user->hasRole('Superadmin');
    }

    private function isOwnProfile(User $user, Player $player): bool
    {
        return $player->user_id !== null && $player->user_id === $user->id;
    }
}
