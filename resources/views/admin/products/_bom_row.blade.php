{{-- Satu baris resep: $index, $row (['material_id', 'qty' => [ukuran => jumlah]]), $sizes, $materials. --}}
@php
    $selected = is_scalar($row['material_id'] ?? null) ? (string) $row['material_id'] : '';
    $quantities = is_array($row['qty'] ?? null) ? $row['qty'] : [];
@endphp
<tr class="js-bom-row">
    <td class="!pl-0 !pr-2 min-w-[15rem]">
        <select name="materials[{{ $index }}][material_id]" aria-label="Bahan baku" class="input js-bom-material {{ $errors->has('materials.'.$index.'.material_id') ? 'has-error' : '' }}">
            <option value="">Pilih bahan</option>
            @foreach ($materials as $m)
                <option value="{{ $m->id }}" data-cost="{{ (float) $m->unit_cost }}" @selected($selected === (string) $m->id)>{{ $m->name }} ({{ $m->unit }})</option>
            @endforeach
        </select>
    </td>
    @foreach ($sizes as $col => $sz)
        @php $qty = $quantities[$sz] ?? ''; @endphp
        <td class="!px-1 w-[5.5rem] min-w-[5rem]">
            <input type="number" min="0" step="0.01" name="materials[{{ $index }}][qty][{{ $sz }}]" value="{{ is_scalar($qty) ? $qty : '' }}" aria-label="Jumlah untuk ukuran {{ $sz }}" data-col="{{ $col }}" placeholder="0" class="input text-right js-bom-qty {{ $errors->has('materials.'.$index.'.qty.'.$sz) ? 'has-error' : '' }}">
        </td>
    @endforeach
    <td class="!pl-2 !pr-0 text-right">
        <button type="button" class="text-sm text-red-700 hover:underline js-bom-remove">Hapus</button>
    </td>
</tr>
