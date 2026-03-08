<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentVersion>
 */
class DocumentVersionFactory extends Factory
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
            'version_number' => fake()->numberBetween(1, 20),
            'title_snapshot' => fake()->sentence(6),
            'content_snapshot' => fake()->paragraphs(4, true),
            'meta_snapshot' => [
                'editor' => 'markdown',
            ],
            'created_by' => User::factory(),
        ];
    }
}
