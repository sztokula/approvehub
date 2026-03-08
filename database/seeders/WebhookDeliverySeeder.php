<?php

namespace Database\Seeders;

use App\Models\WebhookDelivery;
use Illuminate\Database\Seeder;

class WebhookDeliverySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        WebhookDelivery::factory()->count(10)->create();
    }
}
