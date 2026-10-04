@props(['icon' => 'inbox', 'title' => 'Nothing here yet', 'description' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <span class="grid size-12 place-items-center rounded-xl bg-slate-100 text-slate-400">
        <x-icon :name="$icon" class="size-6" />
    </span>
    <h3 class="mt-4 text-sm font-semibold text-ink">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
