<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_my_hours(): void
    {
        $this->get(route('home'))->assertRedirect(route('entries.index'));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['entries.index', 'projects.index', 'reports.index'] as $routeName) {
            $this->get(route($routeName))->assertRedirect(route('login'));
        }
    }

    public function test_authenticated_user_can_visit_main_pages(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['entries.index', 'projects.index', 'reports.index'] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
    }
}
