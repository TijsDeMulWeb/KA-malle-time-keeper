<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_in_an_open_class_can_log_in(): void
    {
        $student = User::factory()->student()->create();

        $this->post(route('login.store'), ['email' => $student->email, 'password' => 'password'])
            ->assertRedirect(route('entries.index'));

        $this->assertAuthenticatedAs($student);
    }

    public function test_student_without_a_class_in_an_open_year_cannot_log_in(): void
    {
        $schoolClass = SchoolClass::factory()->for(SchoolYear::factory()->closed())->create();
        $student = User::factory()->student($schoolClass)->create();

        $this->post(route('login.store'), ['email' => $student->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logged_in_student_is_logged_out_when_the_year_closes(): void
    {
        $schoolClass = SchoolClass::factory()->create();
        $student = User::factory()->student($schoolClass)->create();

        $this->actingAs($student)->get(route('entries.index'))->assertOk();

        $schoolClass->schoolYear->update(['closed_at' => now()]);

        $this->get(route('entries.index'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_admins_never_need_a_class(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('entries.index'))
            ->assertOk();
    }
}
