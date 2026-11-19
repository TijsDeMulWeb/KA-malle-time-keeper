<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\LocalTime;
use App\Support\WeekView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeekViewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();
    }

    private function entry(string $start, ?string $end, array $attributes = []): TimeEntry
    {
        return TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'started_at' => $start,
            'ended_at' => $end,
        ] + $attributes);
    }

    private function week(string $monday): WeekView
    {
        return new WeekView($this->user, LocalTime::startOfWeek($monday));
    }

    public function test_block_sits_in_the_right_day_at_the_local_time(): void
    {
        $this->entry('2026-11-11 09:00:00', '2026-11-11 10:00:00');

        $days = $this->week('2026-11-09')->days();
        $wednesday = $days[2];

        $this->assertSame('2026-11-11', $wednesday['date']->format('Y-m-d'));
        $this->assertCount(1, $wednesday['blocks']);
        $this->assertSame(560.0, $wednesday['blocks'][0]['top']);
        $this->assertSame(56.0, $wednesday['blocks'][0]['height']);
        $this->assertSame('10:00', $wednesday['blocks'][0]['startLabel']);

        foreach ([0, 1, 3, 4, 5, 6] as $index) {
            $this->assertCount(0, $days[$index]['blocks']);
        }
    }

    public function test_late_evening_entry_stays_inside_the_grid(): void
    {
        $this->entry('2026-11-15 20:00:00', '2026-11-15 21:00:00');

        $block = $this->week('2026-11-09')->days()[6]['blocks'][0];

        $this->assertSame('21:00', $block['startLabel']);
        $this->assertSame(1176.0, $block['top']);
        $this->assertLessThanOrEqual(1344.0, $block['top'] + $block['height']);
    }

    public function test_entry_over_midnight_is_split_over_two_days(): void
    {
        $this->entry('2026-11-16 21:15:00', '2026-11-16 23:30:00');

        $days = $this->week('2026-11-16')->days();

        $monday = $days[0]['blocks'][0];
        $tuesday = $days[1]['blocks'][0];

        $this->assertSame(1344.0, $monday['top'] + $monday['height']);
        $this->assertTrue($monday['continuesToNext']);
        $this->assertSame(0.0, $tuesday['top']);
        $this->assertTrue($tuesday['continuesFromPrevious']);
        $this->assertSame(105, $monday['minutes']);
        $this->assertSame(30, $tuesday['minutes']);
        $this->assertSame(135, $this->week('2026-11-16')->totalMinutes());
    }

    public function test_entry_from_previous_week_over_midnight_shows_on_monday(): void
    {
        $this->entry('2026-11-15 22:00:00', '2026-11-15 23:30:00');

        $week = $this->week('2026-11-16');
        $monday = $week->days()[0];

        $this->assertSame(30, $monday['totalMinutes']);
        $this->assertSame(30, $week->totalMinutes());
    }

    public function test_day_and_week_totals(): void
    {
        $this->entry('2026-11-16 13:10:00', '2026-11-16 14:35:00');
        $this->entry('2026-11-16 14:35:00', '2026-11-16 15:00:00');
        $this->entry('2026-11-18 15:00:00', '2026-11-18 18:00:00');

        $week = $this->week('2026-11-16');

        $this->assertSame(110, $week->days()[0]['totalMinutes']);
        $this->assertSame(180, $week->days()[2]['totalMinutes']);
        $this->assertSame(290, $week->totalMinutes());
    }

    public function test_summer_time_is_shown_with_two_hours_offset(): void
    {
        $this->entry('2026-07-01 07:00:00', '2026-07-01 08:00:00');

        $block = $this->week('2026-06-29')->days()[2]['blocks'][0];

        $this->assertSame('09:00', $block['startLabel']);
        $this->assertSame(504.0, $block['top']);
    }

    public function test_week_with_the_switch_back_to_winter_time_keeps_wall_clock_positions(): void
    {
        $this->entry('2026-10-26 08:00:00', '2026-10-26 09:00:00');

        $block = $this->week('2026-10-26')->days()[0]['blocks'][0];

        $this->assertSame('09:00', $block['startLabel']);
    }

    public function test_entry_just_before_midnight_in_utc_belongs_to_the_next_local_day(): void
    {
        $this->entry('2026-11-17 23:30:00', '2026-11-17 23:50:00');

        $days = $this->week('2026-11-16')->days();

        $this->assertCount(0, $days[1]['blocks']);
        $this->assertCount(1, $days[2]['blocks']);
        $this->assertSame('00:30', $days[2]['blocks'][0]['startLabel']);
    }

    public function test_entries_of_other_users_are_not_shown(): void
    {
        TimeEntry::factory()->create([
            'started_at' => '2026-11-16 09:00:00',
            'ended_at' => '2026-11-16 10:00:00',
        ]);

        $this->assertSame(0, $this->week('2026-11-16')->totalMinutes());
    }

    public function test_short_entries_get_a_minimum_height(): void
    {
        $this->entry('2026-11-16 09:00:00', '2026-11-16 09:05:00');

        $this->assertSame(18, (int) $this->week('2026-11-16')->days()[0]['blocks'][0]['height']);
    }
}
