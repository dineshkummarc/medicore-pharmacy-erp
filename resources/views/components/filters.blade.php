{{-- Filter bar wrapper: a GET form that keeps the current sort. --}}
@props(['action' => null, 'search' => true, 'placeholder' => 'Search…'])
<form method="GET" action="{{ $action ?? url()->current() }}" {{ $attributes->merge(['class' => 'flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center']) }} role="search">
    @foreach (['sort', 'direction', 'per_page'] as $keep)
        @if (request()->filled($keep))<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif
    @endforeach
    @if ($search)
        <div class="relative flex-1 lg:max-w-xs">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
            <label class="sr-only" for="filter-q">Search</label>
            <input id="filter-q" type="search" name="q" value="{{ request('q') }}" placeholder="{{ $placeholder }}" class="field-input pl-9">
        </div>
    @endif
    <div class="flex flex-1 flex-wrap items-center gap-2">
        {{ $slot }}
    </div>
    <div class="flex items-center gap-2">
        <button type="submit" class="btn-secondary"><x-icon name="filter" class="size-4" /> Apply</button>
        @if (collect(request()->except(['sort', 'direction', 'page', 'per_page']))->filter()->isNotEmpty())
            <a href="{{ $action ?? url()->current() }}" class="btn-ghost">Reset</a>
        @endif
    </div>
</form>
