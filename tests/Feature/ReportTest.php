<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Report;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $launcher;

    private Project $fablab;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('nl');
        $this->travelTo('2026-11-19 20:52:00');

        $this->user = User::factory()->create([
            'period_starts_on' => '2026-09-21',
            'period_ends_on' => '2027-01-24',
        ]);
        $this->launcher = Project::factory()->for($this->user)->create(['name' => 'Game Launcher', 'color' => '#14b8a6']);
        $this->fablab = Project::factory()->for($this->user)->create(['name' => 'Fablab Opvolging', 'client' => 'Ivan', 'color' => '#6b7280']);
        $this->actingAs($this->user);
    }

    private function entry(Project $project, string $start, string $end, array $attributes = []): TimeEntry
    {
        return TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'started_at' => $start,
            'ended_at' => $end,
        ] + $attributes);
    }

    private function seedData(): void
    {
        $development = Category::factory()->create(['name' => 'Development']);
        $testing = Category::factory()->create(['name' => 'Testing']);

        $this->entry($this->launcher, '2026-11-10 09:00:00', '2026-11-10 12:00:00', ['category_id' => $development->id, 'is_live' => true]);
        $this->entry($this->launcher, '2026-11-17 09:00:00', '2026-11-17 10:30:00', ['category_id' => $testing->id]);
        $this->entry($this->fablab, '2026-11-19 08:00:00', '2026-11-19 09:30:00');
    }

    public function test_period_for_the_whole_course_runs_until_the_end_of_this_week(): void
    {
        $period = ReportPeriod::resolve($this->user, 'course');

        $this->assertSame('21 september 2026 tot en met 22 november 2026', $period->label());
        $this->assertCount(9, $period->weekStarts());
    }

    public function test_period_filters(): void
    {
        $week = ReportPeriod::resolve($this->user, 'week');
        $lastWeek = ReportPeriod::resolve($this->user, 'last-week');
        $month = ReportPeriod::resolve($this->user, 'month');

        $this->assertSame('16 november 2026 tot en met 22 november 2026', $week->label());
        $this->assertSame('9 november 2026 tot en met 15 november 2026', $lastWeek->label());
        $this->assertSame('1 november 2026 tot en met 30 november 2026', $month->label());
    }

    public function test_custom_period_and_invalid_input_fallback(): void
    {
        $custom = ReportPeriod::resolve($this->user, 'custom', '2026-11-01', '2026-11-05');
        $invalid = ReportPeriod::resolve($this->user, 'custom', 'x', 'y');
        $reversed = ReportPeriod::resolve($this->user, 'custom', '2026-11-05', '2026-11-01');

        $this->assertSame('1 november 2026 tot en met 5 november 2026', $custom->label());
        $this->assertSame('16 november 2026 tot en met 22 november 2026', $invalid->label());
        $this->assertSame('16 november 2026 tot en met 22 november 2026', $reversed->label());
    }

    public function test_course_without_configured_period_starts_at_the_first_entry(): void
    {
        $this->user->update(['period_starts_on' => null, 'period_ends_on' => null]);
        $this->entry($this->launcher, '2026-11-03 09:00:00', '2026-11-03 10:00:00');

        $period = ReportPeriod::resolve($this->user->refresh(), 'course');

        $this->assertSame('3 november 2026 tot en met 22 november 2026', $period->label());
    }

    public function test_totals_and_average(): void
    {
        $this->seedData();

        $report = new Report($this->user, ReportPeriod::resolve($this->user, 'course'));

        $this->assertSame(360, $report->totalMinutes());
        $this->assertSame(3, $report->count());
        $this->assertSame(40, $report->averageMinutesPerWeek());
        $this->assertSame(360, $report->courseMinutes());
    }

    public function test_weeks_are_stacked_per_project(): void
    {
        $this->seedData();

        $weeks = (new Report($this->user, ReportPeriod::resolve($this->user, 'course')))->weeks();

        $week46 = collect($weeks)->firstWhere('label', 'wk 46');
        $week47 = collect($weeks)->firstWhere('label', 'wk 47');

        $this->assertSame(180, $week46['total']);
        $this->assertSame(1, count($week46['segments']));
        $this->assertSame(180, $week47['total']);
        $this->assertSame(['Fablab Opvolging', 'Game Launcher'], collect($week47['segments'])->pluck('project.name')->all());
        $this->assertSame(0, collect($weeks)->firstWhere('label', 'wk 39')['total']);
    }

    public function test_axis_gets_a_nice_maximum(): void
    {
        $this->seedData();

        $axis = (new Report($this->user, ReportPeriod::resolve($this->user, 'course')))->axis();

        $this->assertSame(3, $axis['max']);
        $this->assertSame([0, 1, 2, 3], $axis['ticks']);
    }

    public function test_split_per_project_and_category(): void
    {
        $this->seedData();

        $report = new Report($this->user, ReportPeriod::resolve($this->user, 'course'));

        $projects = $report->perProject();
        $categories = $report->perCategory();

        $this->assertSame('Game Launcher', $projects[0]['project']->name);
        $this->assertSame(270, $projects[0]['minutes']);
        $this->assertSame(75, $projects[0]['percentage']);
        $this->assertSame('Development', $categories[0]['name']);
        $this->assertContains('Zonder categorie', collect($categories)->pluck('name')->all());
    }

    public function test_only_entries_in_the_period_are_counted(): void
    {
        $this->seedData();

        $report = new Report($this->user, ReportPeriod::resolve($this->user, 'last-week'));

        $this->assertSame(180, $report->totalMinutes());
        $this->assertSame(1, $report->count());
    }

    public function test_entries_of_other_users_are_ignored(): void
    {
        TimeEntry::factory()->create([
            'started_at' => '2026-11-17 09:00:00',
            'ended_at' => '2026-11-17 12:00:00',
        ]);

        $report = new Report($this->user, ReportPeriod::resolve($this->user, 'course'));

        $this->assertSame(0, $report->totalMinutes());
    }

    public function test_an_empty_report_does_not_break(): void
    {
        $report = new Report($this->user, ReportPeriod::resolve($this->user, 'course'));

        $this->assertSame(1, $report->axis()['max']);
        $this->assertSame([], $report->perProject());
    }

    public function test_report_page_shows_tiles_and_splits(): void
    {
        $this->seedData();

        $this->get(route('reports.index'))
            ->assertOk()
            ->assertSee('6u')
            ->assertSee('3 registraties')
            ->assertSee('Gemiddeld per week')
            ->assertSee('Per project')
            ->assertSee('Development')
            ->assertSee('Zonder categorie')
            ->assertSee('wk 47');
    }

    public function test_report_page_filter_changes_the_period(): void
    {
        $this->seedData();

        Livewire::test('pages::reports.index')
            ->call('choose', 'week')
            ->assertSee('16 november 2026 tot en met 22 november 2026')
            ->assertSee('2 registraties')
            ->call('choose', 'custom')
            ->set('from', '2026-11-01')
            ->set('until', '2026-11-11')
            ->assertSee('1 november 2026 tot en met 11 november 2026')
            ->assertSee('1 registraties');
    }

    public function test_unknown_filter_is_ignored(): void
    {
        Livewire::test('pages::reports.index')
            ->call('choose', 'hack')
            ->assertSet('filter', 'course');
    }

    public function test_csv_export_contains_the_entries_in_local_time(): void
    {
        $this->seedData();

        $response = $this->get(route('reports.export.csv', ['periode' => 'course']));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('Datum', $content);
        $this->assertStringContainsString('2026-11-10', $content);
        $this->assertStringContainsString('10:00', $content);
        $this->assertStringContainsString('Game Launcher', $content);
        $this->assertStringContainsString('Fablab Opvolging', $content);
        $this->assertStringContainsString('Ivan', $content);
        $this->assertStringContainsString('Development', $content);
    }

    public function test_csv_export_respects_the_period_and_other_users(): void
    {
        $this->seedData();

        TimeEntry::factory()->create([
            'description' => 'Geheim',
            'started_at' => '2026-11-18 09:00:00',
            'ended_at' => '2026-11-18 10:00:00',
        ]);

        $content = $this->get(route('reports.export.csv', ['periode' => 'week']))->streamedContent();

        $this->assertStringContainsString('2026-11-19', $content);
        $this->assertStringNotContainsString('2026-11-10', $content);
        $this->assertStringNotContainsString('Geheim', $content);
    }

    public function test_pdf_export_returns_a_pdf(): void
    {
        $this->seedData();

        $response = $this->get(route('reports.export.pdf', ['periode' => 'course']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_exports_validate_input_and_require_login(): void
    {
        $this->get(route('reports.export.csv', ['periode' => 'rommel']))->assertSessionHasErrors('periode');

        auth()->logout();
        $this->get(route('reports.export.csv'))->assertRedirect(route('login'));
        $this->get(route('reports.export.pdf'))->assertRedirect(route('login'));
    }
}
