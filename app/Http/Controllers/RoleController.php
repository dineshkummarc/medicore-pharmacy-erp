<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(): View
    {
        return view('roles.index', [
            'roles' => Role::query()->withCount(['users', 'permissions'])->orderByDesc('is_system')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('roles.form', ['role' => new Role, 'groups' => config('pharmacy.permissions'), 'granted' => []]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = DB::transaction(function () use ($data) {
            $role = new Role(['name' => $data['name'], 'description' => $data['description'] ?? null]);
            $role->slug = $this->uniqueSlug($data['name']);
            $role->save();
            $role->permissions()->sync(Permission::query()->whereIn('name', $data['permissions'] ?? [])->pluck('id'));

            return $role;
        });

        $this->logger->log('role.created', "Role {$role->name} created", $role, ['permissions' => $data['permissions'] ?? []]);

        return redirect()->route('roles.index')->with('success', "Role {$role->name} created.");
    }

    public function edit(Role $role): View|RedirectResponse
    {
        if ($role->isAdmin()) {
            return redirect()->route('roles.index')->with('error', 'The administrator role always has every permission.');
        }

        return view('roles.form', [
            'role' => $role,
            'groups' => config('pharmacy.permissions'),
            'granted' => $role->permissions()->pluck('name')->all(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        abort_if($role->isAdmin(), 403, 'The administrator role cannot be modified.');

        $data = $request->validated();
        $before = $role->permissions()->pluck('name')->all();

        DB::transaction(function () use ($role, $data) {
            $role->fill(['name' => $data['name'], 'description' => $data['description'] ?? null])->save();
            $role->permissions()->sync(Permission::query()->whereIn('name', $data['permissions'] ?? [])->pluck('id'));
        });

        $after = $data['permissions'] ?? [];
        $this->logger->log('role.permissions_changed', "Role {$role->name} updated", $role, [
            'granted' => array_values(array_diff($after, $before)),
            'revoked' => array_values(array_diff($before, $after)),
        ]);

        return redirect()->route('roles.index')->with('success', "Role {$role->name} updated.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'Reassign the staff using this role before deleting it.');
        }

        $role->delete();
        $this->logger->log('role.deleted', "Role {$role->name} deleted", null, ['role' => $role->name]);

        return back()->with('success', 'Role deleted.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $i = 2;

        while (Role::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
