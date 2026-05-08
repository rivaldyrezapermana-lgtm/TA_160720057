@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'placeholder' => '', 'required' => false, 'help' => null])
<div class="field">
    @if ($label)
        <label for="{{ $name }}" class="label">{{ $label }}@if($required)<span class="req">·</span>@endif</label>
    @endif
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ old($name, $value) }}"
        placeholder="{{ $placeholder }}"
        @if($required) required @endif
        @error($name) aria-invalid="true" @enderror
        {{ $attributes->merge(['class' => 'input ' . ($errors->has($name) ? 'has-error' : '')]) }}>
    @if ($help)
        <p class="field-help">{{ $help }}</p>
    @endif
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>
