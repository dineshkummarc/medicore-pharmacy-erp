{{-- Ranked horizontal bars in plain HTML. Every value is printed, so the bar is never the only encoding. --}}
@props(['rows' => [], 'empty' => 'No data for this period.'])
@php $max = max(1, collect($rows)->max('value') ?? 1); @endphp
@if (count($rows))
    <ul {{ $attributes->merge(['class' => 'space-y-3']) }}>
        @foreach ($rows as $row)
            <li title="{{ $row['label'] }}: {{ $row['display'] }}">
                <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                    <span class="truncate text-slate-700">{{ $row['label'] }}</span>
                    <span class="tabular shrink-0 font-medium text-ink">{{ $row['display'] }}</span>
                </div>
                <div class="h-2 rounded-full bg-slate-100">
                    <div class="h-2 rounded-full bg-brand-500" style="width: {{ max(2, round($row['value'] / $max * 100, 1)) }}%"></div>
                </div>
                @isset($row['meta'])<p class="mt-1 text-xs text-slate-500">{{ $row['meta'] }}</p>@endisset
            </li>
        @endforeach
    </ul>
@else
    <p class="py-8 text-center text-sm text-slate-500">{{ $empty }}</p>
@endif
