<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();

        $this->post('/register', [
            'name' => 'Someone',
            'email' => 'someone@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }

    public function test_login_page_has_no_sign_up_link(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('Sign up');
    }

    public function test_user_can_log_in_without_verified_email(): void
    {
        $this->post(route('login.store'), [
            'email' => User::factory()->create()->email,
            'password' => 'password',
        ])->assertRedirect(route('entries.index'));
    }
}
