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

class OwnSchoolYearTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-19 12:00:00');

        SchoolYear::factory()->starting(2027)->create();
        $schoolClass = SchoolClass::factory()->for(SchoolYear::factory()->starting(2026))->create();

        $this->student = User::factory()->student($schoolClass)->create();
        $this->project = Project::factory()->for($this->student)->create();

        $this->actingAs($this->student);
    }

    public function test_student_can_log_time_within_their_school_year(): void
    {
        Livewire::test('entry-modal')
            ->call('open', date: '2026-11-18', start: '09:00', end: '10:00')
            ->set('form.description', 'Schetsen')
            ->set('form.projectId', (string) $this->project->id)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_student_cannot_log_time_outside_their_school_year(): void
    {
        foreach (['2026-08-31', '2027-09-15'] as $date) {
            Livewire::test('entry-modal')
                ->call('open', date: '2026-11-18', start: '09:00', end: '10:00')
                ->set('form.date', $date)
                ->set('form.description', 'Te vroeg of te laat')
                ->set('form.projectId', (string) $this->project->id)
                ->call('save')
                ->assertHasErrors('form.date');
        }

        $this->assertSame(0, $this->student->timeEntries()->count());
    }

    public function test_modal_does_not_open_on_a_day_outside_the_school_year(): void
    {
        Livewire::test('entry-modal')
            ->call('open', date: '2027-09-15', start: '09:00', end: '10:00')
            ->assertSet('show', false);
    }

    public function test_entries_cannot_be_moved_outside_the_school_year(): void
    {
        $entry = TimeEntry::factory()->create([
            'user_id' => $this->student->id,
            'project_id' => $this->project->id,
            'started_at' => '2026-11-18 09:00:00',
            'ended_at' => '2026-11-18 10:00:00',
        ]);

        Livewire::test('pages::entries.index')
            ->call('moveEntry', $entry->id, '2027-09-15', '09:00', '10:00');

        $this->assertSame('2026-11-18', $entry->fresh()->started_at->format('Y-m-d'));
    }

    public function test_week_outside_the_school_year_shows_a_notice(): void
    {
        Livewire::test('pages::entries.index', ['week' => '2027-09-13'])
            ->assertSee('data-test="outside-school-year-notice"', false);

        Livewire::test('pages::entries.index', ['week' => '2026-11-16'])
            ->assertDontSee('data-test="outside-school-year-notice"', false);
    }

    public function test_timer_cannot_start_outside_the_school_year(): void
    {
        $this->travelTo('2027-09-15 12:00:00');

        Livewire::test('timer-bar')
            ->set('projectId', (string) $this->project->id)
            ->call('start')
            ->assertHasErrors('description');
    }

    public function test_admins_can_log_time_on_any_date(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->canLogTimeOn(now()->addYears(3)));
    }
}
