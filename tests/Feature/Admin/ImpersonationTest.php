<?php

namespace Tests\Feature\Admin;

use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Leerkracht']);
        $this->student = User::factory()->student()->create(['name' => 'Jan Peeters']);
    }

    public function test_admin_can_view_the_app_as_a_student_and_return(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.impersonate', $this->student))
            ->assertRedirect(route('entries.index'));

        $this->assertAuthenticatedAs($this->student);

        $this->get(route('entries.index'))
            ->assertOk()
            ->assertSee('Je bekijkt de app als Jan Peeters.')
            ->assertSee('Terug naar Leerkracht')
            ->assertDontSee('data-test="nav-admin-users"', false);

        $this->delete(route('impersonation.destroy'))
            ->assertRedirect(route('admin.users.index'));

        $this->assertAuthenticatedAs($this->admin);

        $this->get(route('entries.index'))->assertDontSee('data-test="impersonation-banner"', false);
    }

    public function test_students_and_admins_cannot_be_impersonated_wrongly(): void
    {
        $this->actingAs($this->student)
            ->post(route('admin.users.impersonate', User::factory()->student()->create()))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('admin.users.impersonate', User::factory()->admin()->create()))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('admin.users.impersonate', $this->admin))
            ->assertForbidden();
    }

    public function test_account_settings_are_blocked_while_impersonating(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.impersonate', $this->student));

        $this->get(route('profile.edit'))->assertForbidden();
        $this->get(route('security.edit'))->assertForbidden();
    }

    public function test_a_student_without_an_open_class_can_still_be_viewed(): void
    {
        $blocked = User::factory()
            ->student(SchoolClass::factory()->for(SchoolYear::factory()->closed())->create())
            ->create();

        $this->actingAs($this->admin)->post(route('admin.users.impersonate', $blocked));

        $this->get(route('reports.index'))->assertOk();
        $this->assertAuthenticatedAs($blocked);
    }

    public function test_impersonate_button_is_shown_to_admins(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertSee(route('admin.users.impersonate', $this->student), false)
            ->assertDontSee(route('admin.users.impersonate', $this->admin), false);
    }

    public function test_sidebar_shows_a_way_back_while_impersonating(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.impersonate', $this->student));

        $this->get(route('entries.index'))
            ->assertSee('data-test="sidebar-stop-impersonating"', false)
            ->assertDontSee('data-test="start-student-view"', false);
    }
}
