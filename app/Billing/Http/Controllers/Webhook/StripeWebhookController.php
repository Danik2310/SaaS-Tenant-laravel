<?php

declare(strict_types=1);

namespace App\Billing\Http\Controllers\Webhook;

use App\Billing\Notifications\PaymentMethodChanged;
use App\Billing\Projectors\LocalSubscriptionProjector;
use App\Billing\Projectors\TenantPaymentCardProjector;
use App\Billing\Webhooks\CashierSubscriptionWebhookAdapter;
use App\Http\Controllers\Controller;
use App\Models\StripeWebhookEvent;
use App\Models\Tenant;
use App\Shared\Contracts\PaymentGatewayInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Central Stripe webhook receiver for card-change confirmations and Cashier
 * subscription state.
 *
 * The signature is verified before the event is recorded, and the event id is
 * used as an idempotency key so Stripe retries never send duplicate emails or
 * replay subscription transitions.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        CashierSubscriptionWebhookAdapter $cashier,
        LocalSubscriptionProjector $projector,
        TenantPaymentCardProjector $cardProjector,
    ): JsonResponse {
        $gateway = null;

        try {
            /** @var PaymentGatewayInterface $gateway */
            $gateway = app(PaymentGatewayInterface::class);
            $event = $gateway->verifyWebhookEvent(
                (string) $request->getContent(),
                (string) $request->header('Stripe-Signature', ''),
            );
        } catch (Throwable $exception) {
            Log::warning('Rejected Stripe webhook with invalid signature', [
                'reason' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        /** @var array{id: string, type: string, object: array<string, mixed>, previous: array<string, mixed>, payload?: array<string, mixed>} $event */
        $eventId = (string) ($event['id'] ?? '');

        if ($eventId === '') {
            return response()->json(['message' => 'Missing event id.'], 400);
        }

        $record = StripeWebhookEvent::firstOrCreate([
            'id' => $eventId,
        ], [
            'type' => (string) ($event['type'] ?? 'unknown'),
        ]);

        if (! $record->wasRecentlyCreated) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        $eventType = (string) ($event['type'] ?? '');

        if ($cardProjector->handles($eventType) && $cardProjector->isCardChange($event)) {
            try {
                $cardProjector->handle($event);
                $this->notifyCardChange($event, $cardProjector->customerIdFromEvent($event));
            } catch (Throwable $exception) {
                report($exception);
                $record->delete();

                return response()->json(['message' => 'Webhook processing failed; retry requested.'], 500);
            }
        }

        if ($cashier->handles($eventType)) {
            try {
                $cashier->handle($event);
                $projector->handle($event);
            } catch (Throwable $exception) {
                report($exception);
                $record->delete();

                return response()->json(['message' => 'Webhook processing failed; retry requested.'], 500);
            }
        }

        return response()->json(['received' => true]);
    }

    /**
     * @param  array{id: string, type: string, object: array<string, mixed>, previous: array<string, mixed>, payload?: array<string, mixed>}  $event
     */
    private function notifyCardChange(array $event, ?string $customerId): void
    {
        $action = match ((string) ($event['type'] ?? '')) {
            'payment_method.attached' => 'added',
            'payment_method.detached' => 'removed',
            'customer.updated' => 'default_changed',
            default => null,
        };

        if ($action === null || $customerId === null) {
            return;
        }

        $tenant = Tenant::where('stripe_customer_id', $customerId)->first();

        if ($tenant === null) {
            return;
        }

        // The projector already refreshed these masked fields from the signed
        // event or Stripe, so the notification never needs a gateway call.
        $card = [
            'brand' => (string) ($tenant->pm_type ?? ''),
            'last4' => (string) ($tenant->pm_last_four ?? ''),
            'exp_month' => (int) ($tenant->pm_exp_month ?? 0),
            'exp_year' => (int) ($tenant->pm_exp_year ?? 0),
        ];

        $recipients = array_values(array_unique(array_filter(
            [$tenant->billing_email, $tenant->email],
            fn ($email): bool => is_string($email) && $email !== '',
        )));

        if ($recipients === []) {
            return;
        }

        Notification::route('mail', $recipients)->notify(
            new PaymentMethodChanged($tenant, $action, $card),
        );
    }
}
