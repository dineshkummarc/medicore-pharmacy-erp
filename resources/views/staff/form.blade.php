@php $editing = $staff->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit staff' : 'Add staff'">
    <x-page-header :title="$editing ? 'Edit '.$staff->name : 'Add staff member'" :back="route('staff.index')" />
    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ $editing ? route('staff.update', $staff) : route('staff.store') }}" class="card p-6">
            @csrf @if ($editing) @method('PUT') @endif
            <h2 class="text-base font-semibold">Account</h2>
            <div class="mt-5 space-y-4">
                <x-input name="name" label="Full name" :value="$staff->name" required maxlength="120" />
                <x-input name="email" type="email" label="Email (used to sign in)" :value="$staff->email" required />
                <x-input name="phone" type="tel" label="Phone" :value="$staff->phone" maxlength="30" />
                <x-select name="role_id" label="Role" :options="$roles->pluck('name', 'id')" :value="$staff->role_id" placeholder="Select a role…" required
                          :disabled="$editing && $staff->is(auth()->user())" hint="Permissions come from the role. Manage them under Roles & permissions." />
                @if ($editing && $staff->is(auth()->user()))<input type="hidden" name="role_id" value="{{ $staff->role_id }}">@endif
                @unless ($editing)
                    <x-input name="password" type="password" label="Temporary password" required autocomplete="new-password" />
                    <x-input name="password_confirmation" type="password" label="Confirm password" required autocomplete="new-password" />
                    <x-toggle name="is_active" label="Active" :checked="true" description="Inactive accounts cannot sign in." />
                @endunless
            </div>
            <div class="mt-6 flex justify-end gap-2"><a href="{{ route('staff.index') }}" class="btn-secondary">Cancel</a><button class="btn-primary">{{ $editing ? 'Save changes' : 'Create account' }}</button></div>
        </form>

        @if ($editing)
            <form method="POST" action="{{ route('staff.password', $staff) }}" class="card self-start p-6">
                @csrf @method('PUT')
                <h2 class="text-base font-semibold">Reset password</h2>
                <p class="mt-1 text-sm text-slate-500">Sets a new password and signs the user out of other sessions. Share it securely.</p>
                <div class="mt-5 space-y-4">
                    <x-input name="password" type="password" label="New password" required autocomplete="new-password" id="reset-password" />
                    <x-input name="password_confirmation" type="password" label="Confirm" required autocomplete="new-password" id="reset-password-confirmation" />
                </div>
                <div class="mt-6 flex justify-end"><button class="btn-secondary"><x-icon name="key" class="size-4" /> Reset password</button></div>
                <p class="mt-4 text-xs text-slate-500">Last sign-in: {{ $staff->last_login_at?->format('d M Y, H:i') ?? 'never' }} @if($staff->last_login_ip) from {{ $staff->last_login_ip }} @endif</p>
            </form>
        @endif
    </div>
</x-layouts.app>
