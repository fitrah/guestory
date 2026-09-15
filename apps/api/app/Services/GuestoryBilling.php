<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventBilling;
use App\Models\PaymentWebhookDelivery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GuestoryBilling
{
    public function plans(): array
    {
        return array_values(config('billing.plans', []));
    }

    public function plan(string $code): array
    {
        $plan = config('billing.plans.'.strtoupper($code));

        if (! is_array($plan)) {
            throw new RuntimeException('Paket billing tidak valid.');
        }

        return $plan;
    }

    public function activateFree(Event $event): EventBilling
    {
        $plan = $this->plan('FREE');

        return DB::transaction(function () use ($event, $plan) {
            $billing = EventBilling::query()->lockForUpdate()->firstOrNew(['event_id' => $event->id]);

            if ($billing->exists && $billing->status === 'PAID') {
                return $billing;
            }

            $billing->fill([
                'plan_code' => 'FREE', 'status' => 'ACTIVE', 'order_id' => null,
                'provider_payment_id' => null, 'amount' => 0, 'currency' => 'IDR',
                'entitlements' => $this->entitlements($plan), 'paid_at' => null,
                'activated_at' => $billing->activated_at ?? now(),
                'expires_at' => null,
            ])->save();

            return $billing->fresh();
        });
    }

    public function checkout(Event $event, string $planCode): array
    {
        $plan = $this->plan($planCode);
        if ($plan['amount'] === 0) {
            throw new RuntimeException('Gunakan aktivasi FREE untuk paket gratis.');
        }

        $this->assertConfigured();
        $orderId = 'GUESTORY-'.$event->id.'-'.strtoupper(Str::random(16));
        $metadata = ['event_id' => $event->id, 'owner_id' => $event->owner_id, 'plan_code' => $plan['code']];

        $billing = EventBilling::query()->updateOrCreate(
            ['event_id' => $event->id],
            [
                'plan_code' => $plan['code'], 'status' => 'PENDING', 'order_id' => $orderId,
                'provider_payment_id' => null, 'amount' => (int) $plan['amount'], 'currency' => 'IDR',
                'entitlements' => null, 'paid_at' => null, 'activated_at' => null,
                'expires_at' => null,
            ],
        );

        try {
            $response = $this->provider()->post('/v1/payments', [
                'order_id' => $orderId,
                'amount' => (int) $plan['amount'],
                'currency' => 'IDR',
                'description' => "Guestory {$plan['name']} - {$event->name}",
                'metadata' => $metadata,
            ]);
            $response->throw();
            $body = $response->json();
            $providerOrderId = data_get($body, 'payment.order_id') ?? data_get($body, 'order_id') ?? data_get($body, 'data.order_id');
            if (is_string($providerOrderId) && ! hash_equals($orderId, $providerOrderId)) {
                throw new RuntimeException('Provider pembayaran mengembalikan order ID yang tidak cocok.');
            }

            $redirectUrl = data_get($body, 'payment.redirect_url') ?? data_get($body, 'redirect_url') ?? data_get($body, 'data.redirect_url');
            if (! is_string($redirectUrl) || filter_var($redirectUrl, FILTER_VALIDATE_URL) === false) {
                throw new RuntimeException('Provider pembayaran tidak mengembalikan redirect URL yang valid.');
            }

            $billing->forceFill([
                'provider_payment_id' => data_get($body, 'payment.id') ?? data_get($body, 'id') ?? data_get($body, 'data.id') ?? $providerOrderId,
                'last_synced_at' => now(),
            ])->save();

            return ['billing' => $billing->fresh(), 'redirect_url' => $redirectUrl];
        } catch (\Throwable $error) {
            $billing->forceFill(['status' => 'FAILED'])->save();
            throw $error;
        }
    }

    public function sync(EventBilling $billing): EventBilling
    {
        if (! $billing->order_id) {
            return $billing;
        }

        $this->assertConfigured();
        $response = $this->provider()->post('/v1/payments/'.rawurlencode($billing->order_id).'/sync');
        $response->throw();
        $payload = $response->json('payment') ?? $response->json('data') ?? $response->json();
        $this->applyProviderStatus($billing, is_array($payload) ? $payload : []);

        return $billing->fresh();
    }

    public function processWebhook(string $deliveryId, array $payload): bool
    {
        return DB::transaction(function () use ($deliveryId, $payload) {
            if (PaymentWebhookDelivery::query()->where('delivery_id', $deliveryId)->lockForUpdate()->exists()) {
                return false;
            }

            $orderId = data_get($payload, 'payment.order_id') ?? data_get($payload, 'order_id') ?? data_get($payload, 'data.order_id');
            $eventType = data_get($payload, 'event') ?? data_get($payload, 'type');
            $billing = is_string($orderId)
                ? EventBilling::query()->where('order_id', $orderId)->lockForUpdate()->first()
                : null;

            PaymentWebhookDelivery::create([
                'delivery_id' => $deliveryId, 'order_id' => is_string($orderId) ? $orderId : null,
                'event_type' => is_string($eventType) ? $eventType : null, 'processed_at' => now(),
            ]);

            if ($billing) {
                $providerPayload = data_get($payload, 'payment') ?? data_get($payload, 'data');
                $this->applyProviderStatus($billing, is_array($providerPayload) ? $providerPayload : $payload);
            }

            return true;
        });
    }

    public function verifyWebhook(string $rawBody, ?string $timestamp, ?string $signature): bool
    {
        $secret = (string) config('billing.provider.webhook_secret');
        if ($secret === '' || ! is_string($timestamp) || ! ctype_digit($timestamp) || ! is_string($signature)) {
            return false;
        }

        if (abs(now()->timestamp - (int) $timestamp) > (int) config('billing.provider.webhook_tolerance_seconds', 300)) {
            return false;
        }

        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return hash_equals($expected, strtolower(substr($signature, 7)));
    }

    public function payload(?EventBilling $billing): ?array
    {
        if (! $billing) {
            return null;
        }

        return [
            'id' => $billing->id, 'event_id' => $billing->event_id,
            'plan_code' => $billing->plan_code, 'status' => $billing->status,
            'order_id' => $billing->order_id, 'amount' => (int) $billing->amount,
            'currency' => $billing->currency, 'entitlements' => $billing->entitlements,
            'paid_at' => $billing->paid_at?->toISOString(),
            'activated_at' => $billing->activated_at?->toISOString(),
            'last_synced_at' => $billing->last_synced_at?->toISOString(),
        ];
    }

    public function effectiveEntitlements(Event $event): array
    {
        $billing = $event->billing;

        if ($billing && in_array($billing->status, ['ACTIVE', 'PAID'], true) && is_array($billing->entitlements)) {
            return $billing->entitlements;
        }

        return $this->entitlements($this->plan('FREE'));
    }

    private function applyProviderStatus(EventBilling $billing, array $payload): void
    {
        $status = strtoupper((string) ($payload['status'] ?? ''));
        $orderId = $payload['order_id'] ?? null;
        if ($orderId !== null && ! hash_equals((string) $billing->order_id, (string) $orderId)) {
            return;
        }

        $updates = ['last_synced_at' => now()];
        if (in_array($status, ['PAID', 'SETTLED', 'SUCCESS'], true)) {
            $plan = $this->plan($billing->plan_code);
            $updates += [
                'status' => 'PAID', 'paid_at' => $billing->paid_at ?? now(),
                'activated_at' => $billing->activated_at ?? now(),
                'entitlements' => $this->entitlements($plan),
            ];
        } elseif (in_array($status, ['FAILED', 'EXPIRED', 'CANCELLED'], true) && $billing->status !== 'PAID') {
            $updates['status'] = $status;
        }

        $billing->forceFill($updates)->save();
    }

    private function entitlements(array $plan): array
    {
        return array_intersect_key($plan, array_flip([
            'guest_limit', 'photo_limit', 'retention_days', 'zip_download', 'google_photos',
            'staff_limit', 'branding', 'single_photo_download',
        ]));
    }

    private function assertConfigured(): void
    {
        if (! config('billing.provider.project_code') || ! config('billing.provider.api_key')) {
            throw new RuntimeException('Pembayaran belum dikonfigurasi. Atur PAYMENT_ADAPTER_PROJECT_CODE dan PAYMENT_ADAPTER_API_KEY.');
        }
    }

    private function provider(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('billing.provider.base_url'), '/'))
            ->acceptJson()->asJson()->timeout((int) config('billing.provider.timeout', 10))
            ->withHeaders([
                'X-Project-Code' => (string) config('billing.provider.project_code'),
                'X-Api-Key' => (string) config('billing.provider.api_key'),
            ]);
    }
}
