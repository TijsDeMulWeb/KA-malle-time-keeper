<?php

namespace App\Livewire\Forms;

use App\Models\SchoolYear;
use Illuminate\Validation\Rule;
use Livewire\Form;

class SchoolYearForm extends Form
{
    public ?SchoolYear $schoolYear = null;

    public string $name = '';

    public string $startsOn = '';

    public string $endsOn = '';

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique(SchoolYear::class, 'name')->ignore($this->schoolYear?->id)],
            'startsOn' => ['required', 'date_format:Y-m-d'],
            'endsOn' => ['required', 'date_format:Y-m-d', 'after:startsOn'],
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'name' => 'naam',
            'startsOn' => 'startdatum',
            'endsOn' => 'einddatum',
        ];
    }

    public function prepareNew(): void
    {
        $latest = SchoolYear::orderByDesc('starts_on')->first();

        $startYear = $latest
            ? $latest->starts_on->year + 1
            : now()->subMonths(8)->year;

        $endYear = $startYear + 1;

        $this->schoolYear = null;
        $this->name = "{$startYear}-{$endYear}";
        $this->startsOn = "{$startYear}-09-01";
        $this->endsOn = "{$endYear}-08-31";
    }

    public function fillFrom(SchoolYear $schoolYear): void
    {
        $this->schoolYear = $schoolYear;
        $this->name = $schoolYear->name;
        $this->startsOn = $schoolYear->starts_on->format('Y-m-d');
        $this->endsOn = $schoolYear->ends_on->format('Y-m-d');
    }

    public function save(): ?SchoolYear
    {
        $this->validate();

        $overlapping = SchoolYear::whereDate('starts_on', '<=', $this->endsOn)
            ->whereDate('ends_on', '>=', $this->startsOn)
            ->when($this->schoolYear, fn ($query) => $query->whereKeyNot($this->schoolYear->id))
            ->first();

        if ($overlapping) {
            $this->addError('startsOn', __('Overlapt met schooljaar :name.', ['name' => $overlapping->name]));

            return null;
        }

        $schoolYear = $this->schoolYear ?? new SchoolYear;

        $schoolYear->fill([
            'name' => $this->name,
            'starts_on' => $this->startsOn,
            'ends_on' => $this->endsOn,
        ])->save();

        return $schoolYear;
    }
}
