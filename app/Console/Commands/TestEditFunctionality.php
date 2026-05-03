<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use App\Models\AdminUser;
use App\Models\Role;

class TestEditFunctionality extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-edit-functionality';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the staff edit functionality to ensure it works correctly';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== TEST: Staff Edit Functionality ===');
        $this->newLine();

        // 1. Obtener un staff existente
        $staff = AdminUser::with('roles')->first();
        if (!$staff) {
            $this->error('❌ No staff members found. Please create one first.');
            return 1;
        }

        $this->info("1. Found staff member: {$staff->name} (ID: {$staff->id})");
        $this->info("   Current roles: " . implode(', ', $staff->roles->pluck('name')->toArray()));
        $this->newLine();

        // 2. Simular la llamada al endpoint show() (como hace handleEditClick)
        $this->info("2. Simulating GET /admin/api/staff/{$staff->id} (show endpoint)");

        $admin = AdminUser::with('roles.permissions')->findOrFail($staff->id);
        $showData = [
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
            ],
        ];

        $this->info("   ✅ Show endpoint returns roles with IDs: " . implode(', ', array_column($showData['staff']['roles'], 'id')));
        $this->newLine();

        // 3. Simular cómo StaffForm procesa estos datos
        $this->info('3. Simulating StaffForm data processing');

        $formData = [
            'name' => $showData['staff']['name'],
            'email' => $showData['staff']['email'],
            'password' => '', // No cambiar password
            'roles' => array_map(function($role) {
                if (is_array($role) && isset($role['id'])) {
                    return $role['id'];
                }
                return $role;
            }, $showData['staff']['roles']),
            'is_active' => $showData['staff']['is_active'],
        ];

        $this->info("   ✅ Form data roles (should be IDs): " . implode(', ', $formData['roles']));
        $this->newLine();

        // 4. Simular validación de actualización
        $this->info('4. Simulating form submission validation');

        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admin_users,email,' . $staff->id,
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
            'is_active' => 'boolean',
        ];

        $validator = Validator::make($formData, $validationRules);

        if ($validator->fails()) {
            $this->error('   ❌ Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->error("      - $error");
            }
            return 1;
        } else {
            $this->info('   ✅ Validation passed! Form data is correct.');
        }

        $this->newLine();
        $this->info('=== TEST COMPLETED SUCCESSFULLY ===');

        return 0;
    }
}
