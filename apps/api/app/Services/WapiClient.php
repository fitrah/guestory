<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WapiClient
{
    public function sendText(string $recipient, string $body): array
    {
        $baseUrl = rtrim((string) config('services.wapi.base_url'), '/');
        $apiKey = config('services.wapi.api_key');
        $numberId = config('services.wapi.number_id');
        $dryRun = (bool) config('services.wapi.dry_run');

        if ($dryRun || blank($apiKey) || blank($numberId)) {
            return [
                'status' => 'SKIPPED',
                'provider_message_id' => null,
                'error_message' => 'WAPI_DRY_RUN_OR_NOT_CONFIGURED',
            ];
        }

        try {
            $response = Http::timeout((int) config('services.wapi.timeout', 10))
                ->acceptJson()
                ->withHeaders(['x-api-key' => $apiKey])
                ->post("{$baseUrl}/api/messages/send-text", [
                    'numberId' => $numberId,
                    'recipient' => $this->normalizeRecipient($recipient),
                    'body' => $body,
                ]);
        } catch (\Throwable $exception) {
            return [
                'status' => 'FAILED',
                'provider_message_id' => null,
                'error_message' => Str::limit($exception->getMessage(), 1000),
            ];
        }

        if (! $response->successful()) {
            return [
                'status' => 'FAILED',
                'provider_message_id' => null,
                'error_message' => Str::limit($response->body(), 1000),
            ];
        }

        $payload = $response->json();
        $message = $payload['data'] ?? $payload;

        return [
            'status' => strtoupper((string) ($message['status'] ?? 'QUEUED')),
            'provider_message_id' => $message['id'] ?? null,
            'error_message' => null,
        ];
    }

    private function normalizeRecipient(string $recipient): string
    {
        return preg_replace('/\D+/', '', $recipient) ?: $recipient;
    }
}
