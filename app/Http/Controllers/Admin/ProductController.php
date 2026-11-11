<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Material;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        $rows = Product::with('category', 'sizes')->latest()->get()->map(fn (Product $product) => [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'category' => $product->category?->name ?? '—',
            'price' => $product->priceLabel(),
            'hpp' => $this->hppRange($product),
            'stock' => $product->stock,
            'sizes' => $product->sizes->sortBy('id')->map(fn ($s) => ['size' => $s->size, 'stock' => $s->stock])->values(),
            'status' => $product->is_active ? 'active' : 'inactive',
        ]);

        return response()->json(['data' => $rows]);
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        $materials = Material::orderBy('name')->get(['id', 'name', 'unit', 'unit_cost']);
        $sizes = Size::ordered()->pluck('name');

        $bomRows = [];

        return view('admin.products.create', compact('categories', 'materials', 'sizes', 'bomRows'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(), $this->messages());

        $product = Product::create([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'sku' => Product::nextSku(),
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'stock' => 0, // stok hanya bertambah dari hasil produksi
            'is_active' => $request->boolean('is_active'),
            'image' => $request->hasFile('image')
                ? $request->file('image')->store('products', 'public')
                : null,
        ]);

        $this->syncRecipe($product, $data);

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil ditambahkan.')
            ->with($this->hppWarning($product));
    }

    public function show(Product $product)
    {
        $product->load('category', 'sizes', 'materials.material');

        // Produk lama tanpa varian tetap memakai resep tanpa ukuran.
        $lines = $product->materials;
        $hppRows = $product->hppBySize();
        $isGeneric = $hppRows === [] && $lines->isNotEmpty();
        if ($isGeneric) {
            $hppRows = ['Semua Ukuran' => $product->computeHpp()];
        }

        // Jumlah per ukuran mengikuti resep yang dipakai HPP, jadi kedua tabel selalu cocok.
        $recipes = collect($hppRows)->map(
            fn ($hpp, $size) => $product->recipeForSize($isGeneric ? null : (string) $size, $lines)->keyBy('material_id')
        );
        $recipeRows = $lines->unique('material_id')->map(fn ($line) => [
            'name' => $line->material?->name ?? '—',
            'unit' => $line->material?->unit ?? '-',
            'qty' => $recipes->map(fn ($recipe) => $recipe->get($line->material_id)?->qty_required),
        ]);

        return view('admin.products.show', compact('product', 'hppRows', 'recipeRows'));
    }

    public function edit(Product $product)
    {
        $product->load('sizes', 'materials');
        $categories = Category::orderBy('name')->get();
        $materials = Material::orderBy('name')->get(['id', 'name', 'unit', 'unit_cost']);
        $sizes = $this->sizesForEdit($product);

        $bomRows = $this->bomFormRows($product, $sizes);

        return view('admin.products.edit', compact('product', 'categories', 'materials', 'sizes', 'bomRows'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate($this->rules($product), $this->messages());

        $product->fill([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
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
        $this->syncRecipe($product, $data);

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
                ->with('error', 'Produk tidak dapat dihapus karena sudah digunakan pada pesanan, produksi, atau riwayat penjualan.');
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }

    /**
     * Master sizes in order, then any size only this product has, on a variant
     * or on a recipe line. A row spelled differently from the master (xl vs XL)
     * keeps its own spelling so the form edits that row instead of adding a
     * second one.
     */
    private function sizesForEdit(Product $product): Collection
    {
        $own = $product->materials->whereNotNull('size')->pluck('size')
            ->merge($product->sizes->pluck('size'))
            ->keyBy(fn (string $size) => mb_strtolower($size));

        return Size::ordered()->pluck('name')
            ->map(fn (string $name) => $own->get(mb_strtolower($name), $name))
            ->merge($own->values())
            ->unique()
            ->values();
    }

    /**
     * Recipe rows for the edit form, one per material. A line without a size
     * fills every size column so saving the form does not drop it.
     *
     * @return array<int, array{material_id: int, qty: array<string, float>}>
     */
    private function bomFormRows(Product $product, Collection $sizes): array
    {
        $columns = $sizes->keyBy(fn (string $size) => mb_strtolower($size));

        return $product->materials->groupBy('material_id')
            ->map(function ($lines, $materialId) use ($sizes, $columns) {
                $qty = [];
                foreach ($lines->whereNull('size') as $line) {
                    $qty = $sizes->mapWithKeys(fn (string $size) => [$size => (float) $line->qty_required])->all();
                }
                foreach ($lines->whereNotNull('size') as $line) {
                    $qty[$columns[mb_strtolower($line->size)]] = (float) $line->qty_required;
                }

                return ['material_id' => $materialId, 'qty' => $qty];
            })
            ->values()
            ->all();
    }

    /** Validation rules shared by store and update. */
    private function rules(?Product $product = null): array
    {
        $allowedSizes = Size::pluck('name')
            ->merge($product?->sizes()->pluck('size') ?? [])
            ->merge($product?->materials()->whereNotNull('size')->pluck('size') ?? [])
            ->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
            'sizes' => ['nullable', 'array'],
            'sizes.*.size' => ['required', 'string', 'max:10', Rule::in($allowedSizes)],
            'sizes.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'sizes.*.chest_cm' => ['nullable', 'integer', 'min:0'],
            'sizes.*.length_cm' => ['nullable', 'integer', 'min:0'],
            'sizes.*.sleeve_cm' => ['nullable', 'integer', 'min:0'],
            'materials' => ['nullable', 'array', function ($attribute, $value, $fail) use ($allowedSizes) {
                $lines = $this->recipeLines(is_array($value) ? $value : []);

                if ($lines->pluck('material_id')->duplicates()->isNotEmpty()) {
                    $fail('Bahan baku yang sama tidak boleh dipilih lebih dari satu kali.');
                }

                $unknown = $lines->flatMap(fn (array $line) => array_keys($line['qty']))
                    ->map(fn ($size) => (string) $size)
                    ->unique()
                    ->diff($allowedSizes);
                if ($unknown->isNotEmpty()) {
                    $fail('Ukuran '.$unknown->implode(', ').' tidak terdaftar pada master ukuran.');
                }
            }],
            'materials.*' => ['array'],
            'materials.*.material_id' => ['nullable', 'integer', Rule::exists('materials', 'id')],
            'materials.*.qty' => ['nullable', 'array'],
            'materials.*.qty.*' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    private function messages(): array
    {
        return [
            'sizes.*.size.in' => 'Ukuran :input tidak terdaftar pada master ukuran.',
            'sizes.*.price.numeric' => 'Harga ukuran harus berupa angka.',
            'sizes.*.price.min' => 'Harga ukuran tidak boleh kurang dari 0.',
            'sizes.*.price.max' => 'Harga ukuran terlalu besar.',
            'materials.*.material_id.exists' => 'Bahan baku yang dipilih tidak terdaftar.',
            'materials.*.qty.*.numeric' => 'Jumlah bahan harus berupa angka.',
            'materials.*.qty.*.min' => 'Jumlah bahan tidak boleh kurang dari 0.',
            'materials.*.qty.*.max' => 'Jumlah bahan terlalu besar.',
        ];
    }

    /**
     * The submitted recipe rows that count: a material with at least one
     * positive quantity. Quantities round to the two decimals the column keeps.
     *
     * @return Collection<int, array{material_id: int, qty: array<string, float>}>
     */
    private function recipeLines(array $rows): Collection
    {
        return collect($rows)
            ->filter(fn ($row) => is_array($row) && is_numeric($row['material_id'] ?? null))
            ->map(fn (array $row) => [
                'material_id' => (int) $row['material_id'],
                'qty' => collect(is_array($row['qty'] ?? null) ? $row['qty'] : [])
                    ->filter(fn ($qty) => is_numeric($qty))
                    ->map(fn ($qty) => round((float) $qty, 2))
                    ->filter(fn (float $qty) => $qty > 0)
                    ->all(),
            ])
            ->filter(fn (array $line) => $line['qty'] !== [])
            ->values();
    }

    /** Save the size variants and recipe together, then refresh the HPP snapshot. */
    private function syncRecipe(Product $product, array $data): void
    {
        $lines = $this->recipeLines($data['materials'] ?? []);

        DB::transaction(function () use ($product, $data, $lines) {
            $this->syncSizes($product, $data['sizes'] ?? [], $lines);
            $this->syncMaterials($product, $lines);
            $this->refreshHpp($product);
        });
    }

    /**
     * Create or update each size variant submitted with the product. Stock only
     * moves through production and sales: an update never touches it and a new
     * variant starts at 0.
     */
    private function syncSizes(Product $product, array $sizes, Collection $lines): void
    {
        $recipeSizes = $lines->flatMap(fn (array $line) => array_keys($line['qty']))
            ->map(fn ($size) => (string) $size)
            ->unique();

        foreach ($sizes as $size) {
            if (empty($size['size'])) {
                continue;
            }

            $attributes = [
                'chest_cm' => $this->intOrNull($size['chest_cm'] ?? null),
                'length_cm' => $this->intOrNull($size['length_cm'] ?? null),
                'sleeve_cm' => $this->intOrNull($size['sleeve_cm'] ?? null),
            ];
            if (array_key_exists('price', $size)) {
                $attributes['price'] = $size['price'];
            }

            $variant = $product->sizes()->where('size', $size['size'])->first();
            if ($variant) {
                $variant->update($attributes);

                continue;
            }

            // Baris kosong berarti produk tidak memakai ukuran ini. Varian baru
            // tetap bisa muncul saat ukuran tersebut dipilih pada batch produksi.
            $isFilled = array_filter($attributes, fn ($value) => $value !== null) !== [];
            if ($isFilled) {
                $product->sizes()->create(['size' => $size['size'], 'stock' => 0] + $attributes);
            }
        }

        foreach ($recipeSizes as $size) {
            $product->sizes()->firstOrCreate(['size' => $size], ['stock' => 0]);
        }
    }

    /**
     * Replace the product's bill of materials with the submitted rows: one line
     * per material per size. Anything else goes, including old lines without a size.
     */
    private function syncMaterials(Product $product, Collection $lines): void
    {
        $keep = [];

        foreach ($lines as $line) {
            foreach ($line['qty'] as $size => $qty) {
                $keep[] = $product->materials()->updateOrCreate(
                    ['material_id' => $line['material_id'], 'size' => (string) $size],
                    ['qty_required' => $qty],
                )->id;
            }
        }

        $product->materials()->whereNotIn('id', $keep)->delete();
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

    /** HPP range across sizes for the product list, e.g. "Rp 81.000 – Rp 101.000". */
    private function hppRange(Product $product): string
    {
        $costs = array_filter($product->hppBySize(), fn (float $hpp) => $hpp > 0);
        if ($costs === []) {
            return '—';
        }

        $low = 'Rp '.number_format(min($costs), 0, ',', '.');
        $high = 'Rp '.number_format(max($costs), 0, ',', '.');

        return $low === $high ? $low : $low.' – '.$high;
    }

    /** Build a flash payload naming every size whose HPP is above that size's selling price. */
    private function hppWarning(Product $product): array
    {
        $product->load('sizes');

        $under = collect($product->hppBySize())
            ->filter(fn (float $hpp, $size) => $hpp > 0 && $product->priceForSize((string) $size) < $hpp)
            ->map(fn (float $hpp, $size) => $size.' (harga Rp '.number_format($product->priceForSize((string) $size), 0, ',', '.')
                .', HPP Rp '.number_format($hpp, 0, ',', '.').')');

        if ($under->isEmpty()) {
            return [];
        }

        return ['warning' => 'Harga jual di bawah HPP pada ukuran '.$under->implode(', ').'. Periksa kembali margin.'];
    }
}
