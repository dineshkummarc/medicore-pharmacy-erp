<x-layouts.app title="Profile">
    <x-page-header title="Your profile" description="Update your contact details and password." />

    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('profile.update') }}" class="card p-6">
            @csrf @method('PUT')
            <h2 class="text-base font-semibold">Account details</h2>
            <div class="mt-5 space-y-4">
                <x-input name="name" label="Full name" :value="$user->name" required />
                <x-input name="email" type="email" label="Email" :value="$user->email" required />
                <x-input name="phone" label="Phone" :value="$user->phone" />
                <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    Role: <span class="font-medium text-ink">{{ $user->role?->name }}</span>
                    @if ($user->last_login_at) · Last sign-in {{ $user->last_login_at->diffForHumans() }} @endif
                </div>
            </div>
            <div class="mt-6 flex justify-end"><button class="btn-primary">Save changes</button></div>
        </form>

        <form method="POST" action="{{ route('profile.password') }}" class="card p-6">
            @csrf @method('PUT')
            <h2 class="text-base font-semibold">Change password</h2>
            <p class="mt-1 text-sm text-slate-500">Changing your password signs out your other devices.</p>
            <div class="mt-5 space-y-4">
                <x-input name="current_password" type="password" label="Current password" required autocomplete="current-password" />
                <x-input name="password" type="password" label="New password" required autocomplete="new-password" />
                <x-input name="password_confirmation" type="password" label="Confirm new password" required autocomplete="new-password" />
            </div>
            <div class="mt-6 flex justify-end"><button class="btn-primary">Update password</button></div>
        </form>
    </div>
</x-layouts.app>
