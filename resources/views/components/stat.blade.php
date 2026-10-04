@props(['label', 'value', 'icon' => null, 'tone' => 'brand', 'hint' => null, 'href' => null, 'change' => null])
@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600',
        'blue' => 'bg-blue-50 text-blue-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'rose' => 'bg-rose-50 text-rose-600',
        'violet' => 'bg-violet-50 text-violet-600',
        'slate' => 'bg-slate-100 text-slate-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card group block p-5 '.($href ? 'transition hover:border-slate-300 hover:shadow-md' : '')]) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-[13px] font-medium text-slate-500">{{ $label }}</p>
        @if ($icon)
            <span class="grid size-9 place-items-center rounded-lg {{ $tones[$tone] ?? $tones['brand'] }}">
                <x-icon :name="$icon" class="size-[18px]" />
            </span>
        @endif
    </div>
    <p class="tabular mt-2 text-[26px] font-semibold leading-none tracking-tight text-ink">{{ $value }}</p>
    @if ($change !== null || $hint)
        <div class="mt-3 flex items-center gap-2 text-xs">
            @if ($change !== null)
                <span class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 font-medium {{ $change >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                    <x-icon :name="$change >= 0 ? 'trend-up' : 'trend-down'" class="size-3.5" stroke="2" />
                    {{ number_format(abs($change), 1) }}%
                </span>
            @endif
            @if ($hint)
                <span class="text-slate-500">{{ $hint }}</span>
            @endif
        </div>
    @endif
</{{ $tag }}>
