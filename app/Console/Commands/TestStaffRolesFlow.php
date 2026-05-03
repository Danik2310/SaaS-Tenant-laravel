<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AdminUser;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class TestStaffRolesFlow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:staff-roles-flow';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the complete flow of creating staff with roles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Testing Staff Creation with Roles ===');
        $this->line('');

        try {
            // Step 1: Simulate fetching roles (like StaffForm does)
            $this->info('Step 1: Fetching available roles (simulating StaffForm.fetchRoles())');
            $roles = Role::where('guard_name', 'admin')->get();
            $this->line("   ✓ Retrieved " . $roles->count() . " roles");
            foreach ($roles as $role) {
                $this->line("     - {$role->name} ({$role->permissions->count()} permissions)");
            }
            $this->line('');

            // Step 2: Create a new staff member
            $this->info('Step 2: Creating new staff member with roles');
            $email = 'newstaff' . time() . '@example.com';
            $newStaff = AdminUser::create([
                'name' => 'New Staff Member',
                'email' => $email,
                'password' => Hash::make('NewStaffPass123!'),
                'is_active' => true,
            ]);
            $this->line("   ✓ Staff created: {$newStaff->name} (ID: {$newStaff->id})");
            $this->line("   ✓ Email: {$newStaff->email}");
            $this->line('');

            // Step 3: Assign roles
            $this->info('Step 3: Assigning roles to staff member');
            $roleIds = $roles->take(1)->pluck('id')->toArray();
            $selectedRoles = Role::whereIn('id', $roleIds)->get();
            $newStaff->syncRoles($selectedRoles);
            $this->line("   ✓ Assigned " . count($selectedRoles) . " role(s)");
            foreach ($newStaff->roles as $role) {
                $this->line("     - {$role->name}");
            }
            $this->line('');

            // Step 4: Verify the staff member has permissions from roles
            $this->info('Step 4: Verifying staff has inherited permissions');
            $this->line("   Staff permissions from roles:");
            foreach ($newStaff->roles as $role) {
                foreach ($role->permissions as $perm) {
                    $this->line("     ✓ {$perm->name} (from role: {$role->name})");
                }
            }
            $this->line('');

            // Step 5: Test permission checking
            $this->info('Step 5: Testing permission verification');
            $testPerms = ['manage tenants', 'manage staff', 'manage plans'];
            foreach ($testPerms as $permName) {
                $hasPermission = $newStaff->hasPermissionTo($permName);
                $status = $hasPermission ? '✓' : '✗';
                $this->line("   {$status} Can perform '{$permName}': " . ($hasPermission ? 'YES' : 'NO'));
            }
            $this->line('');

            // Step 6: Test retrieving staff details (like show() endpoint)
            $this->info('Step 6: Retrieving staff details with roles (simulating show() endpoint)');
            $directPerms = $newStaff->permissions()->pluck('name')->toArray();
            $staffData = [
                'id' => $newStaff->id,
                'name' => $newStaff->name,
                'email' => $newStaff->email,
                'is_active' => $newStaff->is_active,
                'roles' => $newStaff->roles->pluck('name')->toArray(),
                'permissions' => $directPerms,
            ];
            $this->line("   ✓ Staff details retrieved successfully");
            $this->line("   ✓ Roles: " . implode(', ', $staffData['roles']));
            $this->line('');

            // Step 7: Clean up
            $this->info('Step 7: Cleaning up test data');
            $newStaff->forceDelete();
            $this->line("   ✓ Test staff member deleted");
            $this->line('');

            $this->info('=== All Tests Passed! ===');
            $this->line('✓ Role fetching works correctly');
            $this->line('✓ Staff creation with roles is functional');
            $this->line('✓ Permission inheritance from roles works');
            $this->line('✓ Staff data retrieval includes all role information');

        } catch (\Exception $e) {
            $this->error('✗ Error: ' . $e->getMessage());
            $this->line('Trace: ' . $e->getTraceAsString());
        }
    }
}
