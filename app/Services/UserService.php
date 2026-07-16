<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Get users with filters
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getUsers(array $filters = [])
    {
        // Use the QueryBuilderTrait methods directly from the User model
        $query = User::applyFilters($filters);

        // Always hide Superadmin users from the list
        $query->whereDoesntHave('roles', function ($q) {
            $q->where('name', 'Superadmin');
        });

        // Exclude users who ONLY have the Player role (no other roles).
        // Users with Player + another role (e.g. Team Manager) stay visible.
        if (empty($filters['role']) || $filters['role'] !== 'Player') {
            $query->where(function ($q) {
                $q->whereDoesntHave('roles', fn($r) => $r->where('name', 'Player'))
                  ->orWhereHas('roles', fn($r) => $r->where('name', '!=', 'Player')->where('name', '!=', 'Superadmin'));
            });
        }

        // Eager-load player for status display
        $query->with(['player', 'actualTeams']);

        return $query->paginateData([
            'per_page' => $filters['per_page'] ?? 12,
        ]);
    }

    public function createUser(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
        ]);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return $user;
    }

    public function getUserById(int $id): ?User
    {
        return User::findOrFail($id);
    }

    public function updateUser(User $user, array $data): User
    {
        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'],
        ];

        if (isset($data['password']) && ! empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return $user->refresh();
    }
}
