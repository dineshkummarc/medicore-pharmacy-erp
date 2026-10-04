<x-layouts.app title="Staff">
    <x-page-header title="Staff" description="Accounts that can sign in to the system, and what they can do.">
        <x-slot:actions>
            @can('roles.manage')<a href="{{ route('roles.index') }}" class="btn-secondary"><x-icon name="shield" class="size-4" /> Roles</a>@endcan
            <a href="{{ route('staff.create') }}" class="btn-primary"><x-icon name="user-plus" class="size-4" /> Add staff</a>
        </x-slot:actions>
    </x-page-header>
    <div class="card overflow-hidden">
        <x-filters placeholder="Name or email…">
            <x-filter-select name="role" :options="$roles->pluck('name', 'id')" placeholder="Any role" />
            <x-filter-select name="status" :options="['active' => 'Active', 'inactive' => 'Deactivated']" placeholder="Any status" />
        </x-filters>
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Name</th><th>Role</th><th class="hidden md:table-cell">Last sign-in</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($staff as $member)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ $member->initials() }}</span>
                                <div class="min-w-0"><p class="font-medium text-ink">{{ $member->name }} @if($member->is(auth()->user()))<span class="text-xs font-normal text-slate-400">(you)</span>@endif</p><p class="truncate text-xs text-slate-500">{{ $member->email }}</p></div>
                            </div>
                        </td>
                        <td><x-badge :tone="$member->role?->isAdmin() ? 'violet' : 'brand'">{{ $member->role?->name }}</x-badge></td>
                        <td class="hidden md:table-cell text-slate-500">{{ $member->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                        <td><x-badge :tone="$member->is_active ? 'green' : 'gray'" dot>{{ $member->is_active ? 'Active' : 'Deactivated' }}</x-badge></td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('staff.edit', $member) }}" class="btn-ghost btn-sm" aria-label="Edit {{ $member->name }}"><x-icon name="pencil" class="size-4" /></a>
                            @unless ($member->is(auth()->user()))
                                <x-confirm :action="route('staff.toggle', $member)" :title="($member->is_active ? 'Deactivate ' : 'Activate ').$member->name.'?'" :button="$member->is_active ? 'Deactivate' : 'Activate'"
                                           :tone="$member->is_active ? 'danger' : 'primary'" :message="$member->is_active ? 'They are signed out immediately and cannot sign in until reactivated. Their records stay intact.' : 'They will be able to sign in again.'">
                                    <button type="button" class="btn-ghost btn-sm {{ $member->is_active ? 'text-rose-600' : 'text-emerald-600' }}" aria-label="Toggle access"><x-icon name="power" class="size-4" /></button>
                                </x-confirm>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $staff->links() }}
    </div>
</x-layouts.app>
