<?php

namespace App\Shared\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRolesRequest;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\StaffResource;
use App\Models\AdminUser;
use App\Models\Permission;
use App\Models\Role;
use App\Shared\Commands\SyncStaffRolesCommand;
use Illuminate\Support\Facades\Hash;

/**
 * @group Staff Management
 *
 * APIs for managing admin staff members.
 */
class StaffController extends Controller
{
    /**
     * List all staff.
     *
     * Paginated list of admin staff members with their roles and permissions.
     *
     * @authenticated
     */
    public function index()
    {
        $query = AdminUser::with([
            'roles' => fn ($q) => $q->where('guard_name', 'admin'),
            'roles.permissions' => fn ($q) => $q->where('guard_name', 'admin'),
            'permissions' => fn ($q) => $q->where('guard_name', 'admin'),
        ]);

        if ($search = request('search')) {
            $search = str_replace(['%', '_'], ['\%', '\_'], $search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (request()->has('is_active')) {
            $query->where('is_active', request()->boolean('is_active'));
        }

        $sortableColumns = ['name', 'email', 'is_active', 'created_at'];
        $sort = in_array(request('sort', 'created_at'), $sortableColumns) ? request('sort', 'created_at') : 'created_at';
        $order = request('order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $order);

        $perPage = min((int) request('per_page', 5), 100);
        $staff = $query->paginate($perPage);

        return response()->json([
            'staff' => StaffResource::collection($staff->items()),
            'total' => $staff->total(),
        ]);
    }

    /**
     * Get a single staff member.
     *
     * @authenticated
     *
     * @urlParam id integer required The staff member ID.
     */
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
            return response()->json(['message' => 'Staff member not found'], 404);
        }
    }

    /**
     * Create a staff member.
     *
     * @authenticated
     *
     * @bodyParam name string required Staff member name.
     * @bodyParam email string required Staff member email.
     * @bodyParam password string required Initial password.
     * @bodyParam roles integer[] optional Array of role IDs.
     *
     * @response 201 {"message":"Staff member created successfully","staff":{"id":1,"name":"John","email":"john@example.com"}}
     */
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

        $admin->load('roles.permissions', 'permissions');

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

    /**
     * Update a staff member.
     *
     * @authenticated
     *
     * @urlParam id integer required The staff member ID.
     *
     * @bodyParam name string optional Staff member name.
     * @bodyParam email string optional Staff member email.
     * @bodyParam password string optional New password.
     * @bodyParam roles integer[] optional Array of role IDs.
     */
    public function update(UpdateStaffRequest $request, string $id)
    {
        $admin = AdminUser::with('roles.permissions', 'permissions')->findOrFail($id);

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

        $admin->load('roles.permissions', 'permissions');

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

    /**
     * Delete a staff member.
     *
     * @authenticated
     *
     * @urlParam id integer required The staff member ID.
     *
     * @response 204 No content.
     */
    public function destroy(string $id)
    {
        $admin = AdminUser::findOrFail($id);

        if (auth('admin')->id() === (int) $id) {
            return response()->json(['message' => 'Cannot delete your own account'], 422);
        }

        activity('staff')
            ->causedBy(auth('admin')->user())
            ->performedOn($admin)
            ->withProperties(['name' => $admin->name])
            ->log("Deleted staff member: {$admin->name}");

        $admin->delete();

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted staff member.
     *
     * @authenticated
     *
     * @urlParam id integer required The staff member ID.
     *
     * @responseField message string Success message.
     * @responseField staff object The restored staff resource.
     */
    public function restore(string $id)
    {
        $admin = AdminUser::withTrashed()->with('roles.permissions', 'permissions')->findOrFail($id);
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

    /**
     * Get available roles.
     *
     * List all active admin roles with permission counts.
     *
     * @authenticated
     *
     * @responseField roles object[] List of role resources with permission counts.
     */
    public function getRoles()
    {
        $roles = Role::with('permissions')
            ->where('guard_name', 'admin')
            ->active()
            ->orderBy('name')
            ->paginate(5);

        return response()->json([
            'roles' => RoleResource::collection($roles),
            'meta' => [
                'total' => $roles->total(),
            ],
        ]);
    }

    /**
     * Get available permissions.
     *
     * List all active admin permissions grouped by module.
     *
     * @authenticated
     *
     * @responseField permissions object[] List of permissions with module grouping.
     */
    public function getPermissions()
    {
        $permissions = Permission::where('guard_name', 'admin')
            ->active()
            ->orderBy('module')
            ->paginate(5);

        $mapped = $permissions->map(fn ($perm) => [
            'id' => $perm->id,
            'name' => $perm->name,
            'description' => $perm->description,
            'module' => $perm->module ?? 'General',
        ]);

        return response()->json([
            'permissions' => $mapped,
            'meta' => [
                'current_page' => $permissions->currentPage(),
                'last_page' => $permissions->lastPage(),
                'per_page' => $permissions->perPage(),
                'total' => $permissions->total(),
            ],
        ]);
    }

    /**
     * Assign roles to a staff member.
     *
     * @authenticated
     *
     * @urlParam id integer required The staff member ID.
     *
     * @bodyParam role_ids integer[] required Array of role IDs.
     *
     * @responseField message string Success message.
     * @responseField staff object The updated staff resource.
     */
    public function assignRoles(AssignRolesRequest $request, string $id)
    {
        $admin = AdminUser::with('roles.permissions', 'permissions')->findOrFail($id);

        $command = app(SyncStaffRolesCommand::class, [
            'user' => $admin,
            'roleIds' => $request->validated('role_ids'),
        ]);
        $command->execute();

        $admin->load('roles.permissions', 'permissions');

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

    /**
     * Toggle staff member active status.
     *
     * @authenticated
     *
     * @urlParam id integer required The staff member ID.
     *
     * @responseField message string Success message.
     * @responseField staff object The updated staff resource.
     */
    public function toggleStatus(string $id)
    {
        $admin = AdminUser::with('roles.permissions', 'permissions')->findOrFail($id);

        if (auth('admin')->id() === (int) $id) {
            return response()->json(['message' => 'Cannot deactivate your own account'], 422);
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
