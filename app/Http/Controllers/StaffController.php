<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesTableQueries;
use App\Http\Requests\StaffRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StaffController extends Controller
{
    use HandlesTableQueries;

    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(Request $request): View
    {
        $term = $request->string('q')->limit(80)->value();

        $staff = User::query()
            ->with('role')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('email', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->when($request->integer('role'), fn ($q, $id) => $q->where('role_id', $id))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('staff.index', [
            'staff' => $staff,
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['q', 'role', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('staff.form', ['staff' => new User, 'roles' => $this->assignableRoles()]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $this->guardRoleAssignment($request, (int) $request->validated('role_id'));

        $user = new User($request->safe()->only(['name', 'email', 'phone', 'password']));
        $user->role_id = (int) $request->validated('role_id');
        $user->is_active = $request->boolean('is_active', true);
        $user->save();

        $this->logger->log('staff.created', "Staff account {$user->email} created", $user, ['role' => $user->role->name]);

        return redirect()->route('staff.index')->with('success', "Account for {$user->name} created.");
    }

    public function edit(User $staff): View
    {
        return view('staff.form', ['staff' => $staff, 'roles' => $this->assignableRoles()]);
    }

    public function update(StaffRequest $request, User $staff): RedirectResponse
    {
        $roleId = (int) $request->validated('role_id');
        $this->guardRoleAssignment($request, $roleId);

        if ($staff->is($request->user()) && $roleId !== $staff->role_id) {
            return back()->with('error', 'You cannot change your own role.');
        }

        DB::transaction(function () use ($request, $staff, $roleId) {
            $staff->fill($request->safe()->only(['name', 'email', 'phone']));

            if ($request->filled('password')) {
                $staff->password = $request->validated('password');
            }

            $roleChanged = $staff->role_id !== $roleId;
            $staff->role_id = $roleId;
            $this->ensureAnAdminRemains($staff);
            $staff->save();

            $this->logger->log('staff.updated', "Staff account {$staff->email} updated", $staff, array_merge(
                $this->logger->changes($staff),
                $roleChanged ? ['role_changed' => true] : [],
            ));
        });

        return redirect()->route('staff.index')->with('success', 'Account updated.');
    }

    public function toggle(Request $request, User $staff): RedirectResponse
    {
        if ($staff->is($request->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        DB::transaction(function () use ($staff) {
            $staff->is_active = ! $staff->is_active;
            $this->ensureAnAdminRemains($staff);
            $staff->save();
        });

        $this->logger->log($staff->is_active ? 'staff.activated' : 'staff.deactivated', "Account {$staff->email} ".($staff->is_active ? 'activated' : 'deactivated'), $staff);

        return back()->with('success', "{$staff->name} is now ".($staff->is_active ? 'active' : 'inactive').'.');
    }

    /**
     * Admin-initiated password reset for a staff member.
     */
    public function password(Request $request, User $staff): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'confirmed', Password::defaults()]]);

        $staff->forceFill(['password' => $data['password'], 'remember_token' => null])->save();
        $this->logger->log('staff.password_reset', "Password reset for {$staff->email}", $staff);

        return back()->with('success', "Password for {$staff->name} has been reset. Their other sessions are signed out.");
    }

    /**
     * Only admins may grant the admin role (prevents privilege escalation by
     * a staff manager who is not an admin).
     */
    private function guardRoleAssignment(Request $request, int $roleId): void
    {
        $role = Role::query()->findOrFail($roleId);

        abort_if($role->isAdmin() && ! $request->user()->isAdmin(), 403, 'Only administrators can grant the administrator role.');
    }

    private function ensureAnAdminRemains(User $user): void
    {
        $adminRoleId = Role::query()->where('slug', Role::ADMIN)->value('id');

        $remaining = User::query()
            ->where('role_id', $adminRoleId)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->lockForUpdate()
            ->count();

        $stillAdmin = $user->role_id === $adminRoleId && $user->is_active;

        if ($remaining === 0 && ! $stillAdmin) {
            abort(422, 'At least one active administrator is required.');
        }
    }

    private function assignableRoles()
    {
        return Role::query()
            ->when(! request()->user()->isAdmin(), fn ($q) => $q->where('slug', '!=', Role::ADMIN))
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }
}
