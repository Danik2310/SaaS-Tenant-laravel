<?php

namespace App\Http\Controllers;

use App\Commands\Domain\SyncStaffRolesCommand;
use App\Http\Requests\Admin\AssignRolesRequest;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\AdminUser;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    public function index()
    {
        $staff = AdminUser::with([
            'roles' => fn ($q) => $q->where('guard_name', 'admin'),
            'roles.permissions' => fn ($q) => $q->where('guard_name', 'admin'),
            'permissions' => fn ($q) => $q->where('guard_name', 'admin'),
        ])->paginate(25);

        return response()->json([
            'staff' => StaffResource::collection($staff->items()),
            'total' => $staff->total(),
            'meta' => [
                'current_page' => $staff->currentPage(),
                'last_page' => $staff->lastPage(),
                'per_page' => $staff->perPage(),
                'total' => $staff->total(),
            ],
        ]);
    }

    public function show(string $id)
    {
        try {
            $admin = AdminUser::with('roles.permissions')->findOrFail($id);

            return response()->json([
                'staff' => new StaffResource($admin),
                'available_roles' => Role::with('permissions')
                    ->where('guard_name', 'admin')
                    ->get()
                    ->map(fn ($role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'description' => $role->description,
                        'permissions' => $role->permissions->map(fn ($perm) => [
                            'id' => $perm->id,
                            'name' => $perm->name,
                            'description' => $perm->description,
                            'module' => $perm->module,
                        ])->toArray(),
                    ])->toArray(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Staff member not found'], 404);
        }
    }

    public function store(StoreStaffRequest $request)
    {
        $admin = AdminUser::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'is_active' => $request->validated('is_active', true),
        ]);

        if (! empty($request->validated('roles'))) {
            app(SyncStaffRolesCommand::class, [
                'user' => $admin,
                'roleIds' => $request->validated('roles'),
            ])->execute();
        }

        activity('staff')
            ->causedBy(auth('admin')->user())
            ->performedOn($admin)
            ->withProperties(['name' => $admin->name, 'email' => $admin->email])
            ->log("Created staff member: {$admin->name}");

        return response()->json([
            'message' => 'Staff member created successfully',
            'staff' => new StaffResource($admin),
        ], 201);
    }

    public function update(UpdateStaffRequest $request, string $id)
    {
        $admin = AdminUser::findOrFail($id);

        if ($name = $request->validated('name')) {
            $admin->name = $name;
        }
        if ($email = $request->validated('email')) {
            $admin->email = $email;
        }
        if ($password = $request->validated('password')) {
            $admin->password = Hash::make($password);
        }
        if (isset($request->validated()['is_active'])) {
            $admin->is_active = $request->validated('is_active');
        }

        $admin->save();

        if (isset($request->validated()['roles'])) {
            app(SyncStaffRolesCommand::class, [
                'user' => $admin,
                'roleIds' => $request->validated('roles'),
            ])->execute();
        }

        activity('staff')
            ->causedBy(auth('admin')->user())
            ->performedOn($admin)
            ->withProperties(['name' => $admin->name, 'email' => $admin->email])
            ->log("Updated staff member: {$admin->name}");

        return response()->json([
            'message' => 'Staff member updated successfully',
            'staff' => new StaffResource($admin),
        ]);
    }

    public function destroy(string $id)
    {
        $admin = AdminUser::findOrFail($id);

        if (auth('admin')->id() === (int) $id) {
            return response()->json(['error' => 'Cannot delete your own account'], 422);
        }

        activity('staff')
            ->causedBy(auth('admin')->user())
            ->performedOn($admin)
            ->withProperties(['name' => $admin->name])
            ->log("Deleted staff member: {$admin->name}");

        $admin->delete();

        return response()->noContent();
    }

    public function restore(string $id)
    {
        $admin = AdminUser::withTrashed()->findOrFail($id);
        $admin->restore();

        activity('staff')
            ->causedBy(auth('admin')->user())
            ->performedOn($admin)
            ->withProperties(['name' => $admin->name])
            ->log("Restored staff member: {$admin->name}");

        return response()->json([
            'message' => 'Staff member restored successfully',
            'staff' => new StaffResource($admin),
        ]);
    }

    public function getRoles()
    {
        $roles = Role::with('permissions')
            ->where('guard_name', 'admin')
            ->active()
            ->get()
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'permissions_count' => $role->permissions->count(),
                'permissions' => $role->permissions->map(fn ($perm) => [
                    'id' => $perm->id,
                    'name' => $perm->name,
                    'description' => $perm->description,
                    'module' => $perm->module,
                ])->toArray(),
            ]);

        return response()->json([
            'roles' => $roles,
            'total' => $roles->count(),
        ]);
    }

    public function getPermissions()
    {
        $permissions = Permission::where('guard_name', 'admin')
            ->active()
            ->orderBy('module')
            ->get()
            ->map(fn ($perm) => [
                'id' => $perm->id,
                'name' => $perm->name,
                'description' => $perm->description,
                'module' => $perm->module ?? 'General',
            ]);

        return response()->json([
            'permissions' => $permissions,
            'total' => $permissions->count(),
        ]);
    }

    public function assignRoles(AssignRolesRequest $request, string $id)
    {
        $admin = AdminUser::findOrFail($id);

        $command = app(SyncStaffRolesCommand::class, [
            'user' => $admin,
            'roleIds' => $request->validated('role_ids'),
        ]);
        $command->execute();

        $roles = Role::whereIn('id', $request->validated('role_ids'))
            ->where('guard_name', 'admin')
            ->pluck('name');
        $roleNames = $roles->implode(', ');

        activity('staff')
            ->performedOn($admin)
            ->causedBy(auth('admin')->user())
            ->withProperties(['staff_name' => $admin->name, 'roles' => $roleNames])
            ->log("Assigned roles to {$admin->name}: {$roleNames}");

        return response()->json([
            'message' => 'Roles assigned successfully',
            'staff' => new StaffResource($admin),
        ]);
    }

    public function toggleStatus(string $id)
    {
        $admin = AdminUser::findOrFail($id);

        if (auth('admin')->id() === (int) $id) {
            return response()->json(['error' => 'Cannot deactivate your own account'], 422);
        }

        $admin->is_active = ! $admin->is_active;
        $admin->save();

        $statusLabel = $admin->is_active ? 'activated' : 'deactivated';

        activity('staff')
            ->performedOn($admin)
            ->causedBy(auth('admin')->user())
            ->withProperties(['staff_name' => $admin->name, 'is_active' => $admin->is_active])
            ->log("{$statusLabel} staff member: {$admin->name}");

        return response()->json([
            'message' => 'Staff status updated successfully',
            'staff' => new StaffResource($admin),
        ]);
    }

}
