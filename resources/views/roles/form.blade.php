@php $editing = $role->exists; $selected = old('permissions', $granted); @endphp
<x-layouts.app :title="$editing ? 'Edit role' : 'New role'">
    <x-page-header :title="$editing ? 'Edit '.$role->name : 'New role'" :back="route('roles.index')" description="Changes apply to every staff member with this role on their next request." />
    <form method="POST" action="{{ $editing ? route('roles.update', $role) : route('roles.store') }}" class="space-y-6" x-data="{ selected: @js(array_values($selected)) }">
        @csrf @if ($editing) @method('PUT') @endif
        <section class="card grid gap-4 p-6 sm:grid-cols-2">
            <x-input name="name" label="Role name" :value="$role->name" required maxlength="60" />
            <x-input name="description" label="Description" :value="$role->description" maxlength="255" />
        </section>
        <section class="card p-6">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold">Permissions</h2>
                <span class="text-sm text-slate-500"><span x-text="selected.length"></span> selected</span>
            </div>
            @error('permissions.*')<p class="field-error">{{ $message }}</p>@enderror
            <div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($groups as $group => $permissions)
                    <fieldset class="rounded-xl border border-slate-200 p-4">
                        <legend class="px-1 text-sm font-semibold text-ink">{{ $group }}</legend>
                        <div class="mt-1 space-y-2.5">
                            @foreach ($permissions as $name => $label)
                                <label class="flex cursor-pointer items-start gap-2.5 text-sm">
                                    <input type="checkbox" name="permissions[]" value="{{ $name }}" x-model="selected" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                    <span><span class="text-slate-700">{{ $label }}</span><span class="block font-mono text-[11px] text-slate-400">{{ $name }}</span></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </section>
        <div class="flex justify-end gap-2"><a href="{{ route('roles.index') }}" class="btn-secondary">Cancel</a><button class="btn-primary">{{ $editing ? 'Save role' : 'Create role' }}</button></div>
    </form>
</x-layouts.app>
