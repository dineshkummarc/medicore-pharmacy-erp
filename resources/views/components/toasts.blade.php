{{-- Flash messages become toasts; validation errors get a summary toast. --}}
@php
    $flash = collect([
        ['success', session('success')],
        ['success', session('status')],
        ['error', session('error')],
    ])->filter(fn ($f) => filled($f[1]))->values();

    if ($errors->any() && ! session('error')) {
        $flash->push(['error', $errors->count() === 1 ? $errors->first() : 'Please fix the '.$errors->count().' highlighted fields.']);
    }
@endphp
<div x-data x-init='@foreach ($flash as [$type, $message]) $store.toasts.push(@json($message), @json($type)); @endforeach'
     class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:bottom-auto sm:top-4 sm:items-end no-print"
     aria-live="polite" role="status">
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-2 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border bg-white p-3.5 pr-2.5 shadow-pop"
             :class="toast.type === 'error' ? 'border-rose-200' : 'border-slate-200'">
            <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full"
                  :class="toast.type === 'error' ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600'">
                <svg x-show="toast.type !== 'error'" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                <svg x-show="toast.type === 'error'" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 3h.01" /></svg>
            </span>
            <p class="flex-1 text-sm text-slate-700" x-text="toast.message"></p>
            <button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" @click="$store.toasts.dismiss(toast.id)" aria-label="Dismiss">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
        </div>
    </template>
</div>
