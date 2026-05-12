<?php

namespace App\Console\Commands;

use App\Http\Controllers\StaffController;
use App\Models\AdminUser;
use Illuminate\Console\Command;

class TestEditFlow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-edit-flow';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the complete staff edit flow from clicking Edit to form loading';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== TESTING COMPLETE STAFF EDIT FLOW ===');
        $this->newLine();

        // 1. Get a staff member
        $staff = AdminUser::with('roles')->first();
        if (! $staff) {
            $this->error('❌ No staff members found');

            return 1;
        }

        $this->info("1. Found staff member: {$staff->name} (ID: {$staff->id})");
        $this->info('   Current roles: '.implode(', ', $staff->roles->pluck('name')->toArray()));
        $this->newLine();

        // 2. Simulate clicking Edit (call show endpoint directly)
        $this->info('2. Simulating click on Edit button (calling StaffController@show directly)');

        try {
            $controller = app()->make(StaffController::class);
            $response = $controller->show($staff->id);
            $data = json_decode($response->getContent(), true);

            $this->info('   ✅ Controller call successful');
            $this->newLine();

            // 3. Check response structure
            $this->info('3. Checking response structure');

            if (! isset($data['staff'])) {
                $this->error('❌ Response missing "staff" key');

                return 1;
            }

            $staffData = $data['staff'];
            $this->info('   ✅ Response contains "staff" object');
            $this->info("   Staff name: {$staffData['name']}");
            $this->info("   Staff email: {$staffData['email']}");
            $this->info('   Is active: '.($staffData['is_active'] ? 'Yes' : 'No'));
            $this->newLine();

            // 4. Check roles structure
            $this->info('4. Checking roles structure');

            if (! isset($staffData['roles']) || ! is_array($staffData['roles'])) {
                $this->error('❌ Staff data missing "roles" array');

                return 1;
            }

            $roles = $staffData['roles'];
            $this->info('   ✅ Found '.count($roles).' role(s)');

            foreach ($roles as $role) {
                if (! isset($role['id']) || ! isset($role['name'])) {
                    $this->error('❌ Role missing id or name: '.json_encode($role));

                    return 1;
                }
                $this->info("   Role: {$role['name']} (ID: {$role['id']})");
            }
            $this->newLine();

            // 5. Simulate StaffForm processing
            $this->info('5. Simulating StaffForm data processing');

            $formData = [
                'name' => $staffData['name'],
                'email' => $staffData['email'],
                'password' => '',
                'roles' => array_map(function ($role) {
                    if (is_array($role) && isset($role['id'])) {
                        return $role['id'];
                    }

                    return $role;
                }, $staffData['roles']),
                'is_active' => $staffData['is_active'],
            ];

            $this->info('   ✅ Form data processed successfully');
            $this->info("   Name: {$formData['name']}");
            $this->info("   Email: {$formData['email']}");
            $this->info('   Roles (IDs): '.implode(', ', $formData['roles']));
            $this->info('   Is active: '.($formData['is_active'] ? 'Yes' : 'No'));
            $this->newLine();

            // 6. Check available roles
            $this->info('6. Checking available roles for form');

            if (! isset($data['available_roles']) || ! is_array($data['available_roles'])) {
                $this->error('❌ Response missing "available_roles" array');

                return 1;
            }

            $availableRoles = $data['available_roles'];
            $this->info('   ✅ Found '.count($availableRoles).' available role(s) for selection');

            foreach ($availableRoles as $role) {
                $this->info("   Available: {$role['name']} (ID: {$role['id']})");
            }

            $this->newLine();
            $this->info('=== EDIT FLOW TEST COMPLETED SUCCESSFULLY ===');
            $this->info('The form should now load with all existing data pre-filled.');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Exception: '.$e->getMessage());

            return 1;
        }
    }
}
