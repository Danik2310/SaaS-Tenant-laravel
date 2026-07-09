<?php

namespace App\Billing\Console\Commands;

use App\Models\PaymentMethod;
use Illuminate\Console\Command;

class UpdatePaymentMethod extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment-method:update {id} {--name=} {--provider=} {--api_key=} {--secret_key=} {--mode=} {--active=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update a payment method securely from code';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');
        $method = PaymentMethod::find($id);
        if (! $method) {
            $this->error('Payment method not found');

            return Command::FAILURE;
        }

        // Validaciones
        $name = $this->option('name') ?: $method->name;
        $provider = $this->option('provider') ?: $method->provider;
        if (! in_array($provider, ['stripe', 'paypal', 'other'])) {
            $this->error('Invalid provider. Must be stripe, paypal, or other');

            return Command::FAILURE;
        }

        $apiKey = $this->option('api_key');
        $secretKey = $this->option('secret_key');
        $mode = $this->option('mode') ?: $method->mode;
        if (! in_array($mode, ['test', 'live'])) {
            $this->error('Invalid mode. Must be test or live');

            return Command::FAILURE;
        }
        $active = $this->option('active') !== null ? filter_var($this->option('active'), FILTER_VALIDATE_BOOLEAN) : $method->active;

        $method->update([
            'name' => $name,
            'provider' => $provider,
            'api_key' => $apiKey,
            'secret_key' => $secretKey,
            'mode' => $mode,
            'active' => $active,
        ]);

        $this->info('Payment method updated successfully');

        return Command::SUCCESS;
    }
}
