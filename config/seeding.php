<?php

declare(strict_types=1);

return [
    'demo' => [
        /*
         * Target row count for tenant-facing demo tables. A plan limit that is
         * lower than this value always wins, so essential plan gating stays real.
         */
        'records_per_tenant_table' => 10,

        /*
         * Only the canonical tenants are seeded. Domain suffixes are appended by
         * TenantSeeder so this file does not depend on tenancy config load order.
         */
        'tenants' => [
            [
                'key' => 'empresa-abc',
                'name' => 'Empresa ABC',
                'email' => 'contacto@empresaabc.com',
                'domain' => 'empresaabc',
                'plan' => 'free',
                'status' => 'Active',
            ],
            [
                'key' => 'tienda-xyz',
                'name' => 'Tienda XYZ',
                'email' => 'admin@tiendaxyz.com',
                'domain' => 'tiendaxyz',
                'plan' => 'pro',
                'status' => 'Active',
            ],
            [
                'key' => 'consultoria-123',
                'name' => 'Consultoría 123',
                'email' => 'info@consultoria123.com',
                'domain' => 'consultoria123',
                'plan' => 'enterprise',
                'status' => 'Active',
            ],
        ],
    ],
];
