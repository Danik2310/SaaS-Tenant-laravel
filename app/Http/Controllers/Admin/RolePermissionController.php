<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    /**
     * List all roles with their permissions.
     */
    public function indexRoles()
    {
        $roles = Role::with('permissions')
            ->where('guard_name', 'admin')
            ->orderBy('name')
            ->get()
            ->map(fn($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_active' => $role->is_active,
                'permissions_count' => $role->permissions->count(),
                'permissions' => $role->permissions->map(fn($perm) => [
                    'id' => $perm->id,
                    'name' => $perm->name,
                    'module' => $perm->module,
                ])->toArray(),
                'created_at' => $role->created_at,
            ]);

        return response()->json(['roles' => $roles]);
    }

    /**
     * Create a new role.
     */
    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string|max:500',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'exists:permissions,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'guard_name' => 'admin',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (!empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return response()->json([
            'message' => 'Role created successfully',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_active' => $role->is_active,
                'permissions_count' => $role->permissions->count(),
            ],
        ], 201);
    }

    /**
     * Update a role.
     */
    public function updateRole(Request $request, $id)
    {
        $role = Role::where('guard_name', 'admin')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $id,
            'description' => 'nullable|string|max:500',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'exists:permissions,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'is_active' => $validated['is_active'] ?? $role->is_active,
        ]);

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_active' => $role->is_active,
                'permissions_count' => $role->permissions->count(),
            ],
        ]);
    }

    /**
     * Delete a role.
     */
    public function destroyRole($id)
    {
        $role = Role::where('guard_name', 'admin')->findOrFail($id);

        $hasUsers = $role->users()->exists();
        if ($hasUsers) {
            return response()->json(['message' => 'Cannot delete role with assigned users'], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully']);
    }

    /**
     * List all available permissions grouped by module.
     */
    public function indexPermissions()
    {
        $permissions = Permission::where('guard_name', 'admin')
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy('module')
            ->map(fn($perms) => $perms->map(fn($perm) => [
                'id' => $perm->id,
                'name' => $perm->name,
                'description' => $perm->description,
                'is_active' => $perm->is_active,
            ]));

        return response()->json(['permissions' => $permissions]);
    }

    /**
     * Create a new permission.
     */
    public function storePermission(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
            'description' => 'nullable|string|max:500',
            'module' => 'required|string|max:100',
        ]);

        $permission = Permission::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'module' => $validated['module'],
            'guard_name' => 'admin',
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Permission created successfully',
            'permission' => $permission,
        ], 201);
    }

    /**
     * Update a permission.
     */
    public function updatePermission(Request $request, $id)
    {
        $permission = Permission::where('guard_name', 'admin')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,' . $id,
            'description' => 'nullable|string|max:500',
            'module' => 'required|string|max:100',
        ]);

        $permission->update($validated);

        return response()->json([
            'message' => 'Permission updated successfully',
            'permission' => $permission,
        ]);
    }

    /**
     * Delete a permission.
     */
    public function destroyPermission($id)
    {
        $permission = Permission::where('guard_name', 'admin')->findOrFail($id);

        $hasRoles = $permission->roles()->exists();
        if ($hasRoles) {
            return response()->json(['message' => 'Cannot delete permission assigned to roles'], 422);
        }

        $permission->delete();

        return response()->json(['message' => 'Permission deleted successfully']);
    }
}
