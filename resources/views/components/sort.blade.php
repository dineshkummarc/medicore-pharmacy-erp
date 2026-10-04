{{-- Sortable column header that preserves the current query string. --}}
@props(['key', 'label', 'align' => 'left'])
@php
    $current = request('sort');
    $direction = request('direction', 'desc');
    $next = $current === $key && $direction === 'asc' ? 'desc' : 'asc';
    $url = request()->fullUrlWithQuery(['sort' => $key, 'direction' => $next, 'page' => null]);
@endphp
<th scope="col" @if($current === $key) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif class="{{ $align === 'right' ? 'text-right' : '' }}">
    <a href="{{ $url }}" class="group inline-flex items-center gap-1 hover:text-slate-700">
        {{ $label }}
        <span class="{{ $current === $key ? 'text-brand-600' : 'text-slate-300 group-hover:text-slate-400' }}">
            @if ($current === $key)
                <x-icon name="{{ $direction === 'asc' ? 'chevron-down' : 'chevron-down' }}" class="size-3.5 {{ $direction === 'asc' ? 'rotate-180' : '' }}" stroke="2.2" />
            @else
                <x-icon name="sort" class="size-3.5" stroke="2" />
            @endif
        </span>
    </a>
</th>
