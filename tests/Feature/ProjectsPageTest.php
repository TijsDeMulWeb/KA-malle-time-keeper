<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_page_lists_only_my_active_projects(): void
    {
        Project::factory()->for($this->user)->create(['name' => 'Game Launcher']);
        Project::factory()->for($this->user)->archived()->create(['name' => 'Oud project']);
        Project::factory()->create(['name' => 'Van iemand anders']);

        Livewire::test('pages::projects.index')
            ->assertSee('Game Launcher')
            ->assertDontSee('Oud project')
            ->assertDontSee('Van iemand anders')
            ->set('status', 'archived')
            ->assertSee('Oud project')
            ->assertDontSee('Game Launcher');
    }

    public function test_projects_can_be_searched_by_name_and_client(): void
    {
        Project::factory()->for($this->user)->create(['name' => 'Game Launcher', 'client' => null]);
        Project::factory()->for($this->user)->create(['name' => 'AV booking', 'client' => 'Ivan']);

        Livewire::test('pages::projects.index')
            ->set('search', 'ivan')
            ->assertSee('AV booking')
            ->assertDontSee('Game Launcher')
            ->set('search', 'launch')
            ->assertSee('Game Launcher')
            ->assertDontSee('AV booking');
    }

    public function test_card_shows_total_and_week_hours(): void
    {
        $this->travelTo('2026-11-19 12:00:00');

        $project = Project::factory()->for($this->user)->create();

        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'started_at' => '2026-11-18 09:00:00',
            'ended_at' => '2026-11-18 10:30:00',
        ]);
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'started_at' => '2026-11-02 09:00:00',
            'ended_at' => '2026-11-02 11:00:00',
        ]);

        Livewire::test('pages::projects.index')
            ->assertSee('3u 30m')
            ->assertSee('1u 30m deze week');
    }

    public function test_a_project_can_be_created(): void
    {
        Livewire::test('pages::projects.index')
            ->call('create')
            ->set('form.name', 'Fablab Opvolging')
            ->set('form.client', 'Ivan')
            ->set('form.color', '#6b7280')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('projects', [
            'user_id' => $this->user->id,
            'name' => 'Fablab Opvolging',
            'client' => 'Ivan',
            'color' => '#6b7280',
        ]);
    }

    public function test_project_name_is_required_and_color_must_be_hex(): void
    {
        Livewire::test('pages::projects.index')
            ->call('create')
            ->set('form.name', '')
            ->set('form.color', 'rood')
            ->call('save')
            ->assertHasErrors(['form.name', 'form.color']);
    }

    public function test_a_project_can_be_edited_and_archived(): void
    {
        $project = Project::factory()->for($this->user)->create(['name' => 'Oud']);

        Livewire::test('pages::projects.index')
            ->call('edit', $project->id)
            ->assertSet('form.name', 'Oud')
            ->set('form.name', 'Nieuw')
            ->call('save')
            ->call('toggleArchive', $project->id);

        $project->refresh();

        $this->assertSame('Nieuw', $project->name);
        $this->assertTrue($project->isArchived());

        Livewire::test('pages::projects.index')->call('toggleArchive', $project->id);

        $this->assertFalse($project->refresh()->isArchived());
    }

    public function test_projects_of_other_users_cannot_be_edited(): void
    {
        $foreign = Project::factory()->create(['name' => 'Niet van mij']);

        Livewire::test('pages::projects.index')
            ->call('edit', $foreign->id)
            ->assertSet('showForm', false);

        Livewire::test('pages::projects.index')->call('toggleArchive', $foreign->id);

        $this->assertFalse($foreign->refresh()->isArchived());
    }
}
