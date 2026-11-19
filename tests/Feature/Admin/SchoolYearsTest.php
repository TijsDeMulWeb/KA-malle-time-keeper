<?php

namespace Tests\Feature\Admin;

use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SchoolYearsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-19 12:00:00');

        $this->admin = User::factory()->admin()->create();
    }

    public function test_only_admins_can_manage_school_years(): void
    {
        $schoolYear = SchoolYear::factory()->starting(2026)->create();

        $this->actingAs(User::factory()->student()->create())
            ->get(route('admin.school-years.index'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('admin.school-years.index'))
            ->assertOk()
            ->assertSee('2026-2027');

        $this->actingAs($this->admin)
            ->get(route('admin.school-years.show', $schoolYear))
            ->assertOk();
    }

    public function test_new_school_year_is_suggested_after_the_latest(): void
    {
        SchoolYear::factory()->starting(2026)->create();

        Livewire::actingAs($this->admin)
            ->test('pages::admin.school-years.index')
            ->call('create')
            ->assertSet('form.name', '2027-2028')
            ->assertSet('form.startsOn', '2027-09-01')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('school_years', ['name' => '2027-2028']);
    }

    public function test_school_years_cannot_overlap(): void
    {
        SchoolYear::factory()->starting(2026)->create();

        Livewire::actingAs($this->admin)
            ->test('pages::admin.school-years.index')
            ->call('create')
            ->set('form.startsOn', '2027-06-01')
            ->call('save')
            ->assertHasErrors('form.startsOn');
    }

    public function test_admin_adds_and_renames_classes(): void
    {
        $schoolYear = SchoolYear::factory()->starting(2026)->create();

        $component = Livewire::actingAs($this->admin)
            ->test('pages::admin.school-years.show', ['schoolYear' => $schoolYear])
            ->set('name', '6A')
            ->call('store')
            ->assertHasNoErrors()
            ->set('name', '6A')
            ->call('store')
            ->assertHasErrors('name');

        $schoolClass = $schoolYear->schoolClasses()->firstOrFail();

        $component
            ->call('edit', $schoolClass->id)
            ->set('editingName', '6B')
            ->call('update');

        $this->assertSame('6B', $schoolClass->fresh()->name);
    }

    public function test_classes_with_students_cannot_be_deleted(): void
    {
        $schoolClass = SchoolClass::factory()->create();
        User::factory()->student($schoolClass)->create();

        Livewire::actingAs($this->admin)
            ->test('pages::admin.school-years.show', ['schoolYear' => $schoolClass->schoolYear])
            ->call('delete', $schoolClass->id)
            ->assertForbidden();
    }

    public function test_closing_a_year_keeps_data_but_locks_entries(): void
    {
        $schoolYear = SchoolYear::factory()->starting(2025)->create();
        $schoolClass = SchoolClass::factory()->for($schoolYear)->create();
        $student = User::factory()->student($schoolClass)->create();
        User::factory()->student(SchoolClass::factory()->for(SchoolYear::factory()->starting(2026))->create())->create();

        $entry = TimeEntry::factory()->create([
            'user_id' => $student->id,
            'started_at' => '2026-03-10 09:00:00',
            'ended_at' => '2026-03-10 10:00:00',
        ]);

        Livewire::actingAs($this->admin)
            ->test('pages::admin.school-years.show', ['schoolYear' => $schoolYear])
            ->assertSet('studentsLosingAccess', 1)
            ->call('close');

        $this->assertTrue($schoolYear->fresh()->isClosed());
        $this->assertModelExists($entry);
        $this->assertTrue($entry->fresh()->isLocked());
        $this->assertFalse($student->can('update', $entry->fresh()));
        $this->assertFalse($student->can('delete', $entry->fresh()));

        Livewire::actingAs($this->admin)
            ->test('pages::admin.school-years.show', ['schoolYear' => $schoolYear])
            ->set('name', '6Z')
            ->call('store');

        $this->assertDatabaseMissing('school_classes', ['name' => '6Z']);
    }

    public function test_reopening_unlocks_entries(): void
    {
        $schoolYear = SchoolYear::factory()->starting(2025)->closed()->create();
        $entry = TimeEntry::factory()->create(['started_at' => '2026-03-10 09:00:00', 'ended_at' => '2026-03-10 10:00:00']);

        $this->assertTrue($entry->isLocked());

        Livewire::actingAs($this->admin)
            ->test('pages::admin.school-years.show', ['schoolYear' => $schoolYear])
            ->call('reopen');

        $this->assertFalse($entry->fresh()->isLocked());
    }
}
