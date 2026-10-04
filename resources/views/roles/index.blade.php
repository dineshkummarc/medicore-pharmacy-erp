<x-layouts.app title="Roles & permissions">
    <x-page-header title="Roles & permissions" description="Group permissions into roles and assign one role to each staff member.">
        <x-slot:actions><a href="{{ route('roles.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> New role</a></x-slot:actions>
    </x-page-header>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($roles as $role)
            <div class="card flex flex-col p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-ink">{{ $role->name }}</h2>
                        <p class="mt-0.5 text-sm text-slate-500">{{ $role->description ?: 'No description' }}</p>
                    </div>
                    @if ($role->is_system)<x-badge tone="violet">System</x-badge>@endif
                </div>
                <div class="mt-4 flex gap-4 text-sm text-slate-600">
                    <span><strong class="text-ink">{{ $role->users_count }}</strong> {{ Str::plural('member', $role->users_count) }}</span>
                    <span><strong class="text-ink">{{ $role->isAdmin() ? 'All' : $role->permissions_count }}</strong> permissions</span>
                </div>
                <div class="mt-auto flex justify-end gap-1 pt-4">
                    @if ($role->isAdmin())
                        <span class="text-xs text-slate-400">Full access, cannot be edited</span>
                    @else
                        <a href="{{ route('roles.edit', $role) }}" class="btn-secondary btn-sm"><x-icon name="pencil" class="size-3.5" /> Edit permissions</a>
                        @unless ($role->is_system)
                            <x-confirm :action="route('roles.destroy', $role)" method="DELETE" :title="'Delete role '.$role->name.'?'" button="Delete" message="Only possible when no staff use this role.">
                                <button type="button" class="btn-ghost btn-sm text-rose-600" aria-label="Delete"><x-icon name="trash" class="size-4" /></button>
                            </x-confirm>
                        @endunless
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.app>
