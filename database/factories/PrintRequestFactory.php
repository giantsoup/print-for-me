<?php

namespace Database\Factories;

use App\Enums\PrintRequestStatus;
use App\Models\PrintRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PrintRequest> */
class PrintRequestFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'status' => PrintRequestStatus::PENDING, 'instructions' => fake()->sentence()];
    }
}
