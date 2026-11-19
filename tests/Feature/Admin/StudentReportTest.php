<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentReportTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-19 12:00:00');

        $this->student = User::factory()->student()->create(['name' => 'Jan Peeters']);

        TimeEntry::factory()->create([
            'user_id' => $this->student->id,
            'project_id' => Project::factory()->for($this->student)->create(['name' => 'Eindwerk']),
            'description' => 'Wireframes',
            'started_at' => '2026-11-18 09:00:00',
            'ended_at' => '2026-11-18 11:00:00',
        ]);
    }

    public function test_admin_sees_the_report_of_a_student(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.show', ['user' => $this->student, 'periode' => 'week']))
            ->assertOk()
            ->assertSee('Jan Peeters')
            ->assertSee('Eindwerk')
            ->assertSee(route('admin.users.reports.export.csv', ['user' => $this->student, 'periode' => 'week']), false);
    }

    public function test_admin_can_export_a_student_report(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.reports.export.csv', ['user' => $this->student, 'periode' => 'week']))
            ->assertOk()
            ->assertDownload('uren-jan-peeters-2026-11-16-2026-11-22.csv');

        $this->assertStringContainsString('Wireframes', $response->streamedContent());
    }

    public function test_students_cannot_see_each_others_reports(): void
    {
        $this->actingAs(User::factory()->student()->create())
            ->get(route('admin.users.show', $this->student))
            ->assertForbidden();

        $this->actingAs(User::factory()->student()->create())
            ->get(route('admin.users.reports.export.csv', $this->student))
            ->assertForbidden();
    }

    public function test_own_report_still_works_for_students(): void
    {
        $this->actingAs($this->student)
            ->get(route('reports.index', ['periode' => 'week']))
            ->assertOk()
            ->assertSee('Eindwerk')
            ->assertDontSee('data-test="report-subject"', false);
    }
}
