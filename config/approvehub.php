<?php

return [
    'attachments' => [
        'disk' => (string) env('APPROVEHUB_ATTACHMENTS_DISK', 'local'),
    ],

    'rate_limits' => [
        'public_share_links_per_minute' => (int) env('APPROVEHUB_PUBLIC_SHARE_LINKS_PER_MINUTE', 30),
    ],

    'webhooks' => [
        'enabled' => (bool) env('APPROVEHUB_WEBHOOKS_ENABLED', false),
        'urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('APPROVEHUB_WEBHOOK_URLS', ''))))),
        'events' => array_values(array_filter(array_map('trim', explode(',', (string) env('APPROVEHUB_WEBHOOK_EVENTS', 'review.submitted,approval.rejected'))))),
        'timeout_seconds' => (int) env('APPROVEHUB_WEBHOOK_TIMEOUT_SECONDS', 5),
        'signing_secret' => (string) env('APPROVEHUB_WEBHOOK_SIGNING_SECRET', ''),
        'allow_insecure_urls' => (bool) env('APPROVEHUB_WEBHOOK_ALLOW_INSECURE_URLS', false),
        'allow_private_hosts' => (bool) env('APPROVEHUB_WEBHOOK_ALLOW_PRIVATE_HOSTS', false),
    ],
];
