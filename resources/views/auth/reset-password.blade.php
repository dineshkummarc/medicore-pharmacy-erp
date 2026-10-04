<x-layouts.guest title="Choose a new password">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">Choose a new password</h1>
    <p class="mt-1.5 text-sm text-slate-500">Use at least 8 characters with letters and numbers.</p>

    <form method="POST" action="{{ route('password.store') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-input name="email" type="email" label="Email address" :value="$request->email" required autocomplete="username" />
        <x-input name="password" type="password" label="New password" required autocomplete="new-password" />
        <x-input name="password_confirmation" type="password" label="Confirm new password" required autocomplete="new-password" />
        <button type="submit" class="btn-primary w-full py-2.5">Reset password</button>
    </form>
</x-layouts.guest>
