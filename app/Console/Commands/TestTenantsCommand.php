<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestTenantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:tenants';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the AdminDashboardController tenants method';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $controller = new \App\Http\Controllers\AdminDashboardController();
        $response = $controller->tenants();
        
        $this->info('Tenants data:');
        $this->line(json_encode($response->getData(), JSON_PRETTY_PRINT));
    }
}
