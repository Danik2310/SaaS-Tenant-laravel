<?php

namespace Database\Seeders\Central;

use App\Models\AdminUser;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = AdminUser::first();
        if (! $admin) {
            return;
        }

        $tenants = Tenant::pluck('name', 'id')->toArray();
        $tenantIds = array_keys($tenants);

        $logNames = ['tenant', 'staff', 'permission', 'export', 'impersonation', 'plan', 'settings'];
        $descriptions = [
            'tenant' => [
                'Tenant created',
                'Tenant updated',
                'Tenant suspended',
                'Tenant deleted',
                'Tenant restored',
                'Tenant plan changed',
            ],
            'staff' => [
                'Staff user created',
                'Staff user updated',
                'Staff user deactivated',
                'Staff user reactivated',
                'Staff role assigned',
            ],
            'permission' => [
                'Permission created',
                'Permission updated',
                'Permission deleted',
                'Role created',
                'Role updated',
                'Role deleted',
                'Permissions synced to role',
            ],
            'export' => [
                'Tenants export initiated',
                'Tenants export completed',
                'Subscriptions export initiated',
                'Subscriptions export completed',
            ],
            'impersonation' => [
                'Started impersonating tenant',
                'Stopped impersonating tenant',
                'Impersonation session expired',
            ],
            'plan' => [
                'Plan created',
                'Plan updated',
                'Plan deleted',
                'Tenant upgraded to Pro',
                'Tenant downgraded to Free',
            ],
            'settings' => [
                'Global settings updated',
                'System setting changed',
            ],
        ];

        $now = now();
        $batch = [];

        for ($i = 0; $i < 60; $i++) {
            $logName = $logNames[array_rand($logNames)];
            $descPool = $descriptions[$logName];
            $description = $descPool[array_rand($descPool)];

            $subjectId = null;
            $subjectType = null;
            $properties = null;

            if ($logName === 'tenant' && $tenantIds) {
                $subjectId = $tenantIds[array_rand($tenantIds)];
                $subjectType = Tenant::class;
                $properties = json_encode(['tenant_name' => $tenants[$subjectId] ?? 'Unknown']);
            }

            if ($logName === 'export' || $logName === 'impersonation') {
                $properties = json_encode([
                    'date' => $now->copy()->subDays(rand(0, 30))->format('Y-m-d'),
                ]);
            }

            $batch[] = [
                'log_name' => $logName,
                'description' => $description,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'causer_type' => AdminUser::class,
                'causer_id' => $admin->id,
                'properties' => $properties,
                'event' => null,
                'batch_uuid' => null,
                'created_at' => $now->copy()->subHours(rand(0, 720)),
                'updated_at' => $now,
            ];
        }

        DB::table('activity_log')->insert($batch);
    }
}
