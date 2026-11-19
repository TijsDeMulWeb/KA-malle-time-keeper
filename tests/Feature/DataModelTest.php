<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_seeder_creates_the_seven_categories_once(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class);

        $this->assertSame(7, Category::count());
        $this->assertDatabaseHas('categories', ['name' => 'Standups & Meetings']);
    }

    public function test_user_has_default_weekly_goal_of_twenty_hours(): void
    {
        $user = User::factory()->create();

        $this->assertSame(1200, $user->refresh()->weekly_goal_minutes);
    }

    public function test_relationships_are_connected(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $entry = TimeEntry::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $this->assertTrue($entry->user->is($user));
        $this->assertTrue($entry->category->is($category));
        $this->assertTrue($entry->project->user->is($user));
        $this->assertTrue($user->timeEntries->first()->is($entry));
        $this->assertTrue($user->projects->first()->is($entry->project));
    }

    public function test_project_scopes_split_active_and_archived(): void
    {
        $user = User::factory()->create();
        $active = Project::factory()->for($user)->create();
        $archived = Project::factory()->for($user)->archived()->create();

        $this->assertTrue(Project::active()->get()->contains($active));
        $this->assertFalse(Project::active()->get()->contains($archived));
        $this->assertTrue(Project::archived()->get()->contains($archived));
        $this->assertTrue($archived->isArchived());
    }

    public function test_running_scope_finds_entries_without_end(): void
    {
        $running = TimeEntry::factory()->running()->create();
        $finished = TimeEntry::factory()->create();

        $this->assertTrue(TimeEntry::running()->get()->contains($running));
        $this->assertFalse(TimeEntry::running()->get()->contains($finished));
        $this->assertTrue(TimeEntry::finished()->get()->contains($finished));
        $this->assertTrue($running->isRunning());
    }

    public function test_starting_between_filters_in_the_query(): void
    {
        $inside = TimeEntry::factory()->create(['started_at' => '2026-11-18 10:00:00', 'ended_at' => '2026-11-18 11:00:00']);
        $outside = TimeEntry::factory()->create(['started_at' => '2026-11-25 10:00:00', 'ended_at' => '2026-11-25 11:00:00']);

        $entries = TimeEntry::startingBetween(Carbon::parse('2026-11-16'), Carbon::parse('2026-11-23'))->get();

        $this->assertTrue($entries->contains($inside));
        $this->assertFalse($entries->contains($outside));
    }

    public function test_duration_is_calculated_in_minutes(): void
    {
        $entry = TimeEntry::factory()->create([
            'started_at' => '2026-11-18 15:00:00',
            'ended_at' => '2026-11-18 16:25:00',
        ]);

        $this->assertSame(85, $entry->durationInMinutes());
    }

    public function test_running_entry_duration_uses_the_current_time(): void
    {
        $this->travelTo('2026-11-19 12:00:00');

        $entry = TimeEntry::factory()->create([
            'started_at' => '2026-11-19 11:30:00',
            'ended_at' => null,
        ]);

        $this->assertSame(30, $entry->durationInMinutes());
    }

    public function test_times_are_stored_in_utc_and_shown_in_brussels_time(): void
    {
        $entry = TimeEntry::factory()->create([
            'started_at' => '2026-11-18 09:00:00',
            'ended_at' => '2026-11-18 10:00:00',
        ]);

        $entry->refresh();

        $this->assertSame('09:00', $entry->started_at->format('H:i'));
        $this->assertSame('10:00', $entry->localStartedAt()->format('H:i'));
        $this->assertSame('11:00', $entry->localEndedAt()->format('H:i'));
    }

    public function test_local_time_follows_daylight_saving_time(): void
    {
        $summer = TimeEntry::factory()->create([
            'started_at' => '2026-07-01 09:00:00',
            'ended_at' => '2026-07-01 10:00:00',
        ]);

        $this->assertSame('11:00', $summer->localStartedAt()->format('H:i'));
    }

    public function test_deleting_a_category_keeps_the_entries(): void
    {
        $category = Category::factory()->create();
        $entry = TimeEntry::factory()->create(['category_id' => $category->id]);

        $category->delete();

        $this->assertNull($entry->refresh()->category_id);
    }
}
