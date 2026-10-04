@props(['name', 'options' => [], 'placeholder' => 'All'])
<label class="sr-only" for="flt-{{ $name }}">{{ $placeholder }}</label>
<select id="flt-{{ $name }}" name="{{ $name }}" {{ $attributes->merge(['class' => 'field-input w-auto min-w-36 py-2 pr-9']) }}>
    <option value="">{{ $placeholder }}</option>
    @foreach ($options as $value => $label)
        <option value="{{ $value }}" @selected((string) request($name) === (string) $value)>{{ $label }}</option>
    @endforeach
</select>
