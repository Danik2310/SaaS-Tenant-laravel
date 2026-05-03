<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class StaffController extends Controller
{
    /**
     * Obtener lista de todos los staff.
     */
    public function index()
    {
        try {
            // eager load direct permissions and role permissions to prevent duplicate queries
            $staff = AdminUser::with(['roles.permissions', 'permissions'])
                ->get()
                ->map(function ($admin) {
                    $roleNames = $admin->roles->pluck('name')->toArray();

                    $directPerms = $admin->permissions;
                    $rolePerms = $admin->roles
                        ->flatMap(fn($role) => $role->permissions);

                    $allPerms = $directPerms->merge($rolePerms)->unique('id');

                    return [
                        'id' => $admin->id,
                        'name' => $admin->name,
                        'email' => $admin->email,
                        'is_active' => $admin->is_active,
                        'roles' => $roleNames,
                        'permissions_count' => $allPerms->count(),
                        'permissions' => $allPerms->pluck('name')->toArray(),
                        'created_at' => $admin->created_at,
                        'updated_at' => $admin->updated_at,
                    ];
                });

            return response()->json([
                'staff' => $staff,
                'total' => $staff->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch staff: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener detalles de un usuario staff específico.
     */
    public function show($id)
    {
        try {
            $admin = AdminUser::with('roles.permissions')->findOrFail($id);

            return response()->json([
                'staff' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'is_active' => $admin->is_active,
                    'roles' => $admin->roles->map(fn($role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'permissions' => $role->permissions->pluck('id')->toArray(),
                    ])->toArray(),
                    'direct_permissions' => $admin->permissions->pluck('id')->toArray(),
                    'created_at' => $admin->created_at,
                    'updated_at' => $admin->updated_at,
                ],
                'available_roles' => Role::with('permissions')
                    ->where('guard_name', 'admin')
                    ->get()
                    ->map(fn($role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'description' => $role->description,
                        'permissions' => $role->permissions->map(fn($perm) => [
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

    /**
     * Crear nuevo usuario staff.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admin_users,email',
            'password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
            'roles' => 'sometimes|array',
            'roles.*' => 'exists:roles,id',
            'direct_permissions' => 'sometimes|array',
            'direct_permissions.*' => 'exists:permissions,id',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $admin = AdminUser::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Asignar roles
            if (!empty($validated['roles'])) {
                $roleIds = $validated['roles'];
                $roles = Role::whereIn('id', $roleIds)->get();
                $admin->syncRoles($roles);
            }

            // Asignar permisos directos
            if (!empty($validated['direct_permissions'])) {
                $permissionIds = $validated['direct_permissions'];
                $permissions = Permission::whereIn('id', $permissionIds)->get();
                $admin->syncPermissions($permissions);
            }

            return response()->json([
                'message' => 'Staff member created successfully',
                'staff' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'is_active' => $admin->is_active,
                    'roles' => $admin->roles->pluck('name')->toArray(),
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create staff: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar usuario staff.
     */
    public function update(Request $request, $id)
    {
        $admin = AdminUser::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:admin_users,email,' . $id,
            'password' => ['sometimes', Password::min(8)->mixedCase()->numbers()->symbols()],
            'roles' => 'sometimes|array',
            'roles.*' => 'exists:roles,id',
            'direct_permissions' => 'sometimes|array',
            'direct_permissions.*' => 'exists:permissions,id',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            // Actualizar datos básicos
            if (isset($validated['name'])) {
                $admin->name = $validated['name'];
            }
            if (isset($validated['email'])) {
                $admin->email = $validated['email'];
            }
            if (isset($validated['password'])) {
                $admin->password = Hash::make($validated['password']);
            }
            if (isset($validated['is_active'])) {
                $admin->is_active = $validated['is_active'];
            }

            $admin->save();

            // Actualizar roles
            if (isset($validated['roles'])) {
                $roleIds = $validated['roles'];
                $roles = Role::whereIn('id', $roleIds)->get();
                $admin->syncRoles($roles);
            }

            // Actualizar permisos directos
            if (isset($validated['direct_permissions'])) {
                $permissionIds = $validated['direct_permissions'];
                $permissions = Permission::whereIn('id', $permissionIds)->get();
                $admin->syncPermissions($permissions);
            }

            return response()->json([
                'message' => 'Staff member updated successfully',
                'staff' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'is_active' => $admin->is_active,
                    'roles' => $admin->roles->pluck('name')->toArray(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update staff: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar (soft delete) usuario staff.
     */
    public function destroy($id)
    {
        try {
            $admin = AdminUser::findOrFail($id);

            // Prevenir que se eliminen a sí mismos
            if (auth('admin')->id() === (int)$id) {
                return response()->json(['error' => 'Cannot delete your own account'], 422);
            }

            $admin->delete();

            return response()->json([
                'message' => 'Staff member deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete staff: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Restaurar usuario staff eliminado.
     */
    public function restore($id)
    {
        try {
            $admin = AdminUser::withTrashed()->findOrFail($id);
            $admin->restore();

            return response()->json([
                'message' => 'Staff member restored successfully',
                'staff' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'is_active' => $admin->is_active,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to restore staff: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener todos los roles disponibles.
     */
    public function getRoles()
    {
        try {
            $roles = Role::with('permissions')
                ->where('guard_name', 'admin')
                ->active()
                ->get()
                ->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'permissions_count' => $role->permissions->count(),
                    'permissions' => $role->permissions->map(fn($perm) => [
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
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch roles: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener todos los permisos disponibles.
     */
    public function getPermissions()
    {
        try {
            $permissions = Permission::where('guard_name', 'admin')
                ->active()
                ->orderBy('module')
                ->get()
                ->map(fn($perm) => [
                    'id' => $perm->id,
                    'name' => $perm->name,
                    'description' => $perm->description,
                    'module' => $perm->module ?? 'General',
                ]);

            return response()->json([
                'permissions' => $permissions,
                'total' => $permissions->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch permissions: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Asignar roles a un usuario.
     */
    public function assignRoles(Request $request, $id)
    {
        $admin = AdminUser::findOrFail($id);

        $validated = $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        try {
            $roles = Role::whereIn('id', $validated['role_ids'])->get();
            $admin->syncRoles($roles);

            return response()->json([
                'message' => 'Roles assigned successfully',
                'staff' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'roles' => $admin->roles->pluck('name')->toArray(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to assign roles: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Asignar permisos directos a un usuario.
     */
    public function assignPermissions(Request $request, $id)
    {
        $admin = AdminUser::findOrFail($id);

        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        try {
            $admin->syncPermissions($validated['permission_ids']);

            return response()->json([
                'message' => 'Permissions assigned successfully',
                'staff' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'permissions' => $admin->permissions->pluck('name')->toArray(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to assign permissions: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cambiar estado activo/inactivo.
     */
    public function toggleStatus($id)
    {
        try {
            $admin = AdminUser::findOrFail($id);

            // Prevenir que se desactiven a sí mismos
            if (auth('admin')->id() === (int)$id) {
                return response()->json(['error' => 'Cannot deactivate your own account'], 422);
            }

            $admin->is_active = !$admin->is_active;
            $admin->save();

            return response()->json([
                'message' => 'Staff status updated successfully',
                'staff' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'is_active' => $admin->is_active,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update status: ' . $e->getMessage()], 500);
        }
    }
}
