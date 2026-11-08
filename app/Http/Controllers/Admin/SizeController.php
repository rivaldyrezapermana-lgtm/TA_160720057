<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Size;
use Closure;
use Illuminate\Http\Request;

class SizeController extends Controller
{
    public function index()
    {
        return view('admin.sizes.index');
    }

    /** AJAX endpoint for DataTables. */
    public function data(Request $request)
    {
        $rows = Size::ordered()->get()->map(fn (Size $size) => [
            'id' => $size->id,
            'name' => $size->name,
            'sort_order' => $size->sort_order,
            'used' => $size->isUsed(),
        ]);

        return response()->json(['data' => $rows]);
    }

    public function create()
    {
        return view('admin.sizes.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] ??= (int) Size::max('sort_order') + 10;

        Size::create($data);

        return redirect()->route('admin.sizes.index')
            ->with('success', 'Ukuran berhasil ditambahkan.');
    }

    public function edit(Size $size)
    {
        return view('admin.sizes.edit', ['size' => $size, 'used' => $size->isUsed()]);
    }

    public function update(Request $request, Size $size)
    {
        $data = $this->validated($request, $size);
        $data['sort_order'] ??= $size->sort_order;

        $size->update($data);

        return redirect()->route('admin.sizes.index')
            ->with('success', 'Ukuran berhasil diperbarui.');
    }

    public function destroy(Size $size)
    {
        if ($size->isUsed()) {
            return redirect()->route('admin.sizes.index')
                ->with('error', 'Ukuran tidak dapat dihapus karena sudah digunakan pada produk, produksi, atau pesanan.');
        }

        $size->delete();

        return redirect()->route('admin.sizes.index')
            ->with('success', 'Ukuran berhasil dihapus.');
    }

    /**
     * Validate store and update input. The name is trimmed by the global
     * TrimStrings middleware.
     */
    private function validated(Request $request, ?Size $size = null): array
    {
        return $request->validate([
            'name' => [
                'bail', 'required', 'string', 'max:10',
                // Nama ukuran dipakai sebagai kunci array pada form produk dan produksi.
                'not_regex:/[.\[\]]/',
                $this->uniqueIgnoringCase($size),
                $this->unchangedWhenUsed($size),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ], [
            'name.not_regex' => 'Nama ukuran tidak boleh memuat tanda titik atau kurung siku.',
        ], [
            'name' => 'nama ukuran',
            'sort_order' => 'urutan',
        ]);
    }

    /** MySQL collation treats xl and XL as equal while SQLite does not, so compare in PHP-lowered form. */
    private function uniqueIgnoringCase(?Size $size): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($size) {
            $taken = Size::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                ->when($size, fn ($query) => $query->whereKeyNot($size->getKey()))
                ->exists();

            if ($taken) {
                $fail('Nama ukuran sudah digunakan.');
            }
        };
    }

    /** Other tables store the size name as text, so a used size must keep its exact name. */
    private function unchangedWhenUsed(?Size $size): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($size) {
            if ($size && $value !== $size->name && $size->isUsed()) {
                $fail('Nama ukuran tidak dapat diubah karena sudah digunakan pada produk, produksi, atau pesanan.');
            }
        };
    }
}
