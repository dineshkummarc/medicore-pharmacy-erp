@props(['name', 'label' => null, 'value' => null, 'type' => 'text', 'hint' => null, 'prefix' => null, 'id' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id ??= 'f-'.str_replace(['[', ']', '.'], '-', $name);
    $hasError = $errors->has($key);
    $current = $type === 'password' ? null : old($key, $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value);
@endphp
<div {{ $attributes->only('class')->merge(['class' => '']) }}>
    @if ($label)
        <label for="{{ $id }}" class="field-label">
            {{ $label }} @if($attributes->has('required'))<span class="text-rose-500" aria-hidden="true">*</span>@endif
        </label>
    @endif
    <div class="relative">
        @if ($prefix)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-400">{{ $prefix }}</span>
        @endif
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @if(! is_null($current)) value="{{ $current }}" @endif
            {{ $attributes->except('class')->merge(['class' => 'field-input'.($hasError ? ' has-error' : '').($prefix ? ' pl-8' : '')]) }}
            @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif($hint) aria-describedby="{{ $id }}-hint" @endif>
    </div>
    @if ($hasError)
        <p id="{{ $id }}-error" class="field-error">{{ $errors->first($key) }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="field-hint">{{ $hint }}</p>
    @endif
</div>
