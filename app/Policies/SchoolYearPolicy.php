<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SchoolYear;
use App\Models\User;

class SchoolYearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchoolYears->value);
    }

    public function view(User $user, SchoolYear $schoolYear): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchoolYears->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchoolYears->value);
    }

    public function update(User $user, SchoolYear $schoolYear): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchoolYears->value);
    }

    public function delete(User $user, SchoolYear $schoolYear): bool
    {
        if (! $user->hasPermissionTo(Permission::ManageSchoolYears->value)) {
            return false;
        }

        return ! $schoolYear->schoolClasses()->exists();
    }
}
