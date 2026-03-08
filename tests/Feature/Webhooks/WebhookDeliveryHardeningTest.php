<?php

use App\Jobs\DispatchWebhookEventJob;
use Illuminate\Support\Facades\Http;

it('signs webhook payload and stores delivery log', function (): void {
    config()->set('approvehub.webhooks.urls', ['https://hooks.example.test/inbound']);
    config()->set('approvehub.webhooks.timeout_seconds', 5);
    config()->set('approvehub.webhooks.signing_secret', 'secret-key');

    Http::fake([
        'https://hooks.example.test/inbound' => Http::response(['ok' => true], 202),
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

    Http::assertSent(function ($request): bool {
        $signature = (string) ($request->header('X-ApproveHub-Signature')[0] ?? '');

        return $request->hasHeader('X-ApproveHub-Signature')
            && strlen($signature) === 64
            && $request->url() === 'https://hooks.example.test/inbound';
    });

    $this->assertDatabaseHas('webhook_deliveries', [
        'event' => 'review.submitted',
        'target_type' => 'approval_workflow',
        'target_id' => 9,
        'webhook_url' => 'https://hooks.example.test/inbound',
        'response_status' => 202,
        'succeeded' => true,
    ]);
});
