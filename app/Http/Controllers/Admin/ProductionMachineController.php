<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        $rows = ProductionMachine::latest()->get()->map(fn (ProductionMachine $machine) => [
            'id' => $machine->id,
            'code' => $machine->code,
            'name' => $machine->name,
            'capacity' => $machine->capacity !== null ? $machine->capacity : '-',
            'status' => $machine->status,
        ]);

        return response()->json(['data' => $rows]);
    }

    public function create()
    {
        return view('admin.production-machines.create');
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
        return view('admin.production-machines.edit', ['machine' => $production_machine]);
    }

    public function update(Request $request, ProductionMachine $production_machine)
    {
        $production_machine->update($request->validate($this->rules($production_machine)));

        return redirect()->route('admin.production-machines.index')
            ->with('success', 'Mesin produksi berhasil diperbarui.');
    }

    public function destroy(ProductionMachine $production_machine)
    {
        if ($production_machine->productions()->exists()) {
            return redirect()->route('admin.production-machines.index')
                ->with('error', 'Mesin tidak bisa dihapus karena sudah dipakai pada batch produksi.');
        }

        $production_machine->delete();

        return redirect()->route('admin.production-machines.index')
            ->with('success', 'Mesin produksi berhasil dihapus.');
    }

    /** Validation rules shared by store and update. */
    private function rules(?ProductionMachine $machine = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('production_machines', 'code')->ignore($machine)],
            'status' => ['required', Rule::in(ProductionMachine::STATUSES)],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
