<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SharedProjectsTest extends TestCase
{
    use RefreshDatabase;

    private SchoolClass $schoolClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolClass = SchoolClass::factory()->create(['name' => '6A']);
    }

    public function test_admin_creates_a_project_for_a_class(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.school-classes.show', ['schoolClass' => $this->schoolClass])
            ->call('createProject')
            ->set('projectForm.name', 'Eindwerk')
            ->call('saveProject')
            ->assertHasNoErrors();

        $project = Project::where('name', 'Eindwerk')->firstOrFail();

        $this->assertTrue($project->isShared());
        $this->assertTrue($project->schoolClass->is($this->schoolClass));
    }

    public function test_students_of_the_class_see_the_project_but_cannot_edit_it(): void
    {
        $shared = $this->schoolClass->projects()->create(['name' => 'Eindwerk', 'color' => '#14b8a6']);
        SchoolClass::factory()->create()->projects()->create(['name' => 'Andere klas', 'color' => '#14b8a6']);

        Livewire::actingAs(User::factory()->student($this->schoolClass)->create())
            ->test('pages::projects.index')
            ->assertSee('Eindwerk')
            ->assertSee('data-test="shared-badge"', false)
            ->assertDontSee('Andere klas')
            ->call('edit', $shared->id)
            ->assertForbidden();
    }

    public function test_students_can_log_time_on_their_class_project(): void
    {
        $student = User::factory()->student($this->schoolClass)->create();
        $shared = $this->schoolClass->projects()->create(['name' => 'Eindwerk', 'color' => '#14b8a6']);

        Livewire::actingAs($student)
            ->test('timer-bar')
            ->set('projectId', (string) $shared->id)
            ->set('description', 'Moodboard')
            ->call('start')
            ->assertHasNoErrors();

        $this->assertSame($shared->id, $student->timeEntries()->value('project_id'));
    }

    public function test_students_cannot_log_time_on_projects_of_other_classes_or_users(): void
    {
        $student = User::factory()->student($this->schoolClass)->create();
        $otherClassProject = SchoolClass::factory()->create()->projects()->create(['name' => 'Andere klas', 'color' => '#14b8a6']);

        foreach ([$otherClassProject, Project::factory()->create()] as $project) {
            Livewire::actingAs($student)
                ->test('timer-bar')
                ->set('projectId', (string) $project->id)
                ->call('start')
                ->assertHasErrors('projectId');
        }
    }

    public function test_class_projects_disappear_when_the_year_is_closed(): void
    {
        $student = User::factory()->student($this->schoolClass)->create();
        $this->schoolClass->projects()->create(['name' => 'Eindwerk', 'color' => '#14b8a6']);

        $newClass = SchoolClass::factory()->for(SchoolYear::factory())->create();
        $newClass->students()->attach($student);

        $this->schoolClass->schoolYear->update(['closed_at' => now()]);

        Livewire::actingAs($student)
            ->test('pages::projects.index')
            ->assertDontSee('Eindwerk');
    }

    public function test_students_cannot_add_class_projects(): void
    {
        Livewire::actingAs(User::factory()->student($this->schoolClass)->create())
            ->test('pages::admin.school-classes.show', ['schoolClass' => $this->schoolClass])
            ->assertForbidden();
    }
}
