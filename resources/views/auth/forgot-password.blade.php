<x-layouts.guest title="Reset password">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">Forgot your password?</h1>
    <p class="mt-1.5 text-sm text-slate-500">Enter your account email and we'll send you a reset link.</p>

    @if (session('status'))
        <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf
        <x-input name="email" type="email" label="Email address" required autofocus />
        <button type="submit" class="btn-primary w-full py-2.5">Email reset link</button>
    </form>
    <a href="{{ route('login') }}" class="mt-6 inline-flex items-center gap-1 text-sm font-medium text-slate-600 hover:text-ink">
        <x-icon name="chevron-left" class="size-4" /> Back to sign in
    </a>
</x-layouts.guest>
