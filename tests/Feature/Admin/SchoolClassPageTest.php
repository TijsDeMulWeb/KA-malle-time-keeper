<?php

namespace Tests\Feature\Admin;

use App\Mail\InvitationMail;
use App\Models\Project;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class SchoolClassPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private SchoolClass $schoolClass;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->admin = User::factory()->admin()->create();
        $this->schoolClass = SchoolClass::factory()->for(SchoolYear::factory()->starting(2026))->create(['name' => '6A']);
    }

    public function test_page_renders_for_admins_only(): void
    {
        User::factory()->student($this->schoolClass)->create(['name' => 'Jan Peeters']);

        $this->actingAs($this->admin)
            ->get(route('admin.school-classes.show', $this->schoolClass))
            ->assertOk()
            ->assertSee('Klas 6A')
            ->assertSee('Jan Peeters');

        $this->actingAs(User::factory()->student($this->schoolClass)->create())
            ->get(route('admin.school-classes.show', $this->schoolClass))
            ->assertForbidden();
    }

    public function test_personal_projects_of_students_are_listed(): void
    {
        $this->travelTo('2026-11-19 12:00:00');

        $student = User::factory()->student($this->schoolClass)->create(['name' => 'Jan Peeters']);
        $project = Project::factory()->for($student)->create(['name' => 'Portfolio']);
        Project::factory()->create(['name' => 'Van iemand anders']);

        TimeEntry::factory()->create([
            'user_id' => $student->id,
            'project_id' => $project->id,
            'started_at' => '2026-11-18 09:00:00',
            'ended_at' => '2026-11-18 11:00:00',
        ]);

        Livewire::actingAs($this->admin)
            ->test('pages::admin.school-classes.show', ['schoolClass' => $this->schoolClass])
            ->assertSee('Portfolio')
            ->assertSee('Jan Peeters')
            ->assertSee('2u')
            ->assertDontSee('Van iemand anders');
    }

    public function test_admin_invites_students_into_the_class(): void
    {
        Livewire::actingAs($this->admin)
            ->test('pages::admin.school-classes.show', ['schoolClass' => $this->schoolClass])
            ->call('openInviteForm')
            ->set('bulkForm.lines', "Jan Peeters, jan@school.be\nan@school.be")
            ->call('invite')
            ->assertHasNoErrors();

        $this->assertSame(2, $this->schoolClass->students()->count());
        Mail::assertSent(InvitationMail::class, 2);
    }

    public function test_adding_an_existing_student_moves_them_within_the_same_year(): void
    {
        $otherClass = SchoolClass::factory()->for($this->schoolClass->schoolYear)->create();
        $student = User::factory()->student($otherClass)->create();

        Livewire::actingAs($this->admin)
            ->test('pages::admin.school-classes.show', ['schoolClass' => $this->schoolClass])
            ->call('openAddExisting')
            ->set('selectedStudentIds', [(string) $student->id])
            ->call('addExisting');

        $this->assertTrue($student->schoolClasses->contains($this->schoolClass));
        $this->assertFalse($student->fresh()->schoolClasses->contains($otherClass));
    }

    public function test_students_can_be_transferred_to_a_class_in_another_year(): void
    {
        $students = User::factory()->count(3)->student($this->schoolClass)->create();
        $nextClass = SchoolClass::factory()->for(SchoolYear::factory()->starting(2027))->create(['name' => '7A']);

        Livewire::actingAs($this->admin)
            ->test('pages::admin.school-classes.show', ['schoolClass' => $this->schoolClass])
            ->call('openTransfer')
            ->assertSet('targetClassId', (string) $nextClass->id)
            ->call('transfer');

        $this->assertSame(3, $nextClass->students()->count());
        $this->assertSame(3, $this->schoolClass->students()->count());
        $this->assertTrue($students->first()->schoolClasses()->whereKey($this->schoolClass->id)->exists());
    }

    public function test_removing_a_student_keeps_the_account(): void
    {
        $student = User::factory()->student($this->schoolClass)->create();

        Livewire::actingAs($this->admin)
            ->test('pages::admin.school-classes.show', ['schoolClass' => $this->schoolClass])
            ->call('removeStudent', $student->id);

        $this->assertModelExists($student);
        $this->assertSame(0, $this->schoolClass->students()->count());
    }

    public function test_closed_classes_are_read_only(): void
    {
        $this->schoolClass->schoolYear->update(['closed_at' => now()]);

        Livewire::actingAs($this->admin)
            ->test('pages::admin.school-classes.show', ['schoolClass' => $this->schoolClass->fresh()])
            ->assertDontSee('data-test="invite-students"', false)
            ->call('openInviteForm')
            ->assertForbidden();
    }

    public function test_users_page_invites_into_a_class(): void
    {
        Livewire::actingAs($this->admin)
            ->test('pages::admin.users.index')
            ->call('create')
            ->assertSet('form.schoolClassId', (string) $this->schoolClass->id)
            ->set('form.name', 'Jan')
            ->set('form.email', 'jan@school.be')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('6A (2026-2027)');

        $this->assertTrue(User::where('email', 'jan@school.be')->firstOrFail()->hasAccess());
    }
}
