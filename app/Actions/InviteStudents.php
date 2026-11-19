<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Collection;

class InviteStudents
{
    public function __construct(
        private SendInvitation $sendInvitation,
        private AssignStudentToClass $assignStudentToClass,
    ) {}

    /**
     * Returns the email addresses the invitation could not be sent to.
     *
     * @param  Collection<int, array{name: string, email: string}>  $students
     * @return Collection<int, string>
     */
    public function execute(Collection $students, ?SchoolClass $schoolClass): Collection
    {
        $failedEmails = collect();

        $students->each(function (array $student) use ($schoolClass, $failedEmails) {
            $user = User::create($student);
            $user->assignRole(Role::Student->value);

            if ($schoolClass) {
                $this->assignStudentToClass->execute($user, $schoolClass);
            }

            if (! $this->sendInvitation->execute($user)) {
                $failedEmails->push($user->email);
            }
        });

        return $failedEmails;
    }
}
