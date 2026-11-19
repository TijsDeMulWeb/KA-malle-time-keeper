<?php

namespace App\Actions;

use App\Models\TimeEntry;
use App\Models\User;
use App\Rules\LoggableDate;
use App\Support\LocalTime;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MoveTimeEntry
{
    public function handle(User $user, TimeEntry $entry, string $date, string $start, string $end): void
    {
        Validator::make(
            ['date' => $date, 'start' => $start, 'end' => $end],
            [
                'date' => ['required', 'date_format:Y-m-d', new LoggableDate($user)],
                'start' => ['required', 'date_format:H:i'],
                'end' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$|^24:00$/', 'after:start'],
            ],
            attributes: ['date' => 'datum', 'start' => 'begintijd', 'end' => 'eindtijd'],
        )->validate();

        $startedAt = LocalTime::toUtc($date, $start);
        $endedAt = $end === '24:00' ? LocalTime::parse($date)->addDay()->utc() : LocalTime::toUtc($date, $end);

        $overlaps = $user->timeEntries()
            ->overlapping($startedAt, $endedAt)
            ->whereKeyNot($entry->id)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'end' => 'Deze registratie overlapt met een andere registratie.',
            ]);
        }

        $entry->update(['started_at' => $startedAt, 'ended_at' => $endedAt]);
    }
}
