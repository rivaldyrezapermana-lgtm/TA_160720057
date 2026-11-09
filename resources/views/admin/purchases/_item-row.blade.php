@php
    $field = fn (string $key) => is_scalar($row[$key] ?? null) ? (string) $row[$key] : '';
@endphp
<tr data-item-row>
    <td class="align-top">
        <select name="items[{{ $index }}][material_id]" aria-label="Bahan" data-item-material
            @error("items.$index.material_id") aria-invalid="true" @enderror
            class="input {{ $errors->has("items.$index.material_id") ? 'has-error' : '' }}">
            <option value="" data-unit="">— Pilih Bahan —</option>
            @foreach ($materials as $m)
                <option value="{{ $m->id }}" data-unit="{{ $m->unit }}" @selected($field('material_id') === (string) $m->id)>{{ $m->name }}</option>
            @endforeach
        </select>
        @error("items.$index.material_id")<p class="field-error">{{ $message }}</p>@enderror
    </td>
    <td class="align-top">
        <input type="number" name="items[{{ $index }}][qty]" value="{{ $field('qty') }}" min="1" step="1" placeholder="0"
            aria-label="Jumlah" data-item-qty
            @error("items.$index.qty") aria-invalid="true" @enderror
            class="input text-right {{ $errors->has("items.$index.qty") ? 'has-error' : '' }}">
        @error("items.$index.qty")<p class="field-error">{{ $message }}</p>@enderror
    </td>
    <td class="align-top !pt-5 text-ink-500" data-item-unit>—</td>
    <td class="align-top">
        <input type="number" name="items[{{ $index }}][unit_cost]" value="{{ $field('unit_cost') }}" min="0" step="0.01" placeholder="0"
            aria-label="Harga per Unit" data-item-cost
            @error("items.$index.unit_cost") aria-invalid="true" @enderror
            class="input text-right {{ $errors->has("items.$index.unit_cost") ? 'has-error' : '' }}">
        @error("items.$index.unit_cost")<p class="field-error">{{ $message }}</p>@enderror
    </td>
    <td class="align-top !pt-5 text-right tabular-nums font-medium whitespace-nowrap" data-item-subtotal>Rp 0</td>
    <td class="align-top text-right">
        <button type="button" class="btn btn-secondary text-red-600" data-item-remove>Hapus</button>
    </td>
</tr>
