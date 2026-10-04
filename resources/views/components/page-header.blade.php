@props(['title', 'description' => null, 'back' => null])
<div {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="mb-2 inline-flex items-center gap-1 text-[13px] font-medium text-slate-500 hover:text-ink">
                <x-icon name="chevron-left" class="size-4" /> Back
            </a>
        @endif
        <h1 class="truncate text-2xl font-semibold tracking-tight text-ink">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
