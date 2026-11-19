<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TimeSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_page_is_displayed_with_current_values(): void
    {
        $this->get(route('time-settings.edit'))->assertOk();

        Livewire::test('pages::settings.time')
            ->assertSet('periodStartsOn', '');
    }

    public function test_period_can_be_saved(): void
    {
        Livewire::test('pages::settings.time')
            ->set('periodStartsOn', '2026-09-21')
            ->set('periodEndsOn', '2027-01-24')
            ->call('save')
            ->assertHasNoErrors();

        $this->user->refresh();

        $this->assertSame('2026-09-21', $this->user->period_starts_on->format('Y-m-d'));
        $this->assertSame('2027-01-24', $this->user->period_ends_on->format('Y-m-d'));
    }

    public function test_period_can_be_cleared(): void
    {
        $this->user->update(['period_starts_on' => '2026-09-21']);

        Livewire::test('pages::settings.time')
            ->set('periodStartsOn', '')
            ->set('periodEndsOn', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($this->user->refresh()->period_starts_on);
    }

    public function test_end_must_be_after_start(): void
    {
        Livewire::test('pages::settings.time')
            ->set('periodStartsOn', '2026-11-01')
            ->set('periodEndsOn', '2026-10-01')
            ->call('save')
            ->assertHasErrors(['periodEndsOn']);
    }
}
