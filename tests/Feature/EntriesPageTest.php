<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EntriesPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('nl');

        $this->travelTo('2026-11-19 20:52:00');

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create(['name' => 'Game Launcher']);
        $this->actingAs($this->user);
    }

    private function entry(string $start, string $end, string $description, array $attributes = []): TimeEntry
    {
        return TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'description' => $description,
            'started_at' => $start,
            'ended_at' => $end,
        ] + $attributes);
    }

    public function test_calendar_shows_the_current_week_with_blocks_and_totals(): void
    {
        $this->entry('2026-11-16 13:10:00', '2026-11-16 14:35:00', 'SSO launcher gemaakt');

        Livewire::test('pages::entries.index')
            ->assertSee('Week 47')
            ->assertSee('16 – 22 november 2026')
            ->assertSee('SSO launcher gemaakt')
            ->assertSee('1u 25m')
            ->assertSeeHtml('data-test="now-line"');
    }

    public function test_week_can_be_navigated(): void
    {
        $this->entry('2026-11-10 09:00:00', '2026-11-10 10:00:00', 'Vorige week werk');

        Livewire::test('pages::entries.index')
            ->assertDontSee('Vorige week werk')
            ->call('previousWeek')
            ->assertSet('week', '2026-11-09')
            ->assertSee('Vorige week werk')
            ->assertDontSeeHtml('data-test="now-line"')
            ->call('nextWeek')
            ->assertSet('week', '2026-11-16')
            ->call('currentWeek')
            ->assertSet('week', '');
    }

    public function test_an_invalid_week_falls_back_to_the_current_week(): void
    {
        Livewire::withQueryParams(['week' => 'rommel'])
            ->test('pages::entries.index')
            ->assertSee('Week 47');
    }

    public function test_week_goal_progress_is_shown(): void
    {
        $this->entry('2026-11-16 09:00:00', '2026-11-16 19:00:00', 'Lange dag');
        $this->user->update(['weekly_goal_minutes' => 1200]);

        Livewire::test('pages::entries.index')
            ->assertSee('10u')
            ->assertSee('van 20u')
            ->assertSeeHtml('width: 50%');
    }

    public function test_list_view_groups_entries_by_day(): void
    {
        $this->entry('2026-11-16 13:10:00', '2026-11-16 14:35:00', 'SSO launcher gemaakt');
        $this->entry('2026-11-18 15:00:00', '2026-11-18 16:00:00', 'Set launcher live');

        Livewire::test('pages::entries.index')
            ->set('mode', 'list')
            ->assertSee('maandag 16 november')
            ->assertSee('woensdag 18 november')
            ->assertSee('SSO launcher gemaakt')
            ->assertSee('Set launcher live')
            ->assertSee('14:10 – 15:35');
    }

    public function test_list_view_shows_an_empty_state(): void
    {
        Livewire::test('pages::entries.index')
            ->set('mode', 'list')
            ->assertSee('Geen registraties in deze week');
    }

    public function test_every_hour_slot_opens_a_prefilled_entry(): void
    {
        Livewire::test('pages::entries.index')
            ->assertSeeHtml("open-entry', { date: '2026-11-18', start: '09:00', end: '10:00' }");
    }

    public function test_entries_of_other_users_are_never_shown(): void
    {
        TimeEntry::factory()->create([
            'description' => 'Geheim werk',
            'started_at' => '2026-11-16 09:00:00',
            'ended_at' => '2026-11-16 10:00:00',
        ]);

        Livewire::test('pages::entries.index')->assertDontSee('Geheim werk');
    }

    public function test_page_is_rendered_through_the_route_with_timer_and_modal(): void
    {
        $this->get(route('entries.index'))
            ->assertOk()
            ->assertSeeHtml('data-test="timer-bar"')
            ->assertSeeHtml('data-test="calendar"');
    }

    public function test_an_entry_can_be_moved_and_resized_with_the_drag_event(): void
    {
        $entry = $this->entry('2026-11-17 09:00:00', '2026-11-17 10:00:00', 'Verplaatsen');

        Livewire::test('pages::entries.index')
            ->call('moveEntry', entry: $entry->id, date: '2026-11-18', start: '14:30', end: '16:00')
            ->assertDispatched('entries-changed')
            ->assertNotDispatched('entry-move-rejected');

        $entry->refresh();

        $this->assertSame('2026-11-18 13:30:00', $entry->started_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-18 15:00:00', $entry->ended_at->format('Y-m-d H:i:s'));
    }

    public function test_moving_onto_another_entry_is_rejected(): void
    {
        $entry = $this->entry('2026-11-17 09:00:00', '2026-11-17 10:00:00', 'Eerste');
        $this->entry('2026-11-18 13:00:00', '2026-11-18 15:00:00', 'Tweede');

        Livewire::test('pages::entries.index')
            ->call('moveEntry', entry: $entry->id, date: '2026-11-18', start: '15:00', end: '16:00')
            ->assertDispatched('entry-move-rejected')
            ->assertNotDispatched('entries-changed');

        $this->assertSame('2026-11-17 09:00:00', $entry->refresh()->started_at->format('Y-m-d H:i:s'));
    }

    public function test_moving_with_invalid_times_is_rejected(): void
    {
        $entry = $this->entry('2026-11-17 09:00:00', '2026-11-17 10:00:00', 'Eerste');

        Livewire::test('pages::entries.index')
            ->call('moveEntry', entry: $entry->id, date: '2026-11-18', start: '16:00', end: '15:00')
            ->assertDispatched('entry-move-rejected');

        $this->assertSame('2026-11-17 09:00:00', $entry->refresh()->started_at->format('Y-m-d H:i:s'));
    }

    public function test_an_entry_can_be_stretched_until_midnight(): void
    {
        $entry = $this->entry('2026-11-17 19:00:00', '2026-11-17 20:00:00', 'Laat');

        Livewire::test('pages::entries.index')
            ->call('moveEntry', entry: $entry->id, date: '2026-11-17', start: '20:00', end: '24:00')
            ->assertNotDispatched('entry-move-rejected');

        $this->assertSame('2026-11-17 23:00:00', $entry->refresh()->ended_at->format('Y-m-d H:i:s'));
    }

    public function test_entries_of_other_users_cannot_be_moved(): void
    {
        $foreign = TimeEntry::factory()->create([
            'started_at' => '2026-11-17 09:00:00',
            'ended_at' => '2026-11-17 10:00:00',
        ]);

        Livewire::test('pages::entries.index')
            ->call('moveEntry', entry: $foreign->id, date: '2026-11-18', start: '14:30', end: '16:00');

        $this->assertSame('2026-11-17 09:00:00', $foreign->refresh()->started_at->format('Y-m-d H:i:s'));
    }

    public function test_calendar_markup_exposes_drag_data_and_running_entries_are_not_draggable(): void
    {
        $this->entry('2026-11-17 09:00:00', '2026-11-17 10:00:00', 'Sleepbaar');
        TimeEntry::factory()->running()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'description' => 'Lopend',
        ]);

        Livewire::test('pages::entries.index')
            ->assertSeeHtml('data-calendar-day="2026-11-17"')
            ->assertSeeHtml('data-draggable="true"')
            ->assertSeeHtml('data-draggable="false"')
            ->assertSeeHtml('data-start-minutes="600"')
            ->assertSeeHtml('data-end-minutes="660"');
    }

    public function test_shortcut_hooks_and_help_modal_are_present(): void
    {
        $this->get(route('entries.index'))
            ->assertOk()
            ->assertSeeHtml('data-shortcut="n"')
            ->assertSeeHtml('data-shortcut="s"')
            ->assertSeeHtml('data-shortcut="ArrowLeft"')
            ->assertSeeHtml('data-shortcut="ArrowRight"')
            ->assertSeeHtml('data-test="shortcuts-modal"');
    }

    public function test_half_hour_slots_open_prefilled_entries_and_the_last_slot_stops_before_midnight(): void
    {
        Livewire::test('pages::entries.index')
            ->assertSeeHtml("open-entry', { date: '2026-11-18', start: '09:30', end: '10:30' }")
            ->assertSeeHtml("open-entry', { date: '2026-11-18', start: '23:00', end: '23:59' }")
            ->assertSeeHtml("open-entry', { date: '2026-11-18', start: '23:30', end: '23:59' }");
    }
}
