<?php

declare(strict_types=1);

namespace App\Billing\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanDowngradeWarning extends Notification
{
    use Queueable;

    public function __construct(
        private Tenant $tenant,
        private array $exceededLimits,
        private array $lostFeatures,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Your plan has been downgraded — action needed')
            ->greeting("Hello {$this->tenant->name},")
            ->line('Your subscription plan has been changed. Some features or limits have been reduced.')
            ->line('Please review the following changes and take action to avoid service interruptions.');

        if (! empty($this->lostFeatures)) {
            $message->line('Features no longer available:');
            foreach ($this->lostFeatures as $feature) {
                $message->line("- {$feature}");
            }
        }

        if (! empty($this->exceededLimits)) {
            $message->line('Resource limits you are currently exceeding:');
            foreach ($this->exceededLimits as $resource => $data) {
                $message->line("- {$resource}: {$data['current']} used, limit is {$data['new_limit']}");
            }

            $message->action('Manage Resources', route('tenant.dashboard'));
        }

        return $message
            ->line('If you believe this is an error, please contact support.')
            ->salutation('Your SaaS Team');
    }

    public function toArray($notifiable): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'exceeded_limits' => $this->exceededLimits,
            'lost_features' => $this->lostFeatures,
        ];
    }
}
