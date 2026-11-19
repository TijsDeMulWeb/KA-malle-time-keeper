<?php

namespace App\Livewire\Forms;

use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class BulkInviteForm extends Form
{
    public string $lines = '';

    public string $schoolClassId = '';

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'string', 'max:20000'],
            'schoolClassId' => ['nullable', Rule::exists(SchoolClass::class, 'id')->whereIn('school_year_id', SchoolYear::open()->pluck('id'))],
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'lines' => 'studenten',
            'schoolClassId' => 'klas',
        ];
    }

    public function schoolClass(): ?SchoolClass
    {
        return $this->schoolClassId !== '' ? SchoolClass::find((int) $this->schoolClassId) : null;
    }

    /** @return Collection<int, array{name: string, email: string}>|null */
    public function students(): ?Collection
    {
        $this->validate();

        $students = Str::of($this->lines)
            ->explode("\n")
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->map(fn (string $line) => $this->parseLine($line))
            ->unique('email')
            ->values();

        $invalid = $students->first(fn (array $student) => ! filter_var($student['email'], FILTER_VALIDATE_EMAIL));

        if ($invalid) {
            $this->addError('lines', __('Ongeldig e-mailadres: :email', ['email' => $invalid['email']]));

            return null;
        }

        $existing = User::whereIn('email', $students->pluck('email'))->pluck('email');

        if ($existing->isNotEmpty()) {
            $this->addError('lines', __('Bestaat al: :emails', ['emails' => $existing->implode(', ')]));

            return null;
        }

        return $students;
    }

    /** @return array{name: string, email: string} */
    private function parseLine(string $line): array
    {
        $parts = Str::of($line)->split('/[,;\t]/')->map(fn (string $part) => trim($part));

        $email = $parts->first(fn (string $part) => str_contains($part, '@')) ?? $parts->last();
        $name = $parts->reject(fn (string $part) => $part === $email)->implode(' ');

        if ($name === '') {
            $name = Str::of($email)->before('@')->replace(['.', '_', '-'], ' ')->title()->toString();
        }

        return [
            'name' => $name,
            'email' => Str::lower($email),
        ];
    }
}
