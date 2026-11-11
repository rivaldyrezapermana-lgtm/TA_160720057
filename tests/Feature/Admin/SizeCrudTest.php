<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The sizes migration already inserts S, M, L, XL with sort_order 10..40. */
class SizeCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Uji', 'email' => 'admin.uji@labasa.test',
            'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function product(array $sizes = []): Product
    {
        $product = Product::create([
            'category_id' => Category::create(['name' => 'Gamis'])->id,
            'name' => 'Gamis Uji', 'sku' => 'SKU-UJI', 'price' => 1000, 'stock' => 1, 'is_active' => true,
        ]);

        foreach ($sizes as $size) {
            ProductSize::create(['product_id' => $product->id, 'size' => $size, 'stock' => 1]);
        }

        return $product;
    }

    private function masterSize(string $name): Size
    {
        return Size::where('name', $name)->firstOrFail();
    }

    public function test_index_and_data_list_sizes_in_order(): void
    {
        $admin = $this->admin();
        $this->product(['M']);

        $this->actingAs($admin)->get(route('admin.sizes.index'))->assertOk();

        $response = $this->actingAs($admin)->getJson(route('admin.datatables.sizes'))->assertOk();
        $this->assertSame(['S', 'M', 'L', 'XL'], array_column($response->json('data'), 'name'));
        $this->assertSame([false, true, false, false], array_column($response->json('data'), 'used'));
    }

    public function test_create_and_edit_forms_load(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.sizes.create'))->assertOk();

        $this->actingAs($admin)->get(route('admin.sizes.edit', $this->masterSize('M')))
            ->assertOk()
            ->assertSee('value="M"', false)
            ->assertDontSee('readonly', false);
    }

    public function test_edit_form_locks_the_name_of_a_used_size(): void
    {
        $this->product(['M']);

        $this->actingAs($this->admin())->get(route('admin.sizes.edit', $this->masterSize('M')))
            ->assertOk()
            ->assertSee('readonly', false);
    }

    public function test_pembeli_cannot_manage_sizes(): void
    {
        $pembeli = User::create([
            'name' => 'Pembeli', 'email' => 'pembeli.uji@labasa.test',
            'password' => 'password', 'role' => User::ROLE_PEMBELI,
        ]);

        $this->actingAs($pembeli)->get(route('admin.sizes.index'))->assertForbidden();
    }

    public function test_store_trims_the_name_and_defaults_sort_order_to_last_plus_ten(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.sizes.store'), ['name' => '  XXL ', 'sort_order' => ''])
            ->assertRedirectToRoute('admin.sizes.index')
            ->assertSessionHas('success', 'Ukuran berhasil ditambahkan.');

        $this->assertDatabaseHas('sizes', ['name' => 'XXL', 'sort_order' => 50]);
    }

    public function test_store_keeps_a_given_sort_order(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.sizes.store'), ['name' => 'XS', 'sort_order' => 5])
            ->assertSessionHasNoErrors();

        $this->assertSame('XS', Size::ordered()->first()->name);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.sizes.store'), ['name' => '   '])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('admin.sizes.store'), ['name' => 'ABCDEFGHIJK'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('admin.sizes.store'), ['name' => 'X]L'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('admin.sizes.store'), ['name' => ['XXL']])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('admin.sizes.store'), ['name' => 'XXL', 'sort_order' => -1])->assertSessionHasErrors('sort_order');

        $this->assertSame(4, Size::count());
    }

    public function test_duplicate_name_is_rejected_ignoring_case(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.sizes.store'), ['name' => 'XL'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('admin.sizes.store'), ['name' => 'xl'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->put(route('admin.sizes.update', $this->masterSize('M')), ['name' => 'xl'])->assertSessionHasErrors('name');

        $this->assertSame(4, Size::count());
        $this->assertDatabaseHas('sizes', ['name' => 'M']);
    }

    public function test_unused_size_can_be_renamed_and_reordered(): void
    {
        $size = $this->masterSize('M');

        $this->actingAs($this->admin())
            ->put(route('admin.sizes.update', $size), ['name' => 'Medium', 'sort_order' => 99])
            ->assertRedirectToRoute('admin.sizes.index')
            ->assertSessionHas('success', 'Ukuran berhasil diperbarui.');

        $this->assertDatabaseHas('sizes', ['id' => $size->id, 'name' => 'Medium', 'sort_order' => 99]);
    }

    public function test_update_with_blank_sort_order_keeps_the_current_one(): void
    {
        $size = $this->masterSize('M');

        $this->actingAs($this->admin())
            ->put(route('admin.sizes.update', $size), ['name' => 'M', 'sort_order' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame(20, $size->fresh()->sort_order);
    }

    public function test_renaming_a_used_size_is_rejected(): void
    {
        $this->product(['M']);
        $size = $this->masterSize('M');
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.sizes.update', $size), ['name' => 'Medium'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->put(route('admin.sizes.update', $size), ['name' => 'm'])->assertSessionHasErrors('name');

        $this->assertSame('M', $size->fresh()->name);
    }

    public function test_used_size_can_still_be_reordered(): void
    {
        $this->product(['M']);
        $size = $this->masterSize('M');

        $this->actingAs($this->admin())
            ->put(route('admin.sizes.update', $size), ['name' => 'M', 'sort_order' => 99])
            ->assertSessionHasNoErrors();

        $this->assertSame(99, $size->fresh()->sort_order);
    }

    public function test_unused_size_can_be_deleted(): void
    {
        $size = $this->masterSize('XL');

        $this->actingAs($this->admin())
            ->delete(route('admin.sizes.destroy', $size))
            ->assertRedirectToRoute('admin.sizes.index')
            ->assertSessionHas('success', 'Ukuran berhasil dihapus.');

        $this->assertDatabaseMissing('sizes', ['id' => $size->id]);
    }

    public function test_deleting_a_used_size_is_rejected(): void
    {
        $this->product(['M']);
        $size = $this->masterSize('M');

        $this->actingAs($this->admin())
            ->delete(route('admin.sizes.destroy', $size))
            ->assertRedirectToRoute('admin.sizes.index')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('sizes', ['id' => $size->id]);
    }

    public function test_new_master_size_appears_on_product_create_form_in_order(): void
    {
        Size::create(['name' => 'XS', 'sort_order' => 5]);
        Size::create(['name' => '3XL', 'sort_order' => 100]);

        $this->actingAs($this->admin())->get(route('admin.products.create'))
            ->assertOk()
            ->assertSeeInOrder(['sizes[XS][size]', 'sizes[S][size]', 'sizes[XL][size]', 'sizes[3XL][size]'], false)
            ->assertDontSee('S/M/L/XL');
    }

    public function test_product_edit_form_shows_master_sizes_and_rows_outside_the_master(): void
    {
        Size::create(['name' => 'XXL', 'sort_order' => 50]);
        $product = $this->product(['Jumbo', 'xl']);

        $this->actingAs($this->admin())->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSeeInOrder(['sizes[S][size]', 'sizes[xl][size]', 'sizes[XXL][size]', 'sizes[Jumbo][size]'], false)
            ->assertDontSee('sizes[XL][size]', false);
    }

    public function test_product_store_rejects_a_size_outside_the_master(): void
    {
        $category = Category::create(['name' => 'Gamis']);

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), [
                'name' => 'Gamis Baru', 'category_id' => $category->id, 'price' => 1000,
                'sizes' => ['Jumbo' => ['size' => 'Jumbo']],
            ])
            ->assertSessionHasErrors('sizes.Jumbo.size');

        $this->assertDatabaseMissing('products', ['name' => 'Gamis Baru']);
    }

    public function test_product_update_accepts_its_own_size_outside_the_master(): void
    {
        $product = $this->product(['Jumbo']);

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $product), [
                'name' => $product->name, 'category_id' => $product->category_id, 'price' => 1000,
                'sizes' => ['Jumbo' => ['size' => 'Jumbo', 'chest_cm' => 120]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_sizes', ['product_id' => $product->id, 'size' => 'Jumbo', 'chest_cm' => 120, 'stock' => 1]);
    }

    public function test_shop_size_filter_follows_master_order_with_unknown_sizes_last(): void
    {
        Size::where('name', 'XL')->update(['sort_order' => 1]);
        $this->product(['Jumbo', 'M', 'XL', 's']);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertViewHas('sizes', ['XL', 's', 'M', 'Jumbo']);
    }

    public function test_size_pages_do_not_show_the_sort_number(): void
    {
        $admin = $this->admin();
        $size = $this->masterSize('M');

        $this->actingAs($admin)->get(route('admin.sizes.index'))->assertOk()->assertDontSee('Urutan</th>', false);
        $this->actingAs($admin)->get(route('admin.sizes.create'))->assertOk()->assertDontSee('name="sort_order"', false);
        $this->actingAs($admin)->get(route('admin.sizes.edit', $size))->assertOk()->assertDontSee('name="sort_order"', false);

        $rows = $this->actingAs($admin)->getJson(route('admin.datatables.sizes'))->json('data');
        $this->assertArrayNotHasKey('sort_order', $rows[0]);
        $this->assertTrue($rows[0]['first']);
        $this->assertTrue(end($rows)['last']);
    }

    public function test_arrows_move_a_size_one_place(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.sizes.move', $this->masterSize('L')), ['direction' => 'up'])
            ->assertRedirect(route('admin.sizes.index'));
        $this->assertSame(['S', 'L', 'M', 'XL'], Size::ordered()->pluck('name')->all());

        $this->actingAs($admin)->patch(route('admin.sizes.move', $this->masterSize('S')), ['direction' => 'down']);
        $this->assertSame(['L', 'S', 'M', 'XL'], Size::ordered()->pluck('name')->all());
    }

    public function test_moving_past_either_end_changes_nothing(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.sizes.move', $this->masterSize('S')), ['direction' => 'up']);
        $this->actingAs($admin)->patch(route('admin.sizes.move', $this->masterSize('XL')), ['direction' => 'down']);

        $this->assertSame(['S', 'M', 'L', 'XL'], Size::ordered()->pluck('name')->all());
        $this->actingAs($admin)->patch(route('admin.sizes.move', $this->masterSize('M')), ['direction' => 'sideways'])
            ->assertSessionHasErrors('direction');
    }

    public function test_moving_works_when_two_sizes_share_a_sort_number(): void
    {
        Size::create(['name' => 'XXL', 'sort_order' => 40]);
        $before = Size::ordered()->pluck('name')->all();
        $last = end($before);

        $this->actingAs($this->admin())->patch(route('admin.sizes.move', $this->masterSize($last)), ['direction' => 'up']);

        $after = Size::ordered()->pluck('name')->all();
        $this->assertSame($last, $after[3]);
        $this->assertSame([10, 20, 30, 40, 50], Size::ordered()->pluck('sort_order')->all());
    }

    public function test_a_new_size_goes_last_without_a_sort_field(): void
    {
        $this->actingAs($this->admin())->post(route('admin.sizes.store'), ['name' => 'XXL'])->assertSessionHasNoErrors();

        $this->assertSame('XXL', Size::ordered()->pluck('name')->last());
    }
}
