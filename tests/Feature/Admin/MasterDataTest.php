<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Material;
use App\Models\Product;
use App\Models\SalesHistory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    /** A signed-in admin to act as for every request. */
    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Uji',
            'email' => 'admin.uji@labasa.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
        ]);
    }

    // ---- Categories ---------------------------------------------------

    public function test_categories_index_loads(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.categories.index'))
            ->assertOk();
    }

    public function test_category_can_be_created(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), ['name' => 'Gamis Anak'])
            ->assertRedirectToRoute('admin.categories.index');

        $this->assertDatabaseHas('categories', ['name' => 'Gamis Anak']);
    }

    public function test_category_requires_a_name(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_category_can_be_updated(): void
    {
        $category = Category::create(['name' => 'Lama']);

        $this->actingAs($this->admin())
            ->put(route('admin.categories.update', $category), ['name' => 'Baru'])
            ->assertRedirectToRoute('admin.categories.index');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Baru']);
    }

    public function test_category_can_be_deleted(): void
    {
        $category = Category::create(['name' => 'Hapus']);

        $this->actingAs($this->admin())
            ->delete(route('admin.categories.destroy', $category));

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $category = Category::create(['name' => 'Dipakai']);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Produk', 'sku' => 'SKU-GUARD', 'price' => 1000, 'stock' => 1,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.categories.destroy', $category))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    // ---- Products -----------------------------------------------------

    public function test_product_can_be_created_with_sizes(): void
    {
        $category = Category::create(['name' => 'Gamis']);

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), [
                'name' => 'Gamis Navy',
                'category_id' => $category->id,
                'price' => 225000,
                'stock' => 10,
                'is_active' => '1',
                'sizes' => [
                    'M' => ['size' => 'M', 'chest_cm' => 96, 'length_cm' => 137, 'sleeve_cm' => 57, 'stock' => 5],
                ],
            ])
            ->assertRedirectToRoute('admin.products.index');

        $this->assertDatabaseHas('products', ['name' => 'Gamis Navy', 'sku' => 'SKU-0001', 'is_active' => true]);
        $this->assertDatabaseHas('product_sizes', ['size' => 'M', 'stock' => 5]);
    }

    public function test_product_datatable_returns_json_rows(): void
    {
        $category = Category::create(['name' => 'Gamis']);
        Product::create([
            'category_id' => $category->id,
            'name' => 'P', 'sku' => 'P-1', 'price' => 1000, 'stock' => 1,
        ]);

        $this->actingAs($this->admin())
            ->getJson(route('admin.datatables.products'))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'sku', 'name', 'category', 'price', 'stock', 'status']]]);
    }

    public function test_product_with_sales_history_cannot_be_deleted(): void
    {
        $category = Category::create(['name' => 'Gamis']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'P', 'sku' => 'P-HIST', 'price' => 1000, 'stock' => 1,
        ]);
        SalesHistory::create([
            'product_id' => $product->id, 'year' => 2025, 'month' => 1,
            'demand' => 100, 'stock_end' => 10, 'produced' => 90,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.products.destroy', $product))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    // ---- Materials ----------------------------------------------------

    public function test_material_can_be_created(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.materials.store'), [
                'name' => 'Kain Katun',
                'code' => 'MAT-001',
                'unit' => 'meter',
                'stock' => 50,
                'min_stock' => 10,
                'unit_cost' => 45000,
            ])
            ->assertRedirectToRoute('admin.materials.index');

        $this->assertDatabaseHas('materials', ['code' => 'MAT-001']);
    }

    public function test_material_code_must_be_unique(): void
    {
        Material::create([
            'name' => 'A', 'code' => 'MAT-DUP', 'unit' => 'm',
            'stock' => 1, 'min_stock' => 1, 'unit_cost' => 1,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.materials.store'), [
                'name' => 'B', 'code' => 'MAT-DUP', 'unit' => 'm',
                'stock' => 1, 'min_stock' => 1, 'unit_cost' => 1,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_material_datatable_flags_low_stock(): void
    {
        Material::create([
            'name' => 'Menipis', 'code' => 'MAT-LOW', 'unit' => 'm',
            'stock' => 2, 'min_stock' => 10, 'unit_cost' => 1,
        ]);

        $this->actingAs($this->admin())
            ->getJson(route('admin.datatables.materials'))
            ->assertOk()
            ->assertJsonFragment(['code' => 'MAT-LOW', 'status' => 'low']);
    }

    // ---- Suppliers ----------------------------------------------------

    public function test_supplier_can_be_created_and_deleted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.suppliers.store'), ['name' => 'CV Jaya'])
            ->assertRedirectToRoute('admin.suppliers.index');

        $supplier = Supplier::firstWhere('name', 'CV Jaya');
        $this->assertNotNull($supplier);

        $this->actingAs($admin)->delete(route('admin.suppliers.destroy', $supplier));
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    // ---- Users --------------------------------------------------------

    public function test_user_is_created_with_a_hashed_password(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), [
                'name' => 'Karyawan Baru',
                'email' => 'karyawan.baru@labasa.test',
                'role' => User::ROLE_KARYAWAN,
                'password' => 'rahasia123',
            ])
            ->assertRedirectToRoute('admin.users.index');

        $user = User::firstWhere('email', 'karyawan.baru@labasa.test');
        $this->assertNotNull($user);
        $this->assertNotSame('rahasia123', $user->password);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_user_update_keeps_password_when_left_blank(): void
    {
        $user = User::create([
            'name' => 'Lama', 'email' => 'lama@labasa.test',
            'password' => 'password-lama', 'role' => User::ROLE_PEMBELI,
        ]);
        $originalHash = $user->fresh()->password;

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $user), [
                'name' => 'Baru',
                'email' => 'lama@labasa.test',
                'role' => User::ROLE_PEMBELI,
                'password' => '',
            ])
            ->assertRedirectToRoute('admin.users.index');

        $this->assertSame($originalHash, $user->fresh()->password);
        $this->assertSame('Baru', $user->fresh()->name);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
