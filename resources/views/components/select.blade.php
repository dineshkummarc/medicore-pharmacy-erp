@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'hint' => null, 'id' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id ??= 'f-'.str_replace(['[', ']', '.'], '-', $name);
    $hasError = $errors->has($key);
    $current = (string) old($key, $value instanceof \BackedEnum ? $value->value : $value);
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="field-label">
            {{ $label }} @if($attributes->has('required'))<span class="text-rose-500" aria-hidden="true">*</span>@endif
        </label>
    @endif
    <select id="{{ $id }}" name="{{ $name }}" {{ $attributes->except('class')->merge(['class' => 'field-input pr-9'.($hasError ? ' has-error' : '')]) }}
        @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($current !== '' && $current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($hasError)
        <p id="{{ $id }}-error" class="field-error">{{ $errors->first($key) }}</p>
    @elseif ($hint)
        <p class="field-hint">{{ $hint }}</p>
    @endif
</div>
