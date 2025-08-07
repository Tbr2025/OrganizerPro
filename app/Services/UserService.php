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

        return $query->paginateData([
            'per_page' => $filters['per_page'] ?? config('settings.default_pagination') ?? 10,
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
