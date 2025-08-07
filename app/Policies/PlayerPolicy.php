<?php

namespace App\Policies;

use App\Models\Player;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PlayerPolicy
{
    /**
     * Determine whether the user can view any models.
     * This applies to viewing a list of all players.
     */
    public function viewAny(User $user): bool
    {
        // Superadmin and Admin can view all players
        if ($user->hasRole(['superadmin', 'admin', 'organizer', 'team_manager', 'coach', 'captain', 'viewer'])) {
            return true;
        }

        // Players can only view their own profile (handled by 'view' method specifically)
        return false;
    }

    /**
     * Determine whether the user can view the model.
     * This applies to viewing a specific player's profile.
     */
    public function view(User $user, Player $player): bool
    {
        // Superadmin, Admin, Organizer, Team Manager, Coach, Viewer can view any player
        if ($user->hasRole(['superadmin', 'admin', 'organizer', 'team_manager', 'coach', 'viewer'])) {
            return true;
        }

        // Captains can view players on their team (assuming User has a 'team_id' or relation)
        // AND Captains can view their own player profile if they are also a player
        if ($user->hasRole('captain')) {
            // Check if the captain's user record is linked to a team and that team matches the player's team
            // OR if the captain is trying to view their own player record
            if (($user->team_id && $user->team_id === $player->team_id) || ($user->id === $player->user_id)) {
                return true;
            }
        }

        // Players can view their own profile if their user_id matches the player's user_id
        if ($user->hasRole('player') && $user->id === $player->user_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     * This applies to registering a new player.
     */
    public function create(User $user): bool
    {
        // Superadmin, Admin, and Organizer can create players
        if ($user->hasRole(['superadmin', 'admin', 'organizer'])) {
            return true;
        }

        // A Team Manager can create players (presumably for their own team)
        // From saved information: "Manager or admin can create team." - this implies they can also add players.
        if ($user->hasRole('team_manager')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     * This includes updating basic player details AND the player's status.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Player  $player
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Player $player): Response|bool
    {
        // Superadmin and Admin can update any player (including status)
        if ($user->hasRole(['superadmin', 'admin'])) {
            return true;
        }

        // Organizers can update any player (including status)
        if ($user->hasRole('organizer')) {
            return true;
        }

        // Team Managers can update players belonging to their team
        // Assumes the User model has a `team_id` or similar relationship that links them to a team they manage.
        // And the player has a `team_id`.
        if ($user->hasRole('team_manager') && $user->team_id === $player->team_id) {
            return true;
        }

        // Captains can update players belonging to their team (e.g., their own profile or team members)
        if ($user->hasRole('captain') && $user->team_id === $player->team_id) {
            return true;
        }

        // A 'player' role can only update their own profile
        if ($user->hasRole('player') && $user->id === $player->user_id) {
            return true;
        }

        // If none of the above conditions are met, deny access.
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Player $player): bool
    {
        // Superadmin, Admin, Organizer can delete any player
        if ($user->hasRole(['superadmin', 'admin', 'organizer'])) {
            return true;
        }

        // Team Managers can delete players only if they belong to their team
        if ($user->hasRole('team_manager') && $user->team_id === $player->team_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Player $player): bool
    {
        // Typically only Superadmin/Admin can restore soft-deleted items
        return $user->hasRole(['superadmin', 'admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Player $player): bool
    {
        // Typically only Superadmin can force delete items
        return $user->hasRole('superadmin');
    }

    // You might also consider a dedicated method if status updates are very distinct and need separate authorization
    // public function updateStatus(User $user, Player $player): Response|bool
    // {
    //     if ($user->hasRole(['superadmin', 'admin', 'organizer'])) {
    //         return true;
    //     }
    //     if ($user->hasRole('team_manager') && $user->team_id === $player->team_id) {
    //         return true;
    //     }
    //     return false;
    // }
}
