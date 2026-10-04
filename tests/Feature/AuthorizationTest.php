<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Role;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function adminOnlyRoutes(): array
    {
        return [
            'staff list' => ['GET', '/staff'],
            'create staff' => ['GET', '/staff/create'],
            'roles' => ['GET', '/roles'],
            'settings' => ['GET', '/settings'],
            'update settings' => ['PUT', '/settings'],
            'audit log' => ['GET', '/activity-log'],
            'profit report' => ['GET', '/reports/profit'],
            'purchases' => ['GET', '/purchases'],
            'create purchase' => ['POST', '/purchases'],
            'expenses' => ['GET', '/expenses'],
            'create medicine' => ['POST', '/medicines'],
            'returns create' => ['GET', '/returns/create'],
        ];
    }

    #[DataProvider('adminOnlyRoutes')]
    public function test_default_staff_role_cannot_reach_restricted_routes(string $method, string $uri): void
    {
        $staff = User::factory()->create(); // seeded "staff" role

        $this->actingAs($staff)->call($method, $uri)->assertForbidden();
    }

    public function test_default_staff_can_use_pos_and_view_medicines(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)->get('/pos')->assertOk();
        $this->actingAs($staff)->get('/medicines')->assertOk();
        $this->actingAs($staff)->get('/dashboard')->assertOk();
    }

    public function test_restricted_navigation_is_hidden_but_enforced_server_side(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)->get('/dashboard')->assertDontSee(route('settings.edit'));
        $this->actingAs($staff)->get(route('settings.edit'))->assertForbidden();
    }

    public function test_user_with_no_permissions_sees_a_welcome_page(): void
    {
        $user = $this->staffWith([]);

        $this->actingAs($user)->get('/')->assertOk()->assertSee('Your role doesn');
        $this->actingAs($user)->get('/dashboard')->assertForbidden();
    }

    public function test_granted_permission_takes_effect(): void
    {
        $user = $this->staffWith(['purchases.view']);

        $this->actingAs($user)->get('/purchases')->assertOk();
        $this->actingAs($user)->get('/purchases/create')->assertForbidden();
    }

    public function test_admin_can_reach_everything(): void
    {
        foreach (self::adminOnlyRoutes() as [$method, $uri]) {
            if ($method === 'GET') {
                $this->actingAs($this->admin())->get($uri)->assertOk();
            }
        }
    }

    public function test_staff_manager_cannot_grant_admin_role(): void
    {
        $manager = $this->staffWith(['staff.manage']);
        $adminRole = Role::query()->where('slug', Role::ADMIN)->first();

        $this->actingAs($manager)->post('/staff', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'role_id' => $adminRole->id,
            'password' => 'Passw0rd-123', 'password_confirmation' => 'Passw0rd-123',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_staff_manager_cannot_promote_themselves(): void
    {
        $manager = $this->staffWith(['staff.manage']);
        $adminRole = Role::query()->where('slug', Role::ADMIN)->first();

        $this->actingAs($manager)->put("/staff/{$manager->id}", [
            'name' => $manager->name, 'email' => $manager->email, 'role_id' => $adminRole->id,
        ])->assertForbidden();

        $this->assertFalse($manager->refresh()->isAdmin());
    }

    public function test_profile_update_cannot_mass_assign_role_or_status(): void
    {
        $staff = User::factory()->create();
        $originalRole = $staff->role_id;
        $adminRole = Role::query()->where('slug', Role::ADMIN)->value('id');

        $this->actingAs($staff)->put('/profile', [
            'name' => 'Changed', 'email' => $staff->email, 'role_id' => $adminRole, 'is_active' => 1, 'pharmacy_id' => 999,
        ])->assertRedirect();

        $staff->refresh();
        $this->assertSame('Changed', $staff->name);
        $this->assertSame($originalRole, $staff->role_id);
        $this->assertNotSame(999, $staff->pharmacy_id);
    }

    public function test_admin_cannot_deactivate_themselves_or_the_last_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post("/staff/{$admin->id}/toggle")->assertSessionHas('error');
        $this->assertTrue($admin->refresh()->is_active);

        $staffRole = Role::query()->where('slug', Role::STAFF)->value('id');
        $this->actingAs($admin)->put("/staff/{$admin->id}", ['name' => 'Admin', 'email' => $admin->email, 'role_id' => $staffRole])
            ->assertSessionHas('error');
        $this->assertTrue($admin->refresh()->isAdmin());
    }

    public function test_admin_role_permissions_cannot_be_edited(): void
    {
        $adminRole = Role::query()->where('slug', Role::ADMIN)->first();

        $this->actingAs($this->admin())->put("/roles/{$adminRole->id}", ['name' => 'Hacked', 'permissions' => []])->assertForbidden();
    }

    public function test_role_permission_changes_are_audited(): void
    {
        $role = Role::factory()->create();

        $this->actingAs($this->admin())->put("/roles/{$role->id}", ['name' => 'Cashier', 'permissions' => ['sales.create', 'sales.view']])
            ->assertRedirect('/roles');

        $this->assertEqualsCanonicalizing(['sales.create', 'sales.view'], $role->permissions()->pluck('name')->all());
        $this->assertDatabaseHas('activity_logs', ['action' => 'role.permissions_changed', 'subject_id' => $role->id]);
    }

    public function test_unknown_permission_names_are_rejected(): void
    {
        $this->actingAs($this->admin())->post('/roles', ['name' => 'X', 'permissions' => ['god.mode']])
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_bulk_archive_requires_delete_permission(): void
    {
        $medicine = Medicine::factory()->create();
        $editor = $this->staffWith(['medicines.view', 'medicines.update']);

        $this->actingAs($editor)->post('/medicines/bulk', ['action' => 'archive', 'ids' => [$medicine->id]])->assertForbidden();
        $this->assertNotSoftDeleted($medicine);

        $this->actingAs($editor)->post('/medicines/bulk', ['action' => 'deactivate', 'ids' => [$medicine->id]])->assertRedirect();
        $this->assertFalse($medicine->refresh()->is_active);
    }
}
