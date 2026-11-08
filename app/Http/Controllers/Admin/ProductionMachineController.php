<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MachineCategory;
use App\Models\ProductionMachine;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionMachineController extends Controller
{
    public function index()
    {
        return view('admin.production-machines.index');
    }

    /** AJAX endpoint for DataTables. */
    public function data(Request $request)
    {
        $rows = ProductionMachine::orderBy('kode_mesin')->get()->map(fn (ProductionMachine $machine) => [
            'id' => $machine->id,
            'kode_mesin' => $machine->kode_mesin,
            'name' => $machine->name,
            'capacity' => $machine->capacity !== null ? $machine->capacity : '-',
            'status' => $machine->status,
        ]);

        return response()->json(['data' => $rows]);
    }

    public function create()
    {
        return view('admin.production-machines.create', [
            'categories' => MachineCategory::orderBy('name')->pluck('name', 'id')->toArray(),
            'nextKode' => ProductionMachine::nextKode(),
        ]);
    }

    public function store(Request $request)
    {
        ProductionMachine::create($request->validate($this->rules()));

        return redirect()->route('admin.production-machines.index')
            ->with('success', 'Mesin produksi berhasil ditambahkan.');
    }

    public function show(ProductionMachine $production_machine)
    {
        return redirect()->route('admin.production-machines.edit', $production_machine);
    }

    public function edit(ProductionMachine $production_machine)
    {
        return view('admin.production-machines.edit', [
            'machine' => $production_machine,
            'categories' => MachineCategory::orderBy('name')->pluck('name', 'id')->toArray(),
        ]);
    }

    public function update(Request $request, ProductionMachine $production_machine)
    {
        $production_machine->update($request->validate($this->rules()));

        return redirect()->route('admin.production-machines.index')
            ->with('success', 'Mesin produksi berhasil diperbarui.');
    }

    public function destroy(ProductionMachine $production_machine)
    {
        if ($production_machine->productions()->exists()) {
            return redirect()->route('admin.production-machines.index')
                ->with('error', 'Mesin tidak dapat dihapus karena sudah digunakan pada batch produksi.');
        }

        $production_machine->delete();

        return redirect()->route('admin.production-machines.index')
            ->with('success', 'Mesin produksi berhasil dihapus.');
    }

    /** Validation rules shared by store and update. kode_mesin dibuat sistem, jadi sengaja tidak diterima dari form. */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'machine_category_id' => ['nullable', 'exists:machine_categories,id'],
            'status' => ['required', Rule::in(ProductionMachine::STATUSES)],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
