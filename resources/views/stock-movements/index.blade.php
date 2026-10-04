<x-layouts.app title="Stock movements">
    <x-page-header title="Stock movements" description="The complete, append-only ledger of every change to inventory." />
    <div class="card overflow-hidden">
        <x-filters placeholder="Search medicine…">
            @if (request('medicine'))<input type="hidden" name="medicine" value="{{ request('medicine') }}">@endif
            <x-filter-select name="type" :options="collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()])" placeholder="All types" />
            <x-date-range />
        </x-filters>
        @include('stock-movements._table', ['movements' => $movements, 'showMedicine' => true])
        {{ $movements->links() }}
    </div>
</x-layouts.app>
