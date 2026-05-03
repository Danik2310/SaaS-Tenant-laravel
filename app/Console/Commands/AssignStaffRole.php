<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AssignStaffRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:assign-staff {email?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign staff role to an admin user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        if (!$email) {
            $email = $this->ask('Enter the email of the admin user');
        }

        $user = AdminUser::where('email', $email)->first();

        if (!$user) {
            $this->error("Admin user with email {$email} not found.");
            return 1;
        }

        $role = Role::where('name', 'staff')->where('guard_name', 'web')->first();

        if (!$role) {
            $this->error('Staff role not found. Please run the RolePermissionSeeder first.');
            return 1;
        }

        $user->assignRole($role);

        $this->info("Staff role assigned to {$user->name} ({$user->email})");
        return 0;
    }
}
