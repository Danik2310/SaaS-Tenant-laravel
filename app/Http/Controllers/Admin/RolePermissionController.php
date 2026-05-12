<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePermissionRequest;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdatePermissionRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;

class RolePermissionController extends Controller
{
    public function indexRoles()
    {
        $roles = RoleResource::collection(
            Role::with('permissions')
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
            $role->syncPermissions($request->validated('permissions'));
        }

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
            $role->syncPermissions($request->validated('permissions'));
        }

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

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully']);
    }

    public function indexPermissions()
    {
        $permissions = Permission::where('guard_name', 'admin')
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

        return response()->json([
            'message' => 'Permission created successfully',
            'permission' => new PermissionResource($permission),
        ], 201);
    }

    public function updatePermission(UpdatePermissionRequest $request, string $id)
    {
        $permission = Permission::where('guard_name', 'admin')->findOrFail($id);
        $permission->update($request->validated());

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

        $permission->delete();

        return response()->json(['message' => 'Permission deleted successfully']);
    }
}
