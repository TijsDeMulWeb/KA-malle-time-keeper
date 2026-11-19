<?php

namespace App\Livewire\Forms;

use App\Models\Project;
use App\Models\SchoolClass;
use Livewire\Form;

class ProjectForm extends Form
{
    public ?Project $project = null;

    public string $name = '';

    public string $client = '';

    public string $color = '#14b8a6';

    public ?SchoolClass $schoolClass = null;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'client' => ['nullable', 'string', 'max:255'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'name' => 'naam',
            'client' => 'klant',
            'color' => 'kleur',
        ];
    }

    public function fillFrom(Project $project): void
    {
        $this->project = $project;
        $this->name = $project->name;
        $this->client = (string) $project->client;
        $this->color = $project->color;
        $this->schoolClass = $project->schoolClass;
    }

    public function save(): Project
    {
        $this->validate();

        $attributes = [
            'name' => $this->name,
            'client' => $this->client !== '' ? $this->client : null,
            'color' => strtolower($this->color),
        ];

        if ($this->project) {
            $this->project->update($attributes);

            return $this->project;
        }

        if ($this->schoolClass) {
            return $this->schoolClass->projects()->create($attributes);
        }

        return auth()->user()->projects()->create($attributes);
    }
}
