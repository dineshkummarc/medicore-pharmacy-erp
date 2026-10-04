{{-- Date range picker with presets; keeps other query params. --}}
@php
    $presets = ['today' => 'Today', 'yesterday' => 'Yesterday', 'last7' => 'Last 7 days', 'month' => 'This month', 'last_month' => 'Last month', 'last30' => 'Last 30 days', 'year' => 'This year', 'last_year' => 'Last year'];
@endphp
<form method="GET" class="card mb-6 flex flex-col gap-3 p-4 lg:flex-row lg:items-end">
    @foreach (request()->except(['from', 'to', 'range', 'page', 'export']) as $k => $v)
        @if (is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
    @endforeach
    <div class="flex flex-wrap gap-1.5">
        @foreach ($presets as $key => $label)
            <a href="{{ request()->fullUrlWithQuery(['range' => $key, 'from' => null, 'to' => null, 'page' => null]) }}"
               class="rounded-lg px-2.5 py-1.5 text-xs font-medium {{ request('range') === $key ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">{{ $label }}</a>
        @endforeach
    </div>
    <div class="flex flex-wrap items-end gap-2 lg:ml-auto">
        <div><label for="r-from" class="field-label text-xs">From</label><input id="r-from" type="date" name="from" value="{{ $from->toDateString() }}" class="field-input py-1.5"></div>
        <div><label for="r-to" class="field-label text-xs">To</label><input id="r-to" type="date" name="to" value="{{ $to->toDateString() }}" class="field-input py-1.5"></div>
        {{ $slot ?? '' }}
        <button class="btn-secondary">Apply</button>
    </div>
</form>
