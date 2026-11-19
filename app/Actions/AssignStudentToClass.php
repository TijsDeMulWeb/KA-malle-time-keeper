<?php

namespace App\Actions;

use App\Models\SchoolClass;
use App\Models\User;

class AssignStudentToClass
{
    public function execute(User $student, SchoolClass $schoolClass): void
    {
        $otherClassesThisYear = $student->schoolClasses()
            ->where('school_year_id', $schoolClass->school_year_id)
            ->whereKeyNot($schoolClass->id)
            ->pluck('school_classes.id');

        $student->schoolClasses()->detach($otherClassesThisYear);
        $student->schoolClasses()->syncWithoutDetaching([$schoolClass->id]);
    }
}
