<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventBilling;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EventBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Config::set('billing.provider.project_code', 'guestory-test');
        Config::set('billing.provider.api_key', 'protected-test-key');
        Config::set('billing.provider.webhook_secret', 'protected-webhook-secret');
    }

    public function test_plan_list_is_admin_only_and_matches_contract(): void
    {
        $this->getJson('/api/admin/billing/plans')->assertUnauthorized();
        $this->getJson('/api/admin/billing/plans', $this->adminHeaders())->assertOk()
            ->assertJsonPath('plans.0.amount', 0)
            ->assertJsonPath('plans.1.amount', 99000)
            ->assertJsonPath('plans.2.amount', 249000)
            ->assertJsonPath('plans.2.google_photos', true)
            ->assertJsonPath('plans.2.popular', true);
    }

    public function test_owner_can_activate_free_idempotently_and_other_admin_cannot_access(): void
    {
        $event = $this->event();
        $this->postJson("/api/admin/events/{$event->id}/billing/free", [], $this->adminHeaders())->assertOk()
            ->assertJsonPath('billing.plan_code', 'FREE')->assertJsonPath('entitlements.guest_limit', 50);
        $this->postJson("/api/admin/events/{$event->id}/billing/free", [], $this->adminHeaders())->assertOk();
        $this->assertDatabaseCount('event_billings', 1);

        $other = User::create(['name' => 'Other', 'email' => 'billing-other@example.test', 'password' => Hash::make('password'), 'role' => 'EVENT_OWNER', 'status' => 'ACTIVE']);
        [$token] = $other->createAccessToken('test');
        $this->getJson("/api/admin/events/{$event->id}/billing", ['Authorization' => "Bearer {$token}"])->assertForbidden();
    }

    public function test_checkout_uses_server_contract_and_returns_only_safe_payment_data(): void
    {
        $event = $this->event();

        Http::fake(function ($request) {
            if ($request->url() === 'https://pay.proyek.org/v1/payments') {
                return Http::response(['payment' => ['id' => 'pay_123', 'order_id' => $request['order_id'], 'redirect_url' => 'https://pay.proyek.org/checkout/pay_123'], 'client_key' => 'not-for-browser', 'snap_js_url' => 'https://pay.proyek.org/snap.js'], 201);
            }

            return Http::response([], 404);
        });

        $response = $this->postJson("/api/admin/events/{$event->id}/billing/checkout", ['plan_code' => 'BASIC'], $this->adminHeaders())
            ->assertCreated()->assertJsonPath('billing.amount', 99000)->assertJsonPath('redirect_url', 'https://pay.proyek.org/checkout/pay_123')
            ->assertJsonMissingPath('api_key')->assertJsonMissingPath('client_key')->assertJsonMissingPath('snap_js_url')->assertJsonMissingPath('entitlements');

        $this->assertStringStartsWith('GUESTORY-', $response->json('billing.order_id'));
        Http::assertSent(function ($request) use ($event) {
            return $request->url() === 'https://pay.proyek.org/v1/payments'
                && $request->header('X-Project-Code')[0] === 'guestory-test'
                && $request->header('X-Api-Key')[0] === 'protected-test-key'
                && is_int($request['amount']) && $request['amount'] === 99000
                && $request['metadata'] === ['event_id' => $event->id, 'owner_id' => $event->owner_id, 'plan_code' => 'BASIC'];
        });
        $this->assertNull(EventBilling::first()->entitlements);
    }

    public function test_sync_posts_authoritative_adapter_endpoint_and_parses_payment_wrapper(): void
    {
        $event = $this->event();
        $billing = EventBilling::create(['event_id' => $event->id, 'plan_code' => 'BASIC', 'status' => 'PENDING', 'order_id' => 'GUESTORY-SYNC-1', 'amount' => 99000, 'currency' => 'IDR']);
        Http::fake(['https://pay.proyek.org/v1/payments/GUESTORY-SYNC-1/sync' => Http::response(['payment' => ['order_id' => $billing->order_id, 'status' => 'PAID'], 'midtrans' => ['transaction_status' => 'settlement']], 200)]);

        $this->postJson("/api/admin/events/{$event->id}/billing/sync", [], $this->adminHeaders())
            ->assertOk()->assertJsonPath('billing.status', 'PAID')->assertJsonPath('entitlements.zip_download', true);

        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->url() === 'https://pay.proyek.org/v1/payments/GUESTORY-SYNC-1/sync');
    }

    public function test_unconfigured_checkout_returns_useful_error(): void
    {
        Config::set('billing.provider.api_key', null);
        $event = $this->event();
        $this->postJson("/api/admin/events/{$event->id}/billing/checkout", ['plan_code' => 'BASIC'], $this->adminHeaders())
            ->assertStatus(503)->assertJsonPath('code', 'PAYMENT_CHECKOUT_UNAVAILABLE')
            ->assertJsonPath('message', 'Pembayaran belum dikonfigurasi. Atur PAYMENT_ADAPTER_PROJECT_CODE dan PAYMENT_ADAPTER_API_KEY.');
    }

    public function test_invalid_webhook_is_rejected(): void
    {
        $this->call('POST', '/api/payments/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_PAY_ADAPTER_TIMESTAMP' => (string) now()->timestamp,
            'HTTP_X_PAY_ADAPTER_SIGNATURE' => 'sha256=bad', 'HTTP_X_PAY_ADAPTER_DELIVERY' => 'delivery-invalid',
        ], '{}')->assertUnauthorized()->assertJsonPath('code', 'INVALID_WEBHOOK_SIGNATURE');
    }

    public function test_paid_webhook_activates_entitlement_and_duplicate_is_safe(): void
    {
        $event = $this->event();
        $billing = EventBilling::create(['event_id' => $event->id, 'plan_code' => 'PREMIUM', 'status' => 'PENDING', 'order_id' => 'GUESTORY-PAID-1', 'amount' => 249000, 'currency' => 'IDR']);
        $body = json_encode(['event' => 'payment.paid', 'payment' => ['order_id' => $billing->order_id, 'status' => 'PAID'], 'midtrans' => ['transaction_status' => 'settlement'], 'created_at' => now()->toISOString()], JSON_THROW_ON_ERROR);
        $headers = $this->webhookHeaders($body, 'delivery-paid-1');

        $this->call('POST', '/api/payments/webhook', [], [], [], $headers, $body)->assertOk()->assertJsonPath('code', 'WEBHOOK_PROCESSED');
        $this->call('POST', '/api/payments/webhook', [], [], [], $headers, $body)->assertOk()->assertJsonPath('code', 'WEBHOOK_DUPLICATE');

        $billing->refresh();
        $this->assertSame('PAID', $billing->status);
        $this->assertTrue($billing->entitlements['google_photos']);
        $this->assertSame(5000, $billing->entitlements['photo_limit']);
        $this->assertDatabaseCount('payment_webhook_deliveries', 1);
    }

    public function test_non_paid_and_unknown_order_webhooks_do_not_grant_entitlement(): void
    {
        $event = $this->event();
        $billing = EventBilling::create(['event_id' => $event->id, 'plan_code' => 'BASIC', 'status' => 'PENDING', 'order_id' => 'GUESTORY-PENDING-1', 'amount' => 99000, 'currency' => 'IDR']);
        foreach ([
            ['delivery-pending', ['payment' => ['order_id' => $billing->order_id, 'status' => 'PENDING']]],
            ['delivery-unknown', ['payment' => ['order_id' => 'GUESTORY-UNKNOWN', 'status' => 'PAID']]],
        ] as [$delivery, $payload]) {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
            $this->call('POST', '/api/payments/webhook', [], [], [], $this->webhookHeaders($body, $delivery), $body)->assertOk();
        }
        $billing->refresh();
        $this->assertSame('PENDING', $billing->status);
        $this->assertNull($billing->entitlements);
    }

    private function webhookHeaders(string $body, string $delivery): array
    {
        $timestamp = (string) now()->timestamp;

        return ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAY_ADAPTER_TIMESTAMP' => $timestamp, 'HTTP_X_PAY_ADAPTER_SIGNATURE' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'protected-webhook-secret'), 'HTTP_X_PAY_ADAPTER_DELIVERY' => $delivery];
    }

    private function event(): Event
    {
        return Event::create(['owner_id' => User::where('email', 'fitrahajah@gmail.com')->firstOrFail()->id, 'name' => 'Billing Event', 'type' => 'Wedding', 'date' => '2026-12-01', 'status' => 'Draft']);
    }

    private function adminHeaders(): array
    {
        [$token] = User::where('email', 'fitrahajah@gmail.com')->firstOrFail()->createAccessToken('test');

        return ['Authorization' => "Bearer {$token}"];
    }
}
