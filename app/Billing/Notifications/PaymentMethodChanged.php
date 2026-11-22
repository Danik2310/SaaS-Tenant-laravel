<?php

declare(strict_types=1);

namespace App\Billing\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirmation sent when Stripe reports a change to a tenant's saved cards.
 *
 * Only masked card metadata travels through this notification — never a PAN
 * or CVC.
 */
class PaymentMethodChanged extends Notification
{
    use Queueable;

    /**
     * @param  array{brand?: string, last4?: string, exp_month?: int, exp_year?: int}  $card
     */
    public function __construct(
        private Tenant $tenant,
        private string $action,
        private array $card,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment card updated for '.$this->tenant->name)
            ->greeting('Hello '.$this->tenant->name.',')
            ->line($this->actionSentence())
            ->line($this->cardSummary())
            ->line('If you did not make this change, contact support immediately.')
            ->salutation('Your SaaS Team');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'action' => $this->action,
            'card' => $this->card,
        ];
    }

    private function actionSentence(): string
    {
        return match ($this->action) {
            'added' => 'A payment card was added to your workspace.',
            'removed' => 'A payment card was removed from your workspace.',
            'default_changed' => 'Your default payment card was changed.',
            default => 'Your payment card settings were changed.',
        };
    }

    private function cardSummary(): string
    {
        $brand = ucfirst((string) ($this->card['brand'] ?? 'card'));
        $last4 = (string) ($this->card['last4'] ?? '');
        $month = (int) ($this->card['exp_month'] ?? 0);
        $year = (int) ($this->card['exp_year'] ?? 0);

        $label = $last4 !== '' ? "{$brand} ending in {$last4}" : $brand;

        if ($month > 0 && $year > 0) {
            $label .= sprintf(' (expires %02d/%d)', $month, $year);
        }

        return $label.'.';
    }
}
