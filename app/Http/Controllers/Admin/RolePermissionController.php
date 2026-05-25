<?php

namespace App\Http\Controllers\Admin;

use App\Commands\Domain\SyncRolePermissionsCommand;
use App\Factories\PermissionPrerequisiteStrategyFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePermissionRequest;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdatePermissionRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;

class RolePermissionController extends Controller
{
    public function indexRoles()
    {
        $roles = RoleResource::collection(
            Role::select(['id', 'name', 'description', 'is_active'])
                ->with('permissions')
                ->where('guard_name', 'admin')
                ->orderBy('name')
                ->get()
        );

        return response()->json(['roles' => $roles]);
    }

    public function storeRole(StoreRoleRequest $request)
    {
        $role = Role::create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description', ''),
            'guard_name' => 'admin',
            'is_active' => $request->validated('is_active', true),
        ]);

        if (! empty($request->validated('permissions'))) {
            app(SyncRolePermissionsCommand::class, [
                'role' => $role,
                'permissionIds' => $request->validated('permissions'),
            ])->execute();
        }

        activity('permission')
            ->causedBy(Auth::guard('admin')->user())
            ->performedOn($role)
            ->withProperties(['name' => $role->name, 'permissions' => $request->validated('permissions', [])])
            ->log("Created role: {$role->name}");

        return response()->json([
            'message' => 'Role created successfully',
            'role' => new RoleResource($role->load('permissions')),
        ], 201);
    }

    public function updateRole(UpdateRoleRequest $request, string $id)
    {
        $role = Role::where('guard_name', 'admin')->findOrFail($id);

        $role->update([
            'name' => $request->validated('name'),
            'description' => $request->validated('description', ''),
            'is_active' => $request->validated('is_active', $role->is_active),
        ]);

        if (isset($request->validated()['permissions'])) {
            app(SyncRolePermissionsCommand::class, [
                'role' => $role,
                'permissionIds' => $request->validated('permissions'),
            ])->execute();
        }

        activity('permission')
            ->causedBy(Auth::guard('admin')->user())
            ->performedOn($role)
            ->withProperties(['name' => $role->name, 'permissions' => $request->validated('permissions', [])])
            ->log("Updated role: {$role->name}");

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => new RoleResource($role->load('permissions')),
        ]);
    }

    public function destroyRole(string $id)
    {
        $role = Role::where('guard_name', 'admin')->findOrFail($id);

        if ($role->users()->exists()) {
            return response()->json(['message' => 'Cannot delete role with assigned users'], 422);
        }

        activity('permission')
            ->causedBy(Auth::guard('admin')->user())
            ->performedOn($role)
            ->withProperties(['name' => $role->name])
            ->log("Deleted role: {$role->name}");

        $role->delete();

        return response()->noContent();
    }

    public function indexPermissions()
    {
        $permissions = Permission::select(['id', 'name', 'description', 'module', 'is_active'])
            ->where('guard_name', 'admin')
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy('module')
            ->map(fn ($perms) => PermissionResource::collection($perms));

        return response()->json(['permissions' => $permissions]);
    }

    public function storePermission(StorePermissionRequest $request)
    {
        $permission = Permission::create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description', ''),
            'module' => $request->validated('module'),
            'guard_name' => 'admin',
            'is_active' => true,
        ]);

        activity('permission')
            ->causedBy(Auth::guard('admin')->user())
            ->performedOn($permission)
            ->withProperties(['name' => $permission->name, 'module' => $permission->module])
            ->log("Created permission: {$permission->name}");

        return response()->json([
            'message' => 'Permission created successfully',
            'permission' => new PermissionResource($permission),
        ], 201);
    }

    public function updatePermission(UpdatePermissionRequest $request, string $id)
    {
        $permission = Permission::where('guard_name', 'admin')->findOrFail($id);
        $permission->update($request->validated());

        activity('permission')
            ->causedBy(Auth::guard('admin')->user())
            ->performedOn($permission)
            ->withProperties(['name' => $permission->name, 'module' => $permission->module])
            ->log("Updated permission: {$permission->name}");

        return response()->json([
            'message' => 'Permission updated successfully',
            'permission' => new PermissionResource($permission),
        ]);
    }

    public function destroyPermission(string $id)
    {
        $permission = Permission::where('guard_name', 'admin')->findOrFail($id);

        if ($permission->roles()->exists()) {
            return response()->json(['message' => 'Cannot delete permission assigned to roles'], 422);
        }

        if ($permission->name === 'manage tenants') {
            $dependentNames = PermissionPrerequisiteStrategyFactory::getManagedPermissions();
            $rolesWithDependents = Role::whereHas('permissions', fn ($q) => $q->whereIn('name', $dependentNames)
            )->where('guard_name', 'admin')->count();

            if ($rolesWithDependents > 0) {
                return response()->json([
                    'message' => "Cannot delete 'manage tenants': {$rolesWithDependents} role(s) have dependent permissions assigned. Remove dependents first.",
                ], 422);
            }
        }

        activity('permission')
            ->causedBy(Auth::guard('admin')->user())
            ->performedOn($permission)
            ->withProperties(['name' => $permission->name, 'module' => $permission->module])
            ->log("Deleted permission: {$permission->name}");

        $permission->delete();

        return response()->noContent();
    }

}
