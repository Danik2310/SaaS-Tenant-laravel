<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestUserEndpoint extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:user-endpoint';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test /admin/user endpoint';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing /admin/user endpoint...');
        
        // Simulate authenticated request
        $user = \App\Models\AdminUser::first();
        
        if (!$user) {
            $this->error('No admin user found');
            return;
        }
        
        // Manually authenticate the user for this request
        \Auth::guard('admin')->login($user);
        
        // Make request to the controller method
        $controller = new \App\Http\Controllers\AdminAuthController();
        $response = $controller->user();
        
        $this->info('Response status: ' . $response->getStatusCode());
        $this->line('Response data:');
        $this->line(json_encode($response->getData(), JSON_PRETTY_PRINT));
        
        \Auth::guard('admin')->logout();
    }
}
