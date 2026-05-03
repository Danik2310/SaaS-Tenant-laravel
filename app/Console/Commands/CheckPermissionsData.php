<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckPermissionsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:permissions-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check permissions and roles data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== PERMISOS ===');
        $permissions = \App\Models\Permission::all();
        foreach ($permissions as $permission) {
            $this->line("{$permission->name} ({$permission->guard_name})");
            $this->line("  Módulo: {$permission->module}");
            $this->line("  Descripción: {$permission->description}");
            $this->line("  Activo: " . ($permission->is_active ? 'Sí' : 'No'));
            $this->line('');
        }

        $this->info('=== ROLES ===');
        $roles = \App\Models\Role::all();
        foreach ($roles as $role) {
            $this->line("{$role->name} ({$role->guard_name})");
            $this->line("  Descripción: {$role->description}");
            $this->line("  Activo: " . ($role->is_active ? 'Sí' : 'No'));
            $this->line("  Permisos: " . $role->permissions->pluck('name')->join(', '));
            $this->line('');
        }
    }
}
