<?php

namespace Database\Factories;

use App\Enums\SourcePreviewFetchPolicy;
use App\Models\SourcePreviewDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SourcePreviewDomain>
 */
class SourcePreviewDomainFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain' => fake()->unique()->domainName(),
            'label' => fake()->company(),
            'policy' => SourcePreviewFetchPolicy::Allow,
            'last_seen_url' => fake()->url(),
            'last_seen_at' => now(),
        ];
    }
}
