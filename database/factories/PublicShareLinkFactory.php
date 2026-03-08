<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PublicShareLink>
 */
class PublicShareLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'created_by' => User::factory(),
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => now()->addDays(fake()->numberBetween(1, 14)),
            'is_active' => true,
        ];
    }
}
