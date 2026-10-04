@props(['name', 'label', 'checked' => false, 'description' => null])
@php $isOn = (bool) old($name, $checked); @endphp
<label {{ $attributes->merge(['class' => 'flex cursor-pointer items-start gap-3']) }} x-data="{ on: @js($isOn) }">
    <input type="hidden" name="{{ $name }}" :value="on ? 1 : 0" value="{{ $isOn ? 1 : 0 }}">
    <button type="button" role="switch" :aria-checked="on.toString()" @click="on = !on"
            class="relative mt-0.5 inline-flex h-5 w-9 shrink-0 rounded-full transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
            :class="on ? 'bg-brand-600' : 'bg-slate-300'">
        <span class="pointer-events-none inline-block size-4 translate-x-0.5 translate-y-0.5 rounded-full bg-white shadow transition" :class="on && 'translate-x-[18px]'"></span>
    </button>
    <span>
        <span class="block text-sm font-medium text-slate-700">{{ $label }}</span>
        @if ($description)<span class="block text-xs text-slate-500">{{ $description }}</span>@endif
    </span>
</label>
