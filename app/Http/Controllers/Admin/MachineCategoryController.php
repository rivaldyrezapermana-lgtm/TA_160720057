<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MachineCategory;
use App\Models\ProductionStage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MachineCategoryController extends Controller
{
    public function index()
    {
        return view('admin.machine-categories.index');
    }

    public function data(Request $request)
    {
        $rows = MachineCategory::withCount('machines')->latest()->get()->map(fn (MachineCategory $c) => [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'stage' => $c->stage ?? '-',
            'machines' => $c->machines_count,
        ]);

        return response()->json(['data' => $rows]);
    }

    public function create()
    {
        return view('admin.machine-categories.create', ['stages' => ProductionStage::STAGES]);
    }

    public function store(Request $request)
    {
        MachineCategory::create($request->validate($this->rules()));

        return redirect()->route('admin.machine-categories.index')
            ->with('success', 'Kategori mesin berhasil ditambahkan.');
    }

    public function show(MachineCategory $machine_category)
    {
        return redirect()->route('admin.machine-categories.edit', $machine_category);
    }

    public function edit(MachineCategory $machine_category)
    {
        return view('admin.machine-categories.edit', [
            'category' => $machine_category,
            'stages' => ProductionStage::STAGES,
        ]);
    }

    public function update(Request $request, MachineCategory $machine_category)
    {
        $machine_category->update($request->validate($this->rules($machine_category)));

        return redirect()->route('admin.machine-categories.index')
            ->with('success', 'Kategori mesin berhasil diperbarui.');
    }

    public function destroy(MachineCategory $machine_category)
    {
        if ($machine_category->machines()->exists()) {
            return redirect()->route('admin.machine-categories.index')
                ->with('error', 'Kategori tidak bisa dihapus karena masih dipakai mesin.');
        }

        $machine_category->delete();

        return redirect()->route('admin.machine-categories.index')
            ->with('success', 'Kategori mesin berhasil dihapus.');
    }

    private function rules(?MachineCategory $category = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('machine_categories', 'code')->ignore($category)],
            'stage' => ['nullable', Rule::in(ProductionStage::STAGES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
