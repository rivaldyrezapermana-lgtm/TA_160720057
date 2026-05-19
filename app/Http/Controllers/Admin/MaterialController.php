<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaterialController extends Controller
{
    public function index()
    {
        return view('admin.materials.index');
    }

    /** AJAX endpoint for DataTables. */
    public function data(Request $request)
    {
        $rows = Material::latest()->get()->map(fn (Material $material) => [
            'id' => $material->id,
            'code' => $material->code,
            'name' => $material->name,
            'unit' => $material->unit,
            'stock' => $material->stock,
            'min_stock' => $material->min_stock,
            'unit_cost' => number_format((float) $material->unit_cost, 0, ',', '.'),
            'status' => $material->isLowStock() ? 'low' : 'ok',
        ]);

        return response()->json(['data' => $rows]);
    }

    public function create()
    {
        return view('admin.materials.create');
    }

    public function store(Request $request)
    {
        Material::create($request->validate($this->rules()));

        return redirect()->route('admin.materials.index')
            ->with('success', 'Bahan baku berhasil ditambahkan.');
    }

    public function show(Material $material)
    {
        return redirect()->route('admin.materials.edit', $material);
    }

    public function edit(Material $material)
    {
        return view('admin.materials.edit', compact('material'));
    }

    public function update(Request $request, Material $material)
    {
        $material->update($request->validate($this->rules($material)));

        return redirect()->route('admin.materials.index')
            ->with('success', 'Bahan baku berhasil diperbarui.');
    }

    public function destroy(Material $material)
    {
        if ($material->purchaseItems()->exists() || $material->productionMaterials()->exists()) {
            return redirect()->route('admin.materials.index')
                ->with('error', 'Bahan baku tidak bisa dihapus karena sudah dipakai pada pembelian atau produksi.');
        }

        $material->delete();

        return redirect()->route('admin.materials.index')
            ->with('success', 'Bahan baku berhasil dihapus.');
    }

    /** Validation rules shared by store and update. */
    private function rules(?Material $material = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('materials', 'code')->ignore($material)],
            'unit' => ['required', 'string', 'max:20'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }
}
