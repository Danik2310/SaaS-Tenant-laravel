<?php

namespace App\Shared\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePermissionRequest;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdatePermissionRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Shared\Commands\SyncRolePermissionsCommand;
use App\Shared\Factories\PermissionPrerequisiteStrategyFactory;
use Illuminate\Support\Facades\Auth;

/**
 * @group Role & Permission Management
 *
 * APIs for managing admin roles and permissions.
 */
class RolePermissionController extends Controller
{
    /**
     * List all roles.
     *
     * Paginated list of admin roles with their permissions.
     *
     * @authenticated
     */
    public function indexRoles()
    {
        $roles = Role::select(['id', 'name', 'description', 'is_active'])
            ->with('permissions')
            ->where('guard_name', 'admin')
            ->orderBy('name')
            ->paginate(50);

        return response()->json([
            'roles' => RoleResource::collection($roles->items()),
            'meta' => [
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
            ],
        ]);
    }

    /**
     * Create a role.
     *
     * @authenticated
     *
     * @bodyParam name string required Role name.
     * @bodyParam description string optional Role description.
     * @bodyParam permissions integer[] optional Array of permission IDs.
     *
     * @response 201 {"message":"Role created successfully","role":{...}}
     */
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

    /**
     * Update a role.
     *
     * @authenticated
     *
     * @urlParam id integer required The role ID.
     *
     * @bodyParam name string required Role name.
     * @bodyParam description string optional Role description.
     * @bodyParam permissions integer[] optional Array of permission IDs.
     *
     * @responseField message string Success message.
     * @responseField role object The updated role resource.
     */
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

    /**
     * Delete a role.
     *
     * @authenticated
     *
     * @urlParam id integer required The role ID.
     *
     * @response 204 No content.
     *
     * @throws 422 If role has assigned users.
     */
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

    /**
     * List all permissions.
     *
     * Paginated list grouped by module.
     *
     * @authenticated
     */
    public function indexPermissions()
    {
        $permissions = Permission::select(['id', 'name', 'description', 'module', 'is_active'])
            ->where('guard_name', 'admin')
            ->orderBy('module')
            ->orderBy('name')
            ->paginate(100);

        $grouped = collect($permissions->items())
            ->groupBy('module')
            ->map(fn ($perms) => PermissionResource::collection($perms));

        return response()->json([
            'permissions' => $grouped,
            'meta' => [
                'current_page' => $permissions->currentPage(),
                'last_page' => $permissions->lastPage(),
                'per_page' => $permissions->perPage(),
                'total' => $permissions->total(),
            ],
        ]);
    }

    /**
     * Create a permission.
     *
     * @authenticated
     *
     * @bodyParam name string required Permission name.
     * @bodyParam module string required Permission module.
     * @bodyParam description string optional Permission description.
     *
     * @response 201 {"message":"Permission created successfully","permission":{...}}
     */
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

    /**
     * Update a permission.
     *
     * @authenticated
     *
     * @urlParam id integer required The permission ID.
     *
     * @bodyParam name string required Permission name.
     * @bodyParam module string required Permission module.
     * @bodyParam description string optional Permission description.
     *
     * @responseField message string Success message.
     * @responseField permission object The updated permission resource.
     */
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

    /**
     * Delete a permission.
     *
     * @authenticated
     *
     * @urlParam id integer required The permission ID.
     *
     * @response 204 No content.
     *
     * @throws 422 If permission is assigned to roles.
     */
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
