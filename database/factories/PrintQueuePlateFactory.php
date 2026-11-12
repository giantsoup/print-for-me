<?php

namespace Database\Factories;

use App\Enums\PrintQueuePlateStatus;
use App\Models\PrintQueueItem;
use App\Models\PrintQueuePlate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PrintQueuePlate> */
class PrintQueuePlateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'print_queue_item_id' => PrintQueueItem::factory(),
            'name' => 'Plate 1',
            'position' => 1,
            'status' => PrintQueuePlateStatus::Queued,
            'estimated_minutes' => fake()->numberBetween(20, 600),
            'filament' => fake()->optional()->randomElement(['PLA Basic White', 'PETG Black', 'PLA Matte Charcoal']),
        ];
    }

    public function printing(): static
    {
        return $this->state(fn (): array => ['status' => PrintQueuePlateStatus::Printing, 'started_at' => now(), 'printer_slot' => 1]);
    }

    public function done(): static
    {
        return $this->state(fn (): array => ['status' => PrintQueuePlateStatus::Done, 'started_at' => now(), 'finished_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => PrintQueuePlateStatus::Failed, 'started_at' => now(), 'finished_at' => now()]);
    }

    public function unestimated(): static
    {
        return $this->state(fn (): array => ['estimated_minutes' => null]);
    }
}
