@props(['name', 'label' => null, 'options' => [], 'selected' => null, 'placeholder' => '— Pilih —', 'required' => false])
<div class="field">
    @if ($label)
        <label for="{{ $name }}" class="label">{{ $label }}@if($required)<span class="req">·</span>@endif</label>
    @endif
    <select name="{{ $name }}" id="{{ $name }}"
        @if($required) required @endif
        @error($name) aria-invalid="true" @enderror
        {{ $attributes->merge(['class' => 'input ' . ($errors->has($name) ? 'has-error' : '')]) }}>
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $value => $label)
            <option value="{{ $value }}" @selected(old($name, $selected) == $value)>{{ $label }}</option>
        @endforeach
    </select>
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>
