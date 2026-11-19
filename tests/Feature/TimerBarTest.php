<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TimerBarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-19 20:00:30');

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();
        $this->actingAs($this->user);
    }

    public function test_timer_starts_a_live_entry(): void
    {
        Livewire::test('timer-bar')
            ->set('description', 'Testing SSO')
            ->set('projectId', (string) $this->project->id)
            ->call('start')
            ->assertHasNoErrors()
            ->assertDispatched('entries-changed')
            ->assertSet('description', '');

        $entry = TimeEntry::first();

        $this->assertTrue($entry->is_live);
        $this->assertNull($entry->ended_at);
        $this->assertSame('Testing SSO', $entry->description);
        $this->assertSame('2026-11-19 20:00:30', $entry->started_at->format('Y-m-d H:i:s'));
    }

    public function test_timer_requires_a_project(): void
    {
        Livewire::test('timer-bar')
            ->set('projectId', '')
            ->call('start')
            ->assertHasErrors(['projectId']);

        $this->assertSame(0, TimeEntry::count());
    }

    public function test_timer_can_start_without_description(): void
    {
        Livewire::test('timer-bar')
            ->set('projectId', (string) $this->project->id)
            ->call('start')
            ->assertHasNoErrors();

        $this->assertSame('', TimeEntry::first()->description);
    }

    public function test_only_one_timer_runs_at_a_time(): void
    {
        $component = Livewire::test('timer-bar')
            ->set('projectId', (string) $this->project->id)
            ->call('start');

        $component->call('start');

        $this->assertSame(1, TimeEntry::count());
    }

    public function test_timer_cannot_start_inside_an_existing_entry(): void
    {
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'started_at' => '2026-11-19 19:00:00',
            'ended_at' => '2026-11-19 21:00:00',
        ]);

        Livewire::test('timer-bar')
            ->set('projectId', (string) $this->project->id)
            ->call('start')
            ->assertHasErrors(['description']);

        $this->assertSame(1, TimeEntry::count());
    }

    public function test_timer_can_start_right_after_a_finished_entry(): void
    {
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'started_at' => '2026-11-19 19:00:00',
            'ended_at' => '2026-11-19 20:00:00',
        ]);

        Livewire::test('timer-bar')
            ->set('projectId', (string) $this->project->id)
            ->call('start')
            ->assertHasNoErrors();

        $this->assertSame(2, TimeEntry::count());
    }

    public function test_stopping_sets_the_end_time(): void
    {
        $component = Livewire::test('timer-bar')
            ->set('projectId', (string) $this->project->id)
            ->call('start');

        $this->travel(25)->minutes();

        $component->call('stop')->assertDispatched('entries-changed');

        $entry = TimeEntry::first();

        $this->assertNotNull($entry->ended_at);
        $this->assertSame(25, $entry->durationInMinutes());
    }

    public function test_timer_keeps_running_after_a_refresh(): void
    {
        TimeEntry::factory()->running()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'description' => 'Bezig met iets',
        ]);

        Livewire::test('timer-bar')
            ->assertSee('Bezig met iets')
            ->assertSee('0:10:00')
            ->assertSee('Stop');
    }

    public function test_a_stopped_entry_always_has_a_positive_duration(): void
    {
        $component = Livewire::test('timer-bar')
            ->set('projectId', (string) $this->project->id)
            ->call('start');

        $component->call('stop');

        $this->assertSame(1, TimeEntry::first()->durationInMinutes());
    }

    public function test_the_default_project_is_the_last_used_one(): void
    {
        $other = Project::factory()->for($this->user)->create(['name' => 'Zzz']);

        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $other->id,
        ]);

        Livewire::test('timer-bar')->assertSet('projectId', (string) $other->id);
    }

    public function test_timer_can_start_while_an_entry_is_planned_in_the_future(): void
    {
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'started_at' => '2026-11-20 10:00:00',
            'ended_at' => '2026-11-20 12:00:00',
        ]);

        Livewire::test('timer-bar')
            ->set('projectId', (string) $this->project->id)
            ->call('start')
            ->assertHasNoErrors();

        $this->assertSame(2, TimeEntry::count());
    }
}
