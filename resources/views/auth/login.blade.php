<x-layouts.guest title="Sign in">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">Welcome back</h1>
    <p class="mt-1.5 text-sm text-slate-500">Sign in with your staff account to continue.</p>

    @if (session('status'))
        <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5" x-data="{ busy: false }" @submit="busy = true">
        @csrf
        <x-input name="email" type="email" label="Email address" autocomplete="username" required autofocus />
        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label for="f-password" class="text-[13px] font-medium text-slate-700">Password</label>
                <a href="{{ route('password.request') }}" class="text-[13px] font-medium text-brand-700 hover:text-brand-800">Forgot password?</a>
            </div>
            <x-input name="password" type="password" autocomplete="current-password" required />
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            Keep me signed in on this device
        </label>
        <button type="submit" class="btn-primary w-full py-2.5" :disabled="busy">
            <span x-show="!busy">Sign in</span>
            <span x-cloak x-show="busy">Signing in…</span>
        </button>
    </form>
    <p class="mt-10 text-xs text-slate-400">Accounts are created by your pharmacy administrator.</p>
</x-layouts.guest>
