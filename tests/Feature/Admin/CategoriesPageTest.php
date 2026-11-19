<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoriesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_the_page(): void
    {
        $this->actingAs(User::factory()->student()->create())
            ->get(route('admin.categories.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.categories.index'))
            ->assertOk();
    }

    public function test_admin_can_add_rename_and_delete_categories(): void
    {
        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.categories.index')
            ->set('name', 'Presentatie')
            ->call('store')
            ->assertHasNoErrors()
            ->set('name', 'Presentatie')
            ->call('store')
            ->assertHasErrors('name');

        $category = Category::where('name', 'Presentatie')->firstOrFail();

        $component
            ->call('edit', $category->id)
            ->set('editingName', 'Pitch')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertSame('Pitch', $category->fresh()->name);

        $entry = TimeEntry::factory()->create(['category_id' => $category->id]);

        $component->call('delete', $category->id);

        $this->assertModelMissing($category);
        $this->assertNull($entry->fresh()->category_id);
    }

    public function test_students_cannot_manage_categories(): void
    {
        Livewire::actingAs(User::factory()->student()->create())
            ->test('pages::admin.categories.index')
            ->set('name', 'Hack')
            ->call('store')
            ->assertForbidden();
    }
}
