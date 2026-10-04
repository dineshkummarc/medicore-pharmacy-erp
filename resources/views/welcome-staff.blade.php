<x-layouts.app title="Welcome">
    <div class="card">
        <x-empty icon="lock" title="Welcome, {{ auth()->user()->name }}" description="Your role doesn't have access to any modules yet. Ask an administrator to grant permissions to your role." />
    </div>
</x-layouts.app>
