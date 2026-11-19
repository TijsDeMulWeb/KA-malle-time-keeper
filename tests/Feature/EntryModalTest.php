<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EntryModalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();
        $this->actingAs($this->user);
    }

    public function test_a_new_entry_can_be_created_in_brussels_time(): void
    {
        $category = Category::factory()->create();

        Livewire::test('entry-modal')
            ->call('open', date: '2026-11-18', start: '10:00', end: '11:00')
            ->set('form.description', 'Designing layout')
            ->set('form.projectId', (string) $this->project->id)
            ->set('form.categoryId', (string) $category->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('show', false)
            ->assertDispatched('entries-changed');

        $entry = TimeEntry::first();

        $this->assertSame('2026-11-18 09:00:00', $entry->started_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-18 10:00:00', $entry->ended_at->format('Y-m-d H:i:s'));
        $this->assertSame('10:00', $entry->localStartedAt()->format('H:i'));
        $this->assertSame($category->id, $entry->category_id);
        $this->assertFalse($entry->is_live);
    }

    public function test_the_category_is_optional(): void
    {
        Livewire::test('entry-modal')
            ->call('open', date: '2026-11-18', start: '10:00', end: '11:00')
            ->set('form.description', 'Zonder categorie')
            ->set('form.projectId', (string) $this->project->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(TimeEntry::first()->category_id);
    }

    public function test_end_must_be_after_start(): void
    {
        Livewire::test('entry-modal')
            ->call('open', date: '2026-11-18', start: '10:00', end: '09:00')
            ->set('form.description', 'Foutje')
            ->set('form.projectId', (string) $this->project->id)
            ->call('save')
            ->assertHasErrors(['form.end']);

        $this->assertSame(0, TimeEntry::count());
    }

    public function test_overlapping_entries_are_rejected(): void
    {
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'started_at' => '2026-11-18 09:00:00',
            'ended_at' => '2026-11-18 10:00:00',
        ]);

        Livewire::test('entry-modal')
            ->call('open', date: '2026-11-18', start: '10:30', end: '11:30')
            ->set('form.description', 'Overlap')
            ->set('form.projectId', (string) $this->project->id)
            ->call('save')
            ->assertHasErrors(['form.end']);

        $this->assertSame(1, TimeEntry::count());
    }

    public function test_back_to_back_entries_are_allowed(): void
    {
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'started_at' => '2026-11-18 09:00:00',
            'ended_at' => '2026-11-18 10:00:00',
        ]);

        Livewire::test('entry-modal')
            ->call('open', date: '2026-11-18', start: '11:00', end: '12:00')
            ->set('form.description', 'Direct erna')
            ->set('form.projectId', (string) $this->project->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, TimeEntry::count());
    }

    public function test_an_entry_can_be_edited_without_overlapping_itself(): void
    {
        $entry = TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'started_at' => '2026-11-18 09:00:00',
            'ended_at' => '2026-11-18 10:00:00',
        ]);

        Livewire::test('entry-modal')
            ->call('open', entry: $entry->id)
            ->assertSet('form.start', '10:00')
            ->assertSet('form.end', '11:00')
            ->set('form.end', '11:30')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('2026-11-18 10:30:00', $entry->refresh()->ended_at->format('Y-m-d H:i:s'));
    }

    public function test_an_entry_can_be_deleted(): void
    {
        $entry = TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
        ]);

        Livewire::test('entry-modal')
            ->call('open', entry: $entry->id)
            ->call('delete');

        $this->assertModelMissing($entry);
    }

    public function test_entries_of_other_users_cannot_be_opened(): void
    {
        $entry = TimeEntry::factory()->create();

        Livewire::test('entry-modal')
            ->call('open', entry: $entry->id)
            ->assertSet('show', false);
    }

    public function test_a_project_of_another_user_is_rejected(): void
    {
        $foreignProject = Project::factory()->create();

        Livewire::test('entry-modal')
            ->call('open', date: '2026-11-18', start: '10:00', end: '11:00')
            ->set('form.description', 'Hacking')
            ->set('form.projectId', (string) $foreignProject->id)
            ->call('save')
            ->assertHasErrors(['form.projectId']);
    }

    public function test_a_running_entry_can_be_edited_and_keeps_running(): void
    {
        $entry = TimeEntry::factory()->running()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
        ]);

        Livewire::test('entry-modal')
            ->call('open', entry: $entry->id)
            ->set('form.description', 'Nog bezig')
            ->call('save')
            ->assertHasNoErrors();

        $entry->refresh();

        $this->assertSame('Nog bezig', $entry->description);
        $this->assertNull($entry->ended_at);
    }

    public function test_an_end_of_midnight_is_shown_as_the_last_minute_of_the_day(): void
    {
        Livewire::test('entry-modal')
            ->call('open', date: '2026-11-18', start: '22:00', end: '24:00')
            ->assertSet('form.end', '23:59');
    }
}
