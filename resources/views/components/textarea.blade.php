@props(['name', 'label' => null, 'value' => null, 'hint' => null, 'rows' => 3, 'id' => null])
@php
    $id ??= 'f-'.$name;
    $hasError = $errors->has($name);
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    @endif
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" {{ $attributes->except('class')->merge(['class' => 'field-input'.($hasError ? ' has-error' : '')]) }}
        @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>{{ old($name, $value) }}</textarea>
    @if ($hasError)
        <p id="{{ $id }}-error" class="field-error">{{ $errors->first($name) }}</p>
    @elseif ($hint)
        <p class="field-hint">{{ $hint }}</p>
    @endif
</div>
