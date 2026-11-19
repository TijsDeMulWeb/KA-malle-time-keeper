<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClosedSchoolYearTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private TimeEntry $oldEntry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-19 12:00:00');

        $oldClass = SchoolClass::factory()->for(SchoolYear::factory()->starting(2025)->closed())->create();
        $currentClass = SchoolClass::factory()->for(SchoolYear::factory()->starting(2026))->create();

        $this->student = User::factory()->student($currentClass)->create();
        $this->student->schoolClasses()->attach($oldClass);

        $this->oldEntry = TimeEntry::factory()->create([
            'user_id' => $this->student->id,
            'project_id' => Project::factory()->for($this->student),
            'started_at' => '2026-03-10 09:00:00',
            'ended_at' => '2026-03-10 10:00:00',
        ]);

        $this->actingAs($this->student);
    }

    public function test_locked_entries_cannot_be_opened_or_moved(): void
    {
        Livewire::test('entry-modal')
            ->call('open', entry: $this->oldEntry->id)
            ->assertSet('show', false);

        Livewire::test('pages::entries.index', ['week' => '2026-03-09'])
            ->assertSee('data-test="closed-year-notice"', false)
            ->call('moveEntry', $this->oldEntry->id, '2026-03-11', '09:00', '10:00');

        $this->assertSame('2026-03-10 09:00:00', $this->oldEntry->fresh()->started_at->format('Y-m-d H:i:s'));
    }

    public function test_new_entries_cannot_be_added_in_a_closed_year(): void
    {
        $project = Project::factory()->for($this->student)->create();

        Livewire::test('entry-modal')
            ->call('open', date: '2026-03-12', start: '09:00', end: '10:00')
            ->set('form.description', 'Te laat')
            ->set('form.projectId', (string) $project->id)
            ->call('save')
            ->assertHasErrors('form.date');
    }

    public function test_entries_in_an_open_year_can_still_be_edited(): void
    {
        $entry = TimeEntry::factory()->create([
            'user_id' => $this->student->id,
            'project_id' => Project::factory()->for($this->student),
            'started_at' => '2026-11-18 09:00:00',
            'ended_at' => '2026-11-18 10:00:00',
        ]);

        $this->assertTrue($this->student->can('update', $entry));
        $this->assertFalse($this->student->can('update', $this->oldEntry));
    }

    public function test_report_can_be_filtered_on_a_closed_school_year(): void
    {
        app()->setLocale('nl');

        $oldYear = SchoolYear::where('name', '2025-2026')->firstOrFail();

        Livewire::test('pages::reports.index')
            ->set('schoolYearId', (string) $oldYear->id)
            ->assertSet('filter', 'year')
            ->assertSee('1 september 2025')
            ->assertSee('1u');
    }
}
