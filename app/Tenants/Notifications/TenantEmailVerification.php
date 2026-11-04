<?php

declare(strict_types=1);

namespace App\Tenants\Notifications;

use App\Tenants\Support\TenantVerificationUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Branded owner-facing verification mail for tenant signup.
 *
 * The signed URL is pre-built by {@see TenantVerificationUrl}
 * because it must be rooted at the tenant's domain; a notification cannot know
 * that from the notifiable alone.
 */
class TenantEmailVerification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $verificationUrl,
        public readonly string $tenantName,
        public readonly string $domain,
        public readonly int $expiresInMinutes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your email to activate '.$this->tenantName)
            ->markdown('emails.tenant-verification', [
                'url' => $this->verificationUrl,
                'name' => (string) ($notifiable->name ?? ''),
                'tenantName' => $this->tenantName,
                'domain' => $this->domain,
                'expiresInMinutes' => $this->expiresInMinutes,
            ]);
    }
}
