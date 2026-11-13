<?php

use App\Http\Middleware\EnforceAbsoluteSession;
use App\Models\PrintQueueItem;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

beforeEach(function () {
    $this->withoutMiddleware([EnforceAbsoluteSession::class, PreventRequestForgery::class]);
    $this->admin = User::factory()->create(['is_admin' => true, 'whitelisted_at' => now(), 'last_login_at' => now()]);
    $this->actingAs($this->admin);
});

it('moves items and heals gaps while keeping exact order', function (int $index, string $direction, array $expected) {
    $items = PrintQueueItem::factory()->count(4)->withPlates(1)->create();
    $this->patchJson(route('admin.print-queue.move', $items[$index]), ['direction' => $direction])->assertOk();
    expect(PrintQueueItem::query()->active()->orderBy('position')->pluck('id')->all())->toBe(array_map(fn (int $offset): int => $items[$offset]->id, $expected));
    expect(PrintQueueItem::query()->active()->orderBy('position')->pluck('position')->all())->toBe([1, 2, 3, 4]);
})->with([[2, 'up', [0, 2, 1, 3]], [1, 'down', [0, 2, 1, 3]], [3, 'top', [3, 0, 1, 2]], [0, 'up', [0, 1, 2, 3]], [3, 'down', [0, 1, 2, 3]]]);

it('repairs gaps and validates direction', function () {
    $items = PrintQueueItem::factory()->count(3)->withPlates(1)->create();
    foreach ([1, 4, 9] as $index => $position) {
        $items[$index]->forceFill(['position' => $position])->save();
    }
    $this->patchJson(route('admin.print-queue.move', $items[0]), ['direction' => 'up'])->assertOk();
    expect(PrintQueueItem::query()->orderBy('position')->pluck('position')->all())->toBe([1, 2, 3]);
    $this->patchJson(route('admin.print-queue.move', $items[0]), ['direction' => 'sideways'])->assertJsonValidationErrors('direction');
});

it('completes then appends reopened items without auto-completing', function () {
    $items = PrintQueueItem::factory()->count(3)->withPlates(1)->create();
    $this->patchJson(route('admin.print-queue.complete', $items[1]))->assertOk()->assertJsonPath('position', null);
    expect($items[2]->fresh()->position)->toBe(2);
    $this->patchJson(route('admin.print-queue.reopen', $items[1]))->assertOk()->assertJsonPath('position', 3)->assertJsonPath('completed_at', null);
});

it('moves plates both ways and renumbers after deletion', function () {
    $item = PrintQueueItem::factory()->withPlates(3)->create();
    $plates = $item->plates;
    $this->patchJson(route('admin.print-queue.plates.move', [$item, $plates[2]]), ['direction' => 'up'])->assertOk();
    expect($item->plates()->pluck('id')->all())->toBe([$plates[0]->id, $plates[2]->id, $plates[1]->id]);
    $this->patchJson(route('admin.print-queue.plates.move', [$item, $plates[0]]), ['direction' => 'down'])->assertOk();
    expect($item->plates()->pluck('id')->all())->toBe([$plates[2]->id, $plates[0]->id, $plates[1]->id]);
    $this->deleteJson(route('admin.print-queue.plates.destroy', [$item, $plates[0]]))->assertOk();
    expect($item->plates()->pluck('position')->all())->toBe([1, 2]);
    $this->patchJson(route('admin.print-queue.plates.move', [$item, $plates[1]]), ['direction' => 'top'])->assertJsonValidationErrors('direction');
});

it('refuses to move a completed item', function () {
    $done = PrintQueueItem::factory()->completed()->withPlates(1)->create();
    $active = PrintQueueItem::factory()->withPlates(1)->create();
    $this->patchJson(route('admin.print-queue.move', $done), ['direction' => 'top'])
        ->assertUnprocessable()->assertJsonValidationErrors(['item' => 'Reopen this item before moving it.']);
    expect($done->fresh()->position)->toBeNull()->and($active->fresh()->position)->toBe(1);
});
