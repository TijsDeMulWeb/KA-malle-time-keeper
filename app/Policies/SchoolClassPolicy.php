<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    public function view(User $user, SchoolClass $schoolClass): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchoolYears->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchoolYears->value);
    }

    public function update(User $user, SchoolClass $schoolClass): bool
    {
        if (! $user->hasPermissionTo(Permission::ManageSchoolYears->value)) {
            return false;
        }

        return ! $schoolClass->isClosed();
    }

    public function delete(User $user, SchoolClass $schoolClass): bool
    {
        if (! $this->update($user, $schoolClass)) {
            return false;
        }

        if ($schoolClass->students()->exists()) {
            return false;
        }

        return ! $schoolClass->projects()->exists();
    }
}
