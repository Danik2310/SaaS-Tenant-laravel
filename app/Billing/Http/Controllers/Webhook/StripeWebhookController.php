<?php

declare(strict_types=1);

namespace App\Billing\Http\Controllers\Webhook;

use App\Billing\Notifications\PaymentMethodChanged;
use App\Billing\Projectors\LocalSubscriptionProjector;
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

        try {
            $this->notifyCardChange($event, $gateway);
        } catch (Throwable $exception) {
            report($exception);
        }

        if ($cashier->handles((string) ($event['type'] ?? ''))) {
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
    private function notifyCardChange(array $event, PaymentGatewayInterface $gateway): void
    {
        $type = (string) ($event['type'] ?? '');
        $object = (array) ($event['object'] ?? []);
        $previous = (array) ($event['previous'] ?? []);

        $action = null;
        $card = [];
        $customerId = null;

        if ($type === 'payment_method.attached' || $type === 'payment_method.detached') {
            $customerId = $this->identifier($object['customer'] ?? null);
            $action = $type === 'payment_method.attached' ? 'added' : 'removed';
            $card = $this->cardFromPaymentMethod($object);
        } elseif ($type === 'customer.updated') {
            $customerId = $this->identifier($object['id'] ?? null);
            $currentDefault = $this->defaultPaymentMethodId($object);
            $previousDefault = $this->defaultPaymentMethodId($previous);

            if ($currentDefault === null || $currentDefault === $previousDefault) {
                return;
            }

            $action = 'default_changed';
            $card = $this->cardFromPaymentMethod(
                collect($gateway->listPaymentMethods((string) $customerId))
                    ->first(fn (array $item): bool => $item['id'] === $currentDefault) ?? []
            );
        }

        if ($action === null || $customerId === null) {
            return;
        }

        $tenant = Tenant::where('stripe_customer_id', $customerId)->first();

        if ($tenant === null) {
            return;
        }

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

    /**
     * @param  array<string, mixed>  $paymentMethod
     * @return array{brand?: string, last4?: string, exp_month?: int, exp_year?: int}
     */
    private function cardFromPaymentMethod(array $paymentMethod): array
    {
        $card = (array) ($paymentMethod['card'] ?? []);

        return [
            'brand' => (string) ($card['brand'] ?? ''),
            'last4' => (string) ($card['last4'] ?? ''),
            'exp_month' => (int) ($card['exp_month'] ?? 0),
            'exp_year' => (int) ($card['exp_year'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function defaultPaymentMethodId(array $data): ?string
    {
        $settings = (array) ($data['invoice_settings'] ?? []);

        return $this->identifier($settings['default_payment_method'] ?? null);
    }

    private function identifier(mixed $value): ?string
    {
        if (is_array($value)) {
            return isset($value['id']) ? (string) $value['id'] : null;
        }

        if (is_object($value)) {
            return isset($value->id) ? (string) $value->id : null;
        }

        return is_scalar($value) && $value !== '' ? (string) $value : null;
    }
}
