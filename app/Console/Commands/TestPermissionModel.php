<?php

namespace App\Console\Commands;

use App\Models\Permission;
use Illuminate\Console\Command;

class TestPermissionModel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:permission-model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Permission model instantiation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->info('Testing Permission model...');

            // Test instantiation
            $permission = new Permission;
            $this->info('✓ Permission model instantiated successfully');

            // Test static methods
            $count = Permission::count();
            $this->info("✓ Permission count: {$count}");

            // Test config resolution
            $configModel = config('permission.models.permission');
            $this->info("✓ Config model: {$configModel}");

            // Test if class exists
            if (class_exists($configModel)) {
                $this->info('✓ Config model class exists');
            } else {
                $this->error('✗ Config model class does not exist');
            }

        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());
            $this->error('File: '.$e->getFile().':'.$e->getLine());
        }
    }
}
