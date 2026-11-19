<?php

namespace App\Livewire\Forms;

use App\Actions\AssignStudentToClass;
use App\Enums\Role;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class UserForm extends Form
{
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'student';

    public string $schoolClassId = '';

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user?->id),
            ],
            'role' => ['required', Rule::enum(Role::class)],
            'schoolClassId' => ['nullable', Rule::exists(SchoolClass::class, 'id')->whereIn('school_year_id', SchoolYear::open()->pluck('id'))],
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'name' => 'naam',
            'email' => 'e-mailadres',
            'role' => 'rol',
            'schoolClassId' => 'klas',
        ];
    }

    public function fillFrom(User $user): void
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->getRoleNames()->first() ?? Role::Student->value;
        $this->schoolClassId = (string) $user->currentSchoolClasses()->value('school_classes.id');
    }

    public function save(): User
    {
        $this->email = Str::lower(trim($this->email));

        $this->validate();

        $user = $this->user ?? new User;

        $user->fill([
            'name' => $this->name,
            'email' => $this->email,
        ])->save();

        if (! $user->is(auth()->user())) {
            $user->syncRoles([$this->role]);
        }

        $this->saveSchoolClass($user);

        return $user;
    }

    private function saveSchoolClass(User $user): void
    {
        if ($this->schoolClassId !== '') {
            app(AssignStudentToClass::class)->execute($user, SchoolClass::findOrFail((int) $this->schoolClassId));

            return;
        }

        $user->schoolClasses()->detach($user->currentSchoolClasses()->pluck('school_classes.id'));
    }
}
