<?php

use App\Jobs\DispatchWebhookEventJob;
use Illuminate\Support\Facades\Http;

it('blocks insecure and private webhook destinations by default', function (): void {
    config()->set('approvehub.webhooks.urls', [
        'http://hooks.example.test/inbound',
        'https://127.0.0.1/internal',
        'https://user:pass@hooks.example.test/inbound',
        'https://hooks.example.test:9443/inbound',
        'https://hooks.example.test/inbound',
    ]);
    config()->set('approvehub.webhooks.allow_insecure_urls', false);
    config()->set('approvehub.webhooks.allow_private_hosts', false);

    Http::fake([
        'https://hooks.example.test/inbound' => Http::response(['ok' => true], 200),
    ]);

    $job = new DispatchWebhookEventJob(
        event: 'review.submitted',
        organizationId: 1,
        actorId: 5,
        targetType: 'approval_workflow',
        targetId: 9,
        metadata: ['document_id' => 44],
        occurredAt: now()->toIso8601String(),
    );

    $job->handle();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://hooks.example.test/inbound');

    $this->assertDatabaseHas('webhook_deliveries', [
        'webhook_url' => 'http://hooks.example.test/inbound',
        'error_message' => 'Webhook URL blocked by security policy.',
        'succeeded' => false,
    ]);
    $this->assertDatabaseHas('webhook_deliveries', [
        'webhook_url' => 'https://127.0.0.1/internal',
        'error_message' => 'Webhook URL blocked by security policy.',
        'succeeded' => false,
    ]);
    $this->assertDatabaseHas('webhook_deliveries', [
        'webhook_url' => 'https://user:pass@hooks.example.test/inbound',
        'error_message' => 'Webhook URL blocked by security policy.',
        'succeeded' => false,
    ]);
    $this->assertDatabaseHas('webhook_deliveries', [
        'webhook_url' => 'https://hooks.example.test:9443/inbound',
        'error_message' => 'Webhook URL blocked by security policy.',
        'succeeded' => false,
    ]);
});
