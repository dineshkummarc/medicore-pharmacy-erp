{{-- Shared screen for categories, manufacturers and expense categories. --}}
@php
    $isManufacturer = $type === 'manufacturers';
    $countField = $type === 'expense-categories' ? 'expenses_count' : 'medicines_count';
    $countLabel = $type === 'expense-categories' ? 'Expenses' : 'Medicines';
    $routeParam = fn ($item) => $item;
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :description="$type === 'expense-categories' ? 'Group expenses for reporting.' : 'Organise your catalogue for search and reports.'">
        <x-slot:actions>
            <button type="button" class="btn-primary" @click="$dispatch('open-modal', 'create-item')"><x-icon name="plus" class="size-4" /> Add</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        @if ($type !== 'expense-categories')
            <x-filters placeholder="Search by name…" />
        @endif
        @if ($items->isEmpty())
            <x-empty icon="tag" title="Nothing here yet" description="Add the first entry to get started." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Name</th>@if($isManufacturer)<th class="hidden md:table-cell">Country</th><th class="hidden md:table-cell">Contact</th>@elseif($type === 'categories')<th class="hidden md:table-cell">Description</th>@endif<th class="text-right">{{ $countLabel }}</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($items as $item)
                        <tr>
                            <td class="font-medium text-ink">{{ $item->name }}</td>
                            @if ($isManufacturer)
                                <td class="hidden md:table-cell text-slate-500">{{ $item->country ?: '—' }}</td>
                                <td class="hidden md:table-cell text-slate-500">{{ $item->phone ?: $item->email ?: '—' }}</td>
                            @elseif ($type === 'categories')
                                <td class="hidden md:table-cell max-w-sm truncate text-slate-500">{{ $item->description ?: '—' }}</td>
                            @endif
                            <td class="tabular text-right">{{ number_format($item->{$countField}) }}</td>
                            <td><x-badge :tone="$item->is_active ? 'green' : 'gray'" dot>{{ $item->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1">
                                    <button type="button" class="btn-ghost btn-sm" @click="$dispatch('open-modal', 'edit-{{ $item->id }}')" aria-label="Edit {{ $item->name }}"><x-icon name="pencil" class="size-4" /></button>
                                    <x-confirm :action="route($type.'.destroy', $item)" method="DELETE" :title="'Archive '.$item->name.'?'" button="Archive"
                                               message="It will no longer be offered in forms. Existing records keep their reference.">
                                        <button type="button" class="btn-ghost btn-sm text-rose-600" aria-label="Archive {{ $item->name }}"><x-icon name="trash" class="size-4" /></button>
                                    </x-confirm>
                                </div>
                                <x-modal :name="'edit-'.$item->id" :title="'Edit '.$item->name">
                                    <form method="POST" action="{{ route($type.'.update', $item) }}" class="space-y-4 text-left">
                                        @csrf @method('PUT')
                                        @include('catalog._fields', ['item' => $item, 'prefix' => 'e'.$item->id])
                                        <div class="flex justify-end gap-2"><button class="btn-primary">Save</button></div>
                                    </form>
                                </x-modal>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $items->links() }}
        @endif
    </div>

    <x-modal name="create-item" :title="'Add '.Str::singular(strtolower($title))" :open="$errors->any()">
        <form method="POST" action="{{ route($type.'.store') }}" class="space-y-4">
            @csrf
            @include('catalog._fields', ['item' => null, 'prefix' => 'new'])
            <div class="flex justify-end gap-2"><button class="btn-primary">Add</button></div>
        </form>
    </x-modal>
</x-layouts.app>
