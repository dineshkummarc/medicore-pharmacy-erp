@props(['tone' => 'gray', 'dot' => false])
@php
    $tones = [
        'gray' => 'bg-slate-100 text-slate-700 ring-slate-500/10',
        'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
        'red' => 'bg-rose-50 text-rose-700 ring-rose-600/15',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-600/15',
        'brand' => 'bg-brand-50 text-brand-700 ring-brand-600/15',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-600/15',
    ];
    $dots = ['gray' => 'bg-slate-400', 'green' => 'bg-emerald-500', 'red' => 'bg-rose-500', 'amber' => 'bg-amber-500', 'blue' => 'bg-blue-500', 'brand' => 'bg-brand-500', 'violet' => 'bg-violet-500'];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.($tones[$tone] ?? $tones['gray'])]) }}>
    @if ($dot)<span class="size-1.5 rounded-full {{ $dots[$tone] ?? $dots['gray'] }}"></span>@endif
    {{ $slot }}
</span>
