<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentViewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_switches_to_student_view_and_back(): void
    {
        $this->actingAs($this->admin)
            ->get(route('entries.index'))
            ->assertSee('data-test="start-student-view"', false)
            ->assertSee('data-test="nav-admin-users"', false);

        $this->post(route('student-view.store'))->assertRedirect(route('entries.index'));

        $this->assertAuthenticatedAs($this->admin);

        $this->get(route('entries.index'))
            ->assertOk()
            ->assertSee('data-test="student-view-banner"', false)
            ->assertSee('data-test="stop-student-view"', false)
            ->assertDontSee('data-test="nav-admin-users"', false)
            ->assertDontSee('data-test="nav-admin-school-years"', false);

        $this->get(route('admin.users.index'))->assertForbidden();

        $this->delete(route('student-view.destroy'))->assertRedirect();

        $this->get(route('entries.index'))
            ->assertDontSee('data-test="student-view-banner"', false)
            ->assertSee('data-test="nav-admin-users"', false);

        $this->get(route('admin.users.index'))->assertOk();
    }

    public function test_students_cannot_start_student_view(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('entries.index'))
            ->assertDontSee('data-test="start-student-view"', false);

        $this->post(route('student-view.store'))->assertForbidden();
    }

    public function test_student_view_only_affects_the_current_admin(): void
    {
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($this->admin)->post(route('student-view.store'));

        $this->assertFalse($this->admin->can('viewAny', User::class));
        $this->assertTrue($otherAdmin->hasPermissionTo('manage users'));
    }
}
