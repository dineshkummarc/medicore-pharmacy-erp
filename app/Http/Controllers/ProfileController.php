<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user->fill($validated)->save();
        $logger->log('profile.updated', "{$user->name} updated their profile", $user);

        return back()->with('success', 'Profile updated.');
    }

    public function password(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $validated['password']])->save();

        // Sign out other devices that might be using the old password.
        $request->session()->regenerate();
        $logger->log('auth.password_changed', "{$user->name} changed their password", $user);

        return back()->with('success', 'Password changed.');
    }
}
