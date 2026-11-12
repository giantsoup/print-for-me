<?php

namespace Database\Seeders;

use App\Enums\PrintQueuePlateStatus;
use App\Models\PrintQueueItem;
use Illuminate\Database\Seeder;

class PrintQueueSeeder extends Seeder
{
    public function run(): void
    {
        if (! PrintQueueItem::query()->where('title', 'Stormtrooper helmet')->exists()) {
            $helmet = PrintQueueItem::factory()->withPlates(9)->create(['title' => 'Stormtrooper helmet']);
            foreach ($helmet->plates as $plate) {
                $plate->update(['estimated_minutes' => fake()->numberBetween(120, 360)]);
            }
            $helmet->plates->first()->update(['status' => PrintQueuePlateStatus::Done, 'started_at' => now(), 'finished_at' => now()]);
        }
        if (! PrintQueueItem::query()->where('title', 'Phone stand')->exists()) {
            PrintQueueItem::factory()->withPlates(1)->create(['title' => 'Phone stand']);
        }
    }
}
