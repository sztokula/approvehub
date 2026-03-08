<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\DocumentVisibility;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'owner_id' => User::factory(),
            'current_version_id' => null,
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'document_type' => fake()->randomElement(['general', 'contract', 'policy', 'request', 'offer', 'internal']),
            'visibility' => fake()->randomElement(array_column(DocumentVisibility::cases(), 'value')),
            'status' => DocumentStatus::Draft->value,
        ];
    }
}
