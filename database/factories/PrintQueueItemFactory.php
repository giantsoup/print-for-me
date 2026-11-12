<?php

namespace Database\Factories;

use App\Models\PrintQueueItem;
use App\Models\PrintQueuePlate;
use App\Models\PrintRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Str;

/** @extends Factory<PrintQueueItem> */
class PrintQueueItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => Str::title(fake()->words(3, true)),
            'for_whom' => fake()->firstName(),
            'needed_by_date' => null,
            'filament' => null,
            'notes' => null,
            'print_request_id' => null,
            'completed_at' => null,
            'position' => fn (): int => (int) PrintQueueItem::query()->active()->max('position') + 1,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['completed_at' => now(), 'position' => null]);
    }

    public function neededBy(string $date): static
    {
        return $this->state(fn (): array => ['needed_by_date' => $date]);
    }

    public function linkedTo(PrintRequest $printRequest): static
    {
        return $this->state(fn (): array => ['print_request_id' => $printRequest->id, 'for_whom' => $printRequest->user?->name]);
    }

    public function withPlates(int $count = 3): static
    {
        return $this->has(
            PrintQueuePlate::factory()->count($count)->sequence(fn (Sequence $sequence): array => [
                'position' => $sequence->index + 1,
                'name' => 'Plate '.($sequence->index + 1),
            ]),
            'plates',
        );
    }
}
