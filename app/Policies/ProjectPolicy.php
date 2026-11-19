<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function createShared(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageSharedProjects->value);
    }

    public function update(User $user, Project $project): bool
    {
        if ($project->isShared()) {
            if ($project->schoolClass?->isClosed()) {
                return false;
            }

            return $user->hasPermissionTo(Permission::ManageSharedProjects->value);
        }

        return $project->user_id === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }
}
