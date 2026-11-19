<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;

class TimeEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TimeEntry $timeEntry): bool
    {
        if ($timeEntry->user_id !== $user->id) {
            return false;
        }

        return $user->canLogTimeOn($timeEntry->started_at);
    }

    public function delete(User $user, TimeEntry $timeEntry): bool
    {
        return $this->update($user, $timeEntry);
    }
}
