{{--
    Destructive action behind a confirmation dialog.
    <x-confirm :action="route(...)" method="DELETE" title="Archive?" message="..." button="Archive">trigger</x-confirm>
--}}
@props(['action', 'method' => 'POST', 'title' => 'Are you sure?', 'message' => null, 'button' => 'Confirm', 'tone' => 'danger', 'reason' => false])
<div x-data="{ open: false }" class="inline">
    <span @click="open = true">{{ $slot }}</span>
    <template x-teleport="body">
        <div x-cloak x-show="open" class="fixed inset-0 z-[70] overflow-y-auto" role="alertdialog" aria-modal="true" @keydown.escape.window="open = false">
            <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/40 backdrop-blur-[2px]" @click="open = false"></div>
            <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
                <form x-show="open" x-trap.noscroll="open" x-transition method="POST" action="{{ $action }}" class="relative w-full rounded-2xl bg-white p-6 shadow-pop sm:max-w-md">
                    @csrf
                    @if (strtoupper($method) !== 'POST') @method($method) @endif
                    <div class="flex gap-4">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full {{ $tone === 'danger' ? 'bg-rose-100 text-rose-600' : 'bg-brand-100 text-brand-700' }}">
                            <x-icon name="{{ $tone === 'danger' ? 'warning' : 'info' }}" class="size-5" />
                        </span>
                        <div class="flex-1">
                            <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
                            @if ($message)<p class="mt-1.5 text-sm text-slate-600">{{ $message }}</p>@endif
                            @if ($reason)
                                <label class="field-label mt-4" for="reason-{{ md5($action) }}">Reason</label>
                                <input id="reason-{{ md5($action) }}" name="reason" required minlength="3" maxlength="255" class="field-input" placeholder="Why is this being done?">
                            @endif
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" class="btn-secondary" @click="open = false">Cancel</button>
                        <button type="submit" class="{{ $tone === 'danger' ? 'btn-danger' : 'btn-primary' }}">{{ $button }}</button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
