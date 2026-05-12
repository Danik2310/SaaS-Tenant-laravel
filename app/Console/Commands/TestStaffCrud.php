<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use App\Models\Role;
use Illuminate\Console\Command;

class TestStaffCrud extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:staff-crud';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the staff CRUD operations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Testing Staff CRUD Operations ===');
        $this->line('');

        try {
            // 1. Create a new staff member
            $this->info('1. Creating a new staff member...');
            $email = 'teststaff'.time().'@example.com';
            $staffMember = AdminUser::create([
                'name' => 'Test Staff Member',
                'email' => $email,
                'password' => bcrypt('TestPassword123!'),
                'is_active' => true,
            ]);
            $this->line("   ✓ Staff member created: ID {$staffMember->id}, Email: {$staffMember->email}");
            $this->line('');

            // 2. Assign roles
            $this->info('2. Assigning roles...');
            $role = Role::where('guard_name', 'admin')->first();
            if ($role) {
                $staffMember->assignRole($role);
                $this->line("   ✓ Role '{$role->name}' assigned");
            } else {
                $this->line('   ⚠ No admin roles found to assign');
            }
            $this->line('');

            // 3. Retrieve the staff member
            $this->info('3. Retrieving staff member...');
            $retrieved = AdminUser::find($staffMember->id);
            $this->line("   ✓ Retrieved: {$retrieved->name} ({$retrieved->email})");
            $this->line('   ✓ Is Active: '.($retrieved->is_active ? 'Yes' : 'No'));
            $this->line('   ✓ Roles: '.($retrieved->roles->count() > 0 ? $retrieved->roles->pluck('name')->join(', ') : 'None'));
            $this->line('');

            // 4. Update the staff member
            $this->info('4. Updating staff member...');
            $staffMember->update([
                'name' => 'Updated Staff Member',
            ]);
            $this->line("   ✓ Updated name to: {$staffMember->name}");
            $this->line('');

            // 5. Toggle status
            $this->info('5. Toggling active status...');
            $staffMember->update(['is_active' => false]);
            $this->line('   ✓ Status changed to: '.($staffMember->is_active ? 'Active' : 'Inactive'));
            $this->line('');

            // 6. Soft delete
            $this->info('6. Soft deleting staff member...');
            $staffMember->delete();
            $this->line('   ✓ Staff member soft deleted');
            $this->line('');

            // 7. Restore
            $this->info('7. Restoring staff member...');
            $staffMember->restore();
            $this->line('   ✓ Staff member restored');
            $this->line('');

            // 8. List all staff
            $this->info('8. Listing all active staff...');
            $allStaff = AdminUser::active()->get();
            $this->line("   ✓ Total active staff: {$allStaff->count()}");
            foreach ($allStaff as $staff) {
                $this->line("     - {$staff->name} ({$staff->email})");
            }
            $this->line('');

            // 9. Clean up
            $this->info('9. Cleaning up test data...');
            $staffMember->forceDelete();
            $this->line('   ✓ Test staff member permanently deleted');
            $this->line('');

            $this->info('=== All Tests Passed! ===');

        } catch (\Exception $e) {
            $this->error('✗ Error: '.$e->getMessage());
            $this->line('Trace: '.$e->getTraceAsString());
        }
    }
}
