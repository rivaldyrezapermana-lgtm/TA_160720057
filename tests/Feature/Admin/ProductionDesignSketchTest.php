<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionDesignSketch;
use App\Models\ProductionStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionDesignSketchTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function admin(): User
    {
        return $this->user ??= User::create([
            'name' => 'Op', 'email' => 'op@labasa.test', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function batch(): Production
    {
        $cat = Category::create(['name' => 'Gamis']);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Gamis A', 'sku' => 'GA-1',
            'price' => 100000, 'stock' => 0, 'is_active' => true,
        ]);

        $this->actingAs($this->admin())->post(route('admin.productions.store'), [
            'product_id' => $product->id,
            'planned_qty' => 10,
            'start_date' => now()->toDateString(),
        ]);

        return Production::latest('id')->first();
    }

    private function design(Production $p): ProductionStage
    {
        return $p->stages()->where('stage', 'design')->firstOrFail();
    }

    private function upload(Production $p, array $files)
    {
        return $this->actingAs($this->admin())
            ->post(route('admin.productions.sketches.store', $p), ['sketches' => $files]);
    }

    public function test_sketches_can_be_uploaded_to_the_design_stage(): void
    {
        $prod = $this->batch();

        $this->upload($prod, [
            UploadedFile::fake()->image('depan.jpg'),
            UploadedFile::fake()->image('belakang.png'),
        ])->assertSessionHas('success');

        $sketches = $prod->designSketches()->get();
        $this->assertCount(2, $sketches);
        $this->assertSame('depan.jpg', $sketches[0]->original_name);
        $this->assertSame($this->admin()->id, $sketches[0]->user_id);
        Storage::disk('public')->assertExists($sketches[0]->path);
        $this->assertStringStartsWith('production-designs/', $sketches[0]->path);
    }

    public function test_non_image_files_are_rejected(): void
    {
        $prod = $this->batch();

        $this->upload($prod, [UploadedFile::fake()->create('pola.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('sketches.0');

        $this->assertSame(0, $prod->designSketches()->count());
    }

    public function test_design_cannot_be_finished_without_a_sketch(): void
    {
        $prod = $this->batch();
        $design = $this->design($prod);
        $design->update(['status' => 'in_progress']);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $design]), ['action' => 'finish'])
            ->assertSessionHas('error', 'Unggah minimal satu sketsa desain sebelum menyelesaikan tahap Desain.');

        $this->assertSame('in_progress', $design->fresh()->status);
    }

    public function test_design_can_be_finished_once_a_sketch_exists(): void
    {
        $prod = $this->batch();
        $design = $this->design($prod);
        $design->update(['status' => 'in_progress']);
        $this->upload($prod, [UploadedFile::fake()->image('depan.jpg')]);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $design]), ['action' => 'finish'])
            ->assertSessionHas('success');

        $this->assertSame('completed', $design->fresh()->status);
    }

    public function test_a_sketch_can_be_deleted_before_design_is_finished(): void
    {
        $prod = $this->batch();
        $this->upload($prod, [UploadedFile::fake()->image('depan.jpg')]);
        $sketch = $prod->designSketches()->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('admin.productions.sketches.destroy', [$prod, $sketch]))
            ->assertSessionHas('success');

        $this->assertModelMissing($sketch);
        Storage::disk('public')->assertMissing($sketch->path);
    }

    public function test_sketches_are_locked_once_design_is_finished(): void
    {
        $prod = $this->batch();
        $this->upload($prod, [UploadedFile::fake()->image('depan.jpg')]);
        $sketch = $prod->designSketches()->firstOrFail();
        $this->design($prod)->update(['status' => 'completed']);

        $this->actingAs($this->admin())
            ->delete(route('admin.productions.sketches.destroy', [$prod, $sketch]))
            ->assertSessionHas('error');
        $this->upload($prod, [UploadedFile::fake()->image('baru.jpg')])
            ->assertSessionHas('error');

        $this->assertModelExists($sketch);
        Storage::disk('public')->assertExists($sketch->path);
        $this->assertSame(1, $prod->designSketches()->count());
    }

    public function test_a_sketch_from_another_batch_cannot_be_deleted_through_this_one(): void
    {
        $prod = $this->batch();
        $this->upload($prod, [UploadedFile::fake()->image('depan.jpg')]);
        $sketch = $prod->designSketches()->firstOrFail();
        $other = Production::create([
            'product_id' => $prod->product_id, 'user_id' => $this->admin()->id, 'code' => 'PRD-X', 'planned_qty' => 1,
            'start_date' => now()->toDateString(), 'status' => 'planned',
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.productions.sketches.destroy', [$other, $sketch]))
            ->assertNotFound();

        $this->assertModelExists($sketch);
    }

    public function test_deleting_a_batch_removes_its_sketch_files(): void
    {
        $prod = $this->batch();
        $this->upload($prod, [UploadedFile::fake()->image('depan.jpg')]);
        $path = $prod->designSketches()->firstOrFail()->path;

        $this->actingAs($this->admin())->delete(route('admin.productions.destroy', $prod));

        $this->assertSame(0, ProductionDesignSketch::count());
        Storage::disk('public')->assertMissing($path);
    }

    public function test_batch_page_shows_the_uploaded_sketches(): void
    {
        $prod = $this->batch();
        $this->upload($prod, [UploadedFile::fake()->image('depan.jpg')]);
        $sketch = $prod->designSketches()->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.productions.show', $prod))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($sketch->path), false)
            ->assertSee('Sketsa Desain');
    }
}
