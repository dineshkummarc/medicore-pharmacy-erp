<x-layouts.app title="Audit log">
    <x-page-header title="Audit log" description="Who did what, and when. Entries cannot be edited or deleted from the application." />
    <div class="card overflow-hidden">
        <x-filters placeholder="Search descriptions…">
            <x-filter-select name="action" :options="collect($actions)->mapWithKeys(fn ($a) => [$a => ucfirst($a)])" placeholder="All areas" />
            <x-filter-select name="user" :options="$users->pluck('name', 'id')" placeholder="Anyone" />
            <x-date-range />
        </x-filters>
        @if ($logs->isEmpty())
            <x-empty icon="clipboard" title="No activity found" />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($logs as $log)
                    @php
                        $area = strtok($log->action, '.');
                        $tone = match (true) {
                            str_contains($log->action, 'failed') || str_contains($log->action, 'cancel') || str_contains($log->action, 'deactivated') || str_contains($log->action, 'deleted') || str_contains($log->action, 'archived') => 'red',
                            $area === 'auth' => 'gray',
                            in_array($area, ['sale', 'invoice']) => 'blue',
                            in_array($area, ['staff', 'role', 'settings']) => 'violet',
                            default => 'brand',
                        };
                    @endphp
                    <li class="flex flex-col gap-1 px-5 py-3.5 sm:flex-row sm:items-start sm:gap-4" x-data="{ open: false }">
                        <time class="w-36 shrink-0 text-xs text-slate-500" datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('d M Y, H:i:s') }}</time>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-ink"><x-badge :tone="$tone" class="mr-1.5 font-mono text-[11px]">{{ $log->action }}</x-badge>{{ $log->description }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $log->user?->name ?? 'System / guest' }} · {{ $log->ip_address ?? '—' }}</p>
                            @if ($log->properties)
                                <button type="button" class="mt-1 text-xs link" @click="open = !open" x-text="open ? 'Hide details' : 'Show details'"></button>
                                <pre x-cloak x-show="open" class="mt-2 max-h-64 overflow-auto rounded-lg bg-slate-50 p-3 text-[11px] text-slate-700">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            {{ $logs->links() }}
        @endif
    </div>
</x-layouts.app>
