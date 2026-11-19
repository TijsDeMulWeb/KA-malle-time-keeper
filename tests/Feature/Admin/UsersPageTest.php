<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Mail\InvitationMail;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class UsersPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->admin = User::factory()->admin()->create(['name' => 'Leerkracht']);
    }

    public function test_only_admins_can_open_the_page(): void
    {
        $this->actingAs(User::factory()->student()->create())
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Gebruikers');
    }

    public function test_admin_nav_is_hidden_for_students(): void
    {
        $this->actingAs(User::factory()->student()->create())
            ->get(route('entries.index'))
            ->assertDontSee('data-test="nav-admin-users"', false);

        $this->actingAs($this->admin)
            ->get(route('entries.index'))
            ->assertSee('data-test="nav-admin-users"', false);
    }

    public function test_admin_can_invite_a_student(): void
    {
        Livewire::actingAs($this->admin)
            ->test('pages::admin.users.index')
            ->call('create')
            ->set('form.name', 'Jan Peeters')
            ->set('form.email', 'Jan.Peeters@School.be')
            ->call('save')
            ->assertHasNoErrors();

        $student = User::where('email', 'jan.peeters@school.be')->firstOrFail();

        $this->assertTrue($student->hasRole(Role::Student->value));
        $this->assertFalse($student->hasAcceptedInvitation());
        $this->assertNotNull($student->invited_at);

        Mail::assertSent(InvitationMail::class, fn (InvitationMail $mail) => $mail->hasTo('jan.peeters@school.be'));
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'jan@school.be']);

        Livewire::actingAs($this->admin)
            ->test('pages::admin.users.index')
            ->set('form.name', 'Jan')
            ->set('form.email', 'jan@school.be')
            ->call('save')
            ->assertHasErrors('form.email');

        Mail::assertNothingSent();
    }

    public function test_admin_can_invite_a_whole_class(): void
    {
        Livewire::actingAs($this->admin)
            ->test('pages::admin.users.index')
            ->set('bulkForm.lines', "Jan Peeters, jan@school.be\nan.janssens@school.be\n\nZoë Maes;zoe@school.be")
            ->call('saveMany')
            ->assertHasNoErrors();

        $this->assertSame('Jan Peeters', User::where('email', 'jan@school.be')->value('name'));
        $this->assertSame('An Janssens', User::where('email', 'an.janssens@school.be')->value('name'));
        $this->assertSame('Zoë Maes', User::where('email', 'zoe@school.be')->value('name'));

        Mail::assertSentCount(3);
    }

    public function test_bulk_invite_rejects_existing_and_invalid_addresses(): void
    {
        User::factory()->create(['email' => 'jan@school.be']);

        Livewire::actingAs($this->admin)
            ->test('pages::admin.users.index')
            ->set('bulkForm.lines', "jan@school.be\nan@school.be")
            ->call('saveMany')
            ->assertHasErrors('bulkForm.lines');

        Livewire::actingAs($this->admin)
            ->test('pages::admin.users.index')
            ->set('bulkForm.lines', 'Jan, geen-mailadres')
            ->call('saveMany')
            ->assertHasErrors('bulkForm.lines');

        $this->assertDatabaseMissing('users', ['email' => 'an@school.be']);
        Mail::assertNothingSent();
    }

    public function test_admin_can_resend_a_pending_invitation(): void
    {
        $student = User::factory()->invited()->student()->create(['invited_at' => now()->subDays(10)]);

        $this->assertTrue($student->invitationHasExpired());

        Livewire::actingAs($this->admin)
            ->test('pages::admin.users.index')
            ->assertSee('Uitnodiging verlopen')
            ->call('resendInvitation', $student->id);

        Mail::assertSent(InvitationMail::class);
        $this->assertFalse($student->fresh()->invitationHasExpired());
    }

    public function test_admin_can_change_role_but_not_their_own(): void
    {
        $student = User::factory()->student()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::admin.users.index')
            ->call('edit', $student->id)
            ->set('form.role', Role::Admin->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($student->fresh()->isAdmin());

        Livewire::actingAs($this->admin)
            ->test('pages::admin.users.index')
            ->call('edit', $this->admin->id)
            ->set('form.role', Role::Student->value)
            ->call('save');

        $this->assertTrue($this->admin->fresh()->isAdmin());
    }

    public function test_admin_can_delete_a_user_but_not_themselves(): void
    {
        $student = User::factory()->student()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::admin.users.index')
            ->call('confirmDelete', $student->id)
            ->call('delete');

        $this->assertModelMissing($student);

        Livewire::actingAs($this->admin)
            ->test('pages::admin.users.index')
            ->call('confirmDelete', $this->admin->id)
            ->assertForbidden();
    }

    public function test_list_shows_hours_and_can_be_filtered(): void
    {
        $this->travelTo('2026-11-19 12:00:00');

        $student = User::factory()->student()->create(['name' => 'Jan Peeters', 'email' => 'jan@school.be']);
        User::factory()->student()->create(['name' => 'An Janssens', 'email' => 'an@school.be']);

        TimeEntry::factory()->create([
            'user_id' => $student->id,
            'project_id' => Project::factory()->for($student),
            'started_at' => '2026-11-18 09:00:00',
            'ended_at' => '2026-11-18 11:30:00',
        ]);

        Livewire::actingAs($this->admin)
            ->test('pages::admin.users.index')
            ->assertSee('2u 30m')
            ->set('search', 'peet')
            ->assertSee('Jan Peeters')
            ->assertDontSee('An Janssens')
            ->set('search', '')
            ->set('role', Role::Admin->value)
            ->assertSee('Leerkracht')
            ->assertDontSee('Jan Peeters');
    }

    public function test_students_cannot_call_admin_actions(): void
    {
        $student = User::factory()->student()->create();

        Livewire::actingAs($student)
            ->test('pages::admin.users.index')
            ->call('create')
            ->assertForbidden();
    }
}
