<?php

namespace App\Models;

use App\Enums\Role;
use App\Support\StudentView;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property Carbon|null $invited_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'invited_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles {
        hasPermissionTo as hasRolePermissionTo;
    }
    use Notifiable;
    use PasskeyAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'invited_at' => 'datetime',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        if (StudentView::isActiveFor($this)) {
            return false;
        }

        return $this->hasRolePermissionTo($permission, $guardName);
    }

    public function schoolClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class)->withTimestamps();
    }

    public function currentSchoolClasses(): BelongsToMany
    {
        return $this->schoolClasses()->inOpenYear();
    }

    public function hasAccess(): bool
    {
        if (! $this->hasRole(Role::Student->value)) {
            return true;
        }

        return $this->currentSchoolClasses()->exists();
    }

    public function timeLoggingRestriction(CarbonInterface $moment): ?string
    {
        if (SchoolYear::isLockedAt($moment)) {
            return 'Dit schooljaar is afgesloten. Je kan hier geen uren meer registreren of wijzigen.';
        }

        if (! $this->hasRole(Role::Student->value)) {
            return null;
        }

        $inOwnSchoolYear = $this->currentSchoolClasses()
            ->whereHas('schoolYear', fn ($query) => $query->covering($moment))
            ->exists();

        if ($inOwnSchoolYear) {
            return null;
        }

        return 'Je kan alleen uren registreren binnen je eigen schooljaar.';
    }

    public function canLogTimeOn(CarbonInterface $moment): bool
    {
        return $this->timeLoggingRestriction($moment) === null;
    }

    public function hasAcceptedInvitation(): bool
    {
        return $this->password !== null;
    }

    public function invitationHasExpired(): bool
    {
        if ($this->hasAcceptedInvitation()) {
            return false;
        }

        if (! $this->invited_at) {
            return true;
        }

        return $this->invited_at->addDays(config('time-keeper.invitation_expires_in_days'))->isPast();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin->value);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
