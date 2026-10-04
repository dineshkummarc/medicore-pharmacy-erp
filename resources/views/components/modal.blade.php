{{-- Accessible modal. Open with $dispatch('open-modal', 'name'). --}}
@props(['name', 'title' => null, 'maxWidth' => 'md'])
@php
    $widths = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl'];
@endphp
<div x-data="{ show: @js($attributes->get('open', false)) }"
     x-on:open-modal.window="if ($event.detail === @js($name)) show = true"
     x-on:close-modal.window="if ($event.detail === @js($name)) show = false"
     x-on:keydown.escape.window="show = false"
     x-cloak x-show="show" class="fixed inset-0 z-[70] overflow-y-auto no-print" role="dialog" aria-modal="true" @if($title) aria-label="{{ $title }}" @endif>
    <div x-show="show" x-transition.opacity class="fixed inset-0 bg-slate-900/40 backdrop-blur-[2px]" @click="show = false"></div>
    <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
        <div x-show="show" x-trap.noscroll="show"
             x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             class="relative w-full {{ $widths[$maxWidth] ?? $widths['md'] }} rounded-2xl bg-white shadow-pop">
            @if ($title)
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
                    <button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" @click="show = false" aria-label="Close">
                        <x-icon name="x" class="size-5" />
                    </button>
                </div>
            @endif
            <div class="p-5">{{ $slot }}</div>
        </div>
    </div>
</div>
