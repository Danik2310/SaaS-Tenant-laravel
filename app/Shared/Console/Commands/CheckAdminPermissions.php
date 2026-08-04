<?php

namespace App\Shared\Console\Commands;

use App\Models\AdminUser;
use App\Shared\Constants\PermissionNames;
use Illuminate\Console\Command;

class CheckAdminPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:admin-permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check admin user permissions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $user = AdminUser::first();

        if (! $user) {
            $this->error('No admin user found');

            return;
        }

        $this->info('Admin User: '.$user->name.' ('.$user->email.')');
        $this->info('Roles: '.$user->roles->pluck('name')->join(', '));

        $permissions = [
            PermissionNames::VIEW_TENANTS,
            PermissionNames::CREATE_TENANTS,
            PermissionNames::EDIT_TENANTS,
            PermissionNames::DELETE_TENANTS,
            PermissionNames::VIEW_STAFF,
            PermissionNames::MANAGE_PROFILE,
        ];

        foreach ($permissions as $permission) {
            $hasPermission = $user->hasPermissionTo($permission);
            $this->info("Has '{$permission}' permission: ".($hasPermission ? 'YES' : 'NO'));
        }
    }
}
