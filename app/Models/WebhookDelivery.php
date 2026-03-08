<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Handles WebhookDelivery responsibilities for the ApproveHub domain.
 */
class WebhookDelivery extends Model
{
    /** @use HasFactory<\Database\Factories\WebhookDeliveryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event',
        'organization_id',
        'actor_id',
        'target_type',
        'target_id',
        'webhook_url',
        'response_status',
        'response_body',
        'succeeded',
        'error_message',
        'attempted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'succeeded' => 'boolean',
            'attempted_at' => 'datetime',
        ];
    }
}
