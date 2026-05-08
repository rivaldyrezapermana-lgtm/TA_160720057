@props(['name', 'label' => null, 'value' => null, 'rows' => 4, 'required' => false, 'placeholder' => '', 'help' => null])
<div class="field">
    @if ($label)
        <label for="{{ $name }}" class="label">{{ $label }}@if($required)<span class="req">·</span>@endif</label>
    @endif
    <textarea name="{{ $name }}" id="{{ $name }}" rows="{{ $rows }}" placeholder="{{ $placeholder }}"
        @if($required) required @endif
        @error($name) aria-invalid="true" @enderror
        {{ $attributes->merge(['class' => 'input ' . ($errors->has($name) ? 'has-error' : '')]) }}>{{ old($name, $value) }}</textarea>
    @if ($help)<p class="field-help">{{ $help }}</p>@endif
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>
