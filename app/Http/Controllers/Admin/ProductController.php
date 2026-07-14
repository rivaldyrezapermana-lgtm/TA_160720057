<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Material;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        return view('admin.products.index');
    }

    /** AJAX endpoint for DataTables. */
    public function data(Request $request)
    {
        $rows = Product::with('category')->latest()->get()->map(fn (Product $product) => [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'category' => $product->category?->name ?? '—',
            'price' => number_format((float) $product->price, 0, ',', '.'),
            'stock' => $product->stock,
            'status' => $product->is_active ? 'active' : 'inactive',
        ]);

        return response()->json(['data' => $rows]);
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        $materials = Material::orderBy('name')->get(['id', 'name', 'unit', 'unit_cost']);

        return view('admin.products.create', compact('categories', 'materials'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $product = Product::create([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'sku' => $data['sku'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'stock' => (int) ($data['stock'] ?? 0),
            'is_active' => $request->boolean('is_active'),
            'image' => $request->hasFile('image')
                ? $request->file('image')->store('products', 'public')
                : null,
        ]);

        $this->syncSizes($product, $request->input('sizes', []));
        $this->syncMaterials($product, $request->input('materials', []));
        $this->refreshHpp($product);

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil ditambahkan.')
            ->with($this->hppWarning($product));
    }

    public function show(Product $product)
    {
        $product->load('category', 'sizes', 'materials.material');

        return view('admin.products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $product->load('sizes', 'materials');
        $categories = Category::orderBy('name')->get();
        $materials = Material::orderBy('name')->get(['id', 'name', 'unit', 'unit_cost']);

        return view('admin.products.edit', compact('product', 'categories', 'materials'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate($this->rules($product));

        $product->fill([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'sku' => $data['sku'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $product->image = $request->file('image')->store('products', 'public');
        }

        $product->save();
        $this->syncSizes($product, $request->input('sizes', []));
        $this->syncMaterials($product, $request->input('materials', []));
        $this->refreshHpp($product);

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil diperbarui.')
            ->with($this->hppWarning($product));
    }

    public function destroy(Product $product)
    {
        if ($product->orderItems()->exists()
            || $product->productions()->exists()
            || $product->salesHistories()->exists()) {
            return redirect()->route('admin.products.index')
                ->with('error', 'Produk tidak bisa dihapus karena sudah dipakai pada pesanan, produksi, atau riwayat penjualan.');
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }

    /** Validation rules shared by store and update. */
    private function rules(?Product $product = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product)],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
            'sizes' => ['nullable', 'array'],
            'sizes.*.size' => ['required', 'string', 'max:10'],
            'sizes.*.chest_cm' => ['nullable', 'integer', 'min:0'],
            'sizes.*.length_cm' => ['nullable', 'integer', 'min:0'],
            'sizes.*.sleeve_cm' => ['nullable', 'integer', 'min:0'],
            'sizes.*.stock' => ['nullable', 'integer', 'min:0'],
            'materials' => ['nullable', 'array'],
            'materials.*.use' => ['nullable'],
            'materials.*.qty_required' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** Create or update each size variant submitted with the product. */
    private function syncSizes(Product $product, array $sizes): void
    {
        foreach ($sizes as $size) {
            if (empty($size['size'])) {
                continue;
            }

            $product->sizes()->updateOrCreate(
                ['size' => $size['size']],
                [
                    'chest_cm' => $this->intOrNull($size['chest_cm'] ?? null),
                    'length_cm' => $this->intOrNull($size['length_cm'] ?? null),
                    'sleeve_cm' => $this->intOrNull($size['sleeve_cm'] ?? null),
                    'stock' => $this->intOrNull($size['stock'] ?? null) ?? 0,
                ],
            );
        }
    }

    /**
     * Replace the product's bill of materials with the submitted rows.
     * Each checked material with a positive qty becomes one BOM line.
     */
    private function syncMaterials(Product $product, array $materials): void
    {
        $keep = [];

        foreach ($materials as $materialId => $row) {
            if (empty($row['use'])) {
                continue;
            }

            $qty = $this->intOrNull($row['qty_required'] ?? null);
            if ($qty === null || $qty <= 0) {
                continue;
            }

            $product->materials()->updateOrCreate(
                ['material_id' => $materialId],
                ['qty_required' => $qty],
            );

            $keep[] = $materialId;
        }

        $product->materials()->whereNotIn('material_id', $keep ?: [0])->delete();
    }

    /** Cast a form value to an integer, treating blank input as null. */
    private function intOrNull(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }

    /** Recalculate and persist the product's HPP snapshot from its recipe. */
    private function refreshHpp(Product $product): void
    {
        $product->forceFill(['hpp' => $product->computeHpp()])->save();
    }

    /** Build a flash payload warning when the selling price is below HPP. */
    private function hppWarning(Product $product): array
    {
        if ((float) $product->hpp > 0 && (float) $product->price < (float) $product->hpp) {
            return ['warning' => 'Harga jual (Rp '.number_format((float) $product->price, 0, ',', '.').
                ') di bawah HPP (Rp '.number_format((float) $product->hpp, 0, ',', '.').'). Periksa kembali margin.'];
        }

        return [];
    }
}
