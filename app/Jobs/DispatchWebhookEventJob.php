<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Handles DispatchWebhookEventJob responsibilities for the ApproveHub domain.
 */
class DispatchWebhookEventJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly string $event,
        public readonly int $organizationId,
        public readonly ?int $actorId,
        public readonly string $targetType,
        public readonly int $targetId,
        public readonly ?array $metadata,
        public readonly string $occurredAt,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $webhookUrls = config('approvehub.webhooks.urls', []);
        $timeoutSeconds = (int) config('approvehub.webhooks.timeout_seconds', 5);
        $signingSecret = (string) config('approvehub.webhooks.signing_secret', '');
        $allowInsecureUrls = (bool) config('approvehub.webhooks.allow_insecure_urls', false);
        $allowPrivateHosts = (bool) config('approvehub.webhooks.allow_private_hosts', false);
        $payload = $this->payload();
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($webhookUrls as $webhookUrl) {
            if (! is_string($webhookUrl)) {
                continue;
            }

            if (! $this->isSafeWebhookUrl($webhookUrl, $allowInsecureUrls, $allowPrivateHosts)) {
                $this->storeDelivery($webhookUrl, null, 'Webhook URL blocked by security policy.');
                continue;
            }

            $response = null;

            try {
                $request = Http::timeout($timeoutSeconds);

                if ($signingSecret !== '' && is_string($payloadJson)) {
                    $request = $request->withHeaders([
                        'X-ApproveHub-Signature' => hash_hmac('sha256', $payloadJson, $signingSecret),
                    ]);
                }

                $response = $request->post($webhookUrl, $payload);
                $this->storeDelivery($webhookUrl, $response, null);
            } catch (Throwable $exception) {
                $this->storeDelivery($webhookUrl, $response, $exception->getMessage());
                report($exception);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'event' => $this->event,
            'organization_id' => $this->organizationId,
            'actor_id' => $this->actorId,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'metadata' => $this->metadata,
            'occurred_at' => $this->occurredAt,
        ];
    }

    private function storeDelivery(string $webhookUrl, ?Response $response, ?string $errorMessage): void
    {
        WebhookDelivery::query()->create([
            'event' => $this->event,
            'organization_id' => $this->organizationId,
            'actor_id' => $this->actorId,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'webhook_url' => $webhookUrl,
            'response_status' => $response?->status(),
            'response_body' => Str::limit((string) $response?->body(), 4000),
            'succeeded' => $response?->successful() ?? false,
            'error_message' => $errorMessage,
            'attempted_at' => now(),
        ]);
    }

    private function isSafeWebhookUrl(string $webhookUrl, bool $allowInsecureUrls, bool $allowPrivateHosts): bool
    {
        $parts = parse_url($webhookUrl);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme === '' || $host === '') {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (! $allowInsecureUrls && $scheme !== 'https') {
            return false;
        }

        $port = (int) ($parts['port'] ?? 0);
        if (! $this->isAllowedPort($scheme, $port)) {
            return false;
        }

        if ($allowPrivateHosts) {
            return true;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        if (str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false && ! $this->isPublicIp($host)) {
            return false;
        }

        foreach ($this->resolveHostIps($host) as $ipAddress) {
            if (! $this->isPublicIp($ipAddress)) {
                return false;
            }
        }

        return true;
    }

    private function isAllowedPort(string $scheme, int $port): bool
    {
        if ($port === 0) {
            return true;
        }

        if ($scheme === 'https') {
            return in_array($port, [443, 8443], true);
        }

        if ($scheme === 'http') {
            return in_array($port, [80, 8080], true);
        }

        return false;
    }

    private function isPublicIp(string $ipAddress): bool
    {
        return filter_var(
            $ipAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * @return list<string>
     */
    private function resolveHostIps(string $host): array
    {
        $ipv4Records = @dns_get_record($host, DNS_A);
        $ipv6Records = @dns_get_record($host, DNS_AAAA);

        if ($ipv4Records === false && $ipv6Records === false) {
            return [];
        }

        $records = array_merge(is_array($ipv4Records) ? $ipv4Records : [], is_array($ipv6Records) ? $ipv6Records : []);

        $ips = [];

        foreach ($records as $record) {
            if (is_array($record) && isset($record['ip']) && is_string($record['ip'])) {
                $ips[] = $record['ip'];
            }

            if (is_array($record) && isset($record['ipv6']) && is_string($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
