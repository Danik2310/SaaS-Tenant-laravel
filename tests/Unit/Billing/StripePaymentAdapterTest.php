<?php

namespace Tests\Unit\Billing;

use App\Billing\Adapters\StripePaymentAdapter;
use Mockery;
use PHPUnit\Framework\TestCase;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Service\CustomerService;
use Stripe\Service\PaymentIntentService;
use Stripe\Service\RefundService;
use Stripe\StripeClient;

class StripePaymentAdapterTest extends TestCase
{
    private $client;

    private $adapter;

    private $paymentIntentsService;

    private $refundsService;

    private $customersService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Mockery::mock(StripeClient::class);
        $this->addServiceMock('paymentIntents', 'paymentIntentsService', PaymentIntentService::class);
        $this->addServiceMock('refunds', 'refundsService', RefundService::class);
        $this->addServiceMock('customers', 'customersService', CustomerService::class);

        $this->adapter = new StripePaymentAdapter($this->client);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function addServiceMock(string $accessor, string $property, string $serviceClass): void
    {
        $service = Mockery::mock($serviceClass);
        $this->client->shouldReceive('getService')->with($accessor)->andReturn($service);

        $this->{$property} = $service;
    }

    public function test_charge_creates_payment_intent_and_returns_client_secret(): void
    {
        $this->paymentIntentsService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $params) {
                return $params['amount'] === 1999
                    && $params['currency'] === 'usd'
                    && $params['automatic_payment_methods'] === ['enabled' => true];
            }))
            ->andReturn(PaymentIntent::constructFrom([
                'id' => 'pi_123',
                'object' => 'payment_intent',
                'status' => 'requires_payment_method',
                'amount' => 1999,
                'currency' => 'usd',
                'client_secret' => 'pi_123_secret_xyz',
                'customer' => 'cus_456',
            ]));

        $result = $this->adapter->charge(19.99, ['currency' => 'usd', 'customer' => 'cus_456']);

        $this->assertSame('pi_123', $result['id']);
        $this->assertSame('requires_payment_method', $result['status']);
        $this->assertSame(1999, $result['amount']);
        $this->assertSame('pi_123_secret_xyz', $result['client_secret']);
        $this->assertSame('cus_456', $result['customer']);
    }

    public function test_charge_defaults_to_usd_currency(): void
    {
        $this->paymentIntentsService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $params) => $params['currency'] === 'usd'))
            ->andReturn(PaymentIntent::constructFrom([
                'id' => 'pi_124',
                'object' => 'payment_intent',
                'status' => 'requires_payment_method',
                'amount' => 500,
                'currency' => 'usd',
                'client_secret' => 'pi_124_secret_abc',
                'customer' => null,
            ]));

        $result = $this->adapter->charge(5.0);

        $this->assertSame('usd', $result['currency']);
    }

    public function test_refund_creates_refund_for_payment_intent(): void
    {
        $this->refundsService
            ->shouldReceive('create')
            ->once()
            ->with([
                'payment_intent' => 'pi_123',
                'amount' => 1000,
            ])
            ->andReturn(Refund::constructFrom([
                'id' => 're_123',
                'object' => 'refund',
                'payment_intent' => 'pi_123',
                'amount' => 1000,
                'currency' => 'usd',
                'status' => 'succeeded',
            ]));

        $result = $this->adapter->refund('pi_123', 10.0);

        $this->assertSame('re_123', $result['id']);
        $this->assertSame('pi_123', $result['payment_intent']);
        $this->assertSame(1000, $result['amount']);
        $this->assertSame('succeeded', $result['status']);
    }

    public function test_get_customer_returns_customer_array(): void
    {
        $this->customersService
            ->shouldReceive('retrieve')
            ->once()
            ->with('cus_456')
            ->andReturn(Customer::constructFrom([
                'id' => 'cus_456',
                'object' => 'customer',
                'email' => 'client@example.com',
                'name' => 'Client',
            ]));

        $result = $this->adapter->getCustomer('cus_456');

        $this->assertSame('cus_456', $result['id']);
        $this->assertSame('client@example.com', $result['email']);
    }
}
