<?php

namespace Tests\Feature;

use App\Mail\InvitationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private function invitationUrl(User $user, string $route = 'invitations.show'): string
    {
        return URL::temporarySignedRoute($route, now()->addDays(7), ['user' => $user]);
    }

    public function test_invited_user_sees_the_set_password_form(): void
    {
        $user = User::factory()->invited()->create(['name' => 'Jan Peeters']);

        $this->get($this->invitationUrl($user))
            ->assertOk()
            ->assertSee('Welkom, Jan Peeters')
            ->assertSee($user->email);
    }

    public function test_unsigned_or_expired_links_are_rejected(): void
    {
        $user = User::factory()->invited()->create();

        $this->get(route('invitations.show', $user))->assertForbidden();

        $expired = URL::temporarySignedRoute('invitations.show', now()->subMinute(), ['user' => $user]);

        $this->get($expired)->assertForbidden();
    }

    public function test_accepting_sets_the_password_and_logs_in(): void
    {
        $user = User::factory()->invited()->create();

        $this->put($this->invitationUrl($user, 'invitations.update'), [
            'password' => 'geheim-wachtwoord',
            'password_confirmation' => 'geheim-wachtwoord',
        ])->assertRedirect(route('entries.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->hasAcceptedInvitation());
    }

    public function test_passwords_must_match(): void
    {
        $user = User::factory()->invited()->create();

        $this->put($this->invitationUrl($user, 'invitations.update'), [
            'password' => 'geheim-wachtwoord',
            'password_confirmation' => 'iets-anders',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_an_accepted_invitation_cannot_be_used_again(): void
    {
        $user = User::factory()->create();

        $this->get($this->invitationUrl($user))->assertRedirect(route('login'));

        $this->put($this->invitationUrl($user, 'invitations.update'), [
            'password' => 'nieuw-wachtwoord',
            'password_confirmation' => 'nieuw-wachtwoord',
        ])->assertForbidden();
    }

    public function test_invited_user_cannot_log_in_before_accepting(): void
    {
        $user = User::factory()->invited()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => ''])
            ->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_mail_contains_a_signed_link(): void
    {
        $user = User::factory()->invited()->create(['name' => 'Jan Peeters']);

        $mail = new InvitationMail($user, User::factory()->admin()->create(['name' => 'Els Peeters']));

        $mail->assertSeeInHtml('Hallo Jan Peeters');
        $mail->assertSeeInHtml('signature=');
        $mail->assertSeeInHtml('Els Peeters');
        $mail->assertHasSubject('Je bent uitgenodigd voor '.config('app.name'));
    }
}
