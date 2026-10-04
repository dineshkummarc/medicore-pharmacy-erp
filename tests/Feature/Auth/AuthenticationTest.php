<?php

namespace Tests\Feature\Auth;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/pos')->assertRedirect('/login');
        $this->postJson('/pos', [])->assertUnauthorized();
    }

    public function test_user_can_log_in_and_it_is_audited_without_the_password(): void
    {
        $response = $this->post('/login', ['email' => 'admin@pharmacy.test', 'password' => 'Secret-Passw0rd']);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->admin());
        $this->assertNotNull($this->admin()->last_login_at);

        $log = ActivityLog::query()->where('action', 'auth.login')->firstOrFail();
        $this->assertStringNotContainsString('Secret-Passw0rd', json_encode($log->toArray()));
    }

    public function test_wrong_password_is_rejected_and_logged(): void
    {
        $this->post('/login', ['email' => 'admin@pharmacy.test', 'password' => 'wrong'])->assertSessionHasErrors('email');

        $this->assertGuest();
        $log = ActivityLog::query()->where('action', 'auth.failed')->firstOrFail();
        $this->assertArrayNotHasKey('password', $log->properties ?? []);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create(['password' => 'Passw0rd-123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'Passw0rd-123'])
            ->assertSessionHasErrors(['email' => 'Your account has been deactivated. Contact your administrator.']);
        $this->assertGuest();
    }

    public function test_user_deactivated_mid_session_is_signed_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->forceFill(['is_active' => false])->save();

        $this->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'admin@pharmacy.test', 'password' => 'nope-'.$i]);
        }

        // Even the correct password is refused while locked out.
        $this->post('/login', ['email' => 'admin@pharmacy.test', 'password' => 'Secret-Passw0rd'])
            ->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $this->actingAs($this->admin())->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.logout']);
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = $this->admin();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        // Same answer for unknown emails (no account enumeration).
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassw0rd99',
            'password_confirmation' => 'NewPassw0rd99',
        ])->assertRedirect('/login');

        $this->post('/login', ['email' => $user->email, 'password' => 'NewPassw0rd99'])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_reset_with_invalid_token_fails(): void
    {
        $this->post('/reset-password', [
            'token' => 'invalid', 'email' => 'admin@pharmacy.test',
            'password' => 'NewPassw0rd99', 'password_confirmation' => 'NewPassw0rd99',
        ])->assertSessionHasErrors('email');
    }

    public function test_password_change_requires_current_password(): void
    {
        $this->actingAs($this->admin())
            ->put('/profile/password', ['current_password' => 'wrong', 'password' => 'NewPassw0rd99', 'password_confirmation' => 'NewPassw0rd99'])
            ->assertSessionHasErrors('current_password');
    }
}
