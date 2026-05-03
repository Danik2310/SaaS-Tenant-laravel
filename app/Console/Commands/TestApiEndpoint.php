<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestApiEndpoint extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:api-endpoint';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test API endpoint for tenants';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing /admin/api/tenants endpoint...');
        
        // Simulate authenticated request
        $user = \App\Models\AdminUser::first();
        
        if (!$user) {
            $this->error('No admin user found');
            return;
        }
        
        // Manually authenticate the user for this request
        \Auth::guard('admin')->login($user);
        
        // Make request to the controller method
        $controller = new \App\Http\Controllers\AdminDashboardController();
        $response = $controller->tenants();
        
        $this->info('Response status: ' . $response->getStatusCode());
        $this->line('Response data:');
        $this->line(json_encode($response->getData(), JSON_PRETTY_PRINT));
        
        \Auth::guard('admin')->logout();
    }
}
