<?php

namespace App\Livewire\Forms;

use App\Models\TimeEntry;
use App\Support\Duration;
use App\Support\LocalTime;
use Illuminate\Validation\Rule;
use Livewire\Form;

class EntryForm extends Form
{
    public ?TimeEntry $entry = null;

    public string $description = '';

    public string $projectId = '';

    public string $categoryId = '';

    public string $date = '';

    public string $start = '';

    public string $end = '';

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'projectId' => [
                'required',
                Rule::exists('projects', 'id')->where('user_id', auth()->id()),
            ],
            'categoryId' => ['nullable', 'exists:categories,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'date_format:H:i'],
            'end' => [
                Rule::requiredIf(fn () => ! $this->isRunning()),
                'nullable',
                'date_format:H:i',
                'after:start',
            ],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'description' => 'omschrijving',
            'projectId' => 'project',
            'categoryId' => 'categorie',
            'date' => 'datum',
            'start' => 'begintijd',
            'end' => 'eindtijd',
        ];
    }

    public function fillFrom(TimeEntry $entry): void
    {
        $started = $entry->localStartedAt();
        $ended = $entry->localEndedAt();

        $this->entry = $entry;
        $this->description = $entry->description;
        $this->projectId = (string) $entry->project_id;
        $this->categoryId = (string) $entry->category_id;
        $this->date = $started->format('Y-m-d');
        $this->start = $started->format('H:i');
        $this->end = $ended?->format('H:i') ?? '';
    }

    public function prepareNew(string $date, string $start, string $end, string $projectId): void
    {
        $this->reset();
        $this->resetErrorBag();

        $this->date = $date;
        $this->start = $start;
        $this->end = $end;
        $this->projectId = $projectId;
    }

    public function isRunning(): bool
    {
        return $this->entry?->isRunning() ?? false;
    }

    public function durationLabel(): string
    {
        $minutes = $this->durationInMinutes();

        return $minutes > 0 ? Duration::format($minutes) : '–';
    }

    public function save(): bool
    {
        $this->validate();

        $startedAt = LocalTime::toUtc($this->date, $this->start);
        $endedAt = $this->end !== '' ? LocalTime::toUtc($this->date, $this->end) : null;

        if ($this->overlaps($startedAt, $endedAt)) {
            $this->addError('end', 'Deze registratie overlapt met een andere registratie.');

            return false;
        }

        $attributes = [
            'description' => $this->description,
            'project_id' => (int) $this->projectId,
            'category_id' => $this->categoryId !== '' ? (int) $this->categoryId : null,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ];

        if ($this->entry) {
            $this->entry->update($attributes);

            return true;
        }

        auth()->user()->timeEntries()->create($attributes);

        return true;
    }

    private function overlaps($startedAt, $endedAt): bool
    {
        return auth()->user()->timeEntries()
            ->overlapping($startedAt, $endedAt)
            ->when($this->entry, fn ($query) => $query->whereKeyNot($this->entry->id))
            ->exists();
    }

    private function durationInMinutes(): int
    {
        if ($this->date === '' || $this->start === '' || $this->end === '') {
            return 0;
        }

        $start = LocalTime::parse($this->date, $this->start);
        $end = LocalTime::parse($this->date, $this->end);

        return $end->greaterThan($start) ? (int) $start->diffInMinutes($end) : 0;
    }
}
