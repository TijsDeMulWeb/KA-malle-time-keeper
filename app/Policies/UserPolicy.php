<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageUsers->value);
    }

    public function view(User $user, User $model): bool
    {
        if ($user->is($model)) {
            return true;
        }

        return $user->hasPermissionTo(Permission::ViewAllReports->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageUsers->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermissionTo(Permission::ManageUsers->value);
    }

    public function impersonate(User $user, User $model): bool
    {
        if ($user->is($model)) {
            return false;
        }

        if ($model->hasPermissionTo(Permission::ManageUsers->value)) {
            return false;
        }

        return $user->hasPermissionTo(Permission::ManageUsers->value);
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->is($model)) {
            return false;
        }

        return $user->hasPermissionTo(Permission::ManageUsers->value);
    }
}
