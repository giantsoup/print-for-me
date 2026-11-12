<?php

use App\Enums\PrintQueuePlateStatus;
use App\Http\Middleware\EnforceAbsoluteSession;
use App\Models\PrintQueueItem;
use App\Models\PrintQueuePlate;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutMiddleware([EnforceAbsoluteSession::class, PreventRequestForgery::class]);
    $this->admin = User::factory()->create(['is_admin' => true, 'whitelisted_at' => now(), 'last_login_at' => now()]);
    $this->actingAs($this->admin);
});

it('refreshes available actions when the same controller handles later requests', function () {
    $item = PrintQueueItem::factory()->withPlates(1)->create();
    $this->get(route('admin.print-queue.show', $item))->assertInertia(fn (Assert $page) => $page
        ->component('admin/queue/Show', false)
        ->where('printerBusy', false)
        ->where('item.plates.0.availableActions', ['start']));

    $busy = PrintQueuePlate::factory()->printing()->create();
    $this->get(route('admin.print-queue.show', $item))->assertInertia(fn (Assert $page) => $page
        ->where('printerBusy', true)
        ->where('item.plates.0.availableActions', []));

    $busy->forceFill(['status' => 'done', 'printer_slot' => null])->save();
    $this->get(route('admin.print-queue.show', $item))->assertInertia(fn (Assert $page) => $page
        ->where('printerBusy', false)
        ->where('item.plates.0.availableActions', ['start']));
});

it('uses custom plate validation messages on create and update', function (string $operation, array $payload, string $key, string $message) {
    $item = PrintQueueItem::factory()->withPlates(1)->create();
    $data = array_replace(['name' => 'Plate'], $payload);
    $response = $operation === 'store'
        ? $this->postJson(route('admin.print-queue.plates.store', $item), $data)
        : $this->patchJson(route('admin.print-queue.plates.update', [$item, $item->plates[0]]), $data);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors($key)
        ->assertJsonPath('errors.'.$key.'.0', $message);
})->with(['store', 'update'])->with([
    [['name' => str_repeat('a', 121)], 'name', 'Keep plate names to 120 characters.'],
    [['estimated_minutes' => '90.5'], 'estimated_minutes', 'Estimated time must be whole minutes.'],
    [['filament' => str_repeat('a', 161)], 'filament', 'Keep filament details to 160 characters.'],
    [['notes' => str_repeat('a', 2001)], 'notes', 'Keep notes to 2000 characters.'],
]);

it('defines every plate transition and label', function () {
    foreach (PrintQueuePlateStatus::cases() as $source) {
        expect($source->label())->toBe(ucfirst($source->value));
        foreach (PrintQueuePlateStatus::cases() as $target) {
            $allowed = match ($source) {
                PrintQueuePlateStatus::Queued => $target === PrintQueuePlateStatus::Printing,
                PrintQueuePlateStatus::Printing => in_array($target, [PrintQueuePlateStatus::Queued, PrintQueuePlateStatus::Done, PrintQueuePlateStatus::Failed], true),
                default => $target === PrintQueuePlateStatus::Queued,
            };
            expect($source->canTransitionTo($target))->toBe($allowed);
        }
    }
});

it('refuses deleting a printing plate without losing its printer slot', function () {
    $item = PrintQueueItem::factory()->withPlates(2)->create();
    $plate = $item->plates[0];
    $plate->forceFill(['status' => 'printing', 'printer_slot' => 1])->save();

    $this->deleteJson(route('admin.print-queue.plates.destroy', [$item, $plate]))
        ->assertUnprocessable()->assertJsonValidationErrors('plate')
        ->assertJsonPath('errors.plate.0', 'Finish or requeue the printing plate first.');

    expect($plate->fresh()->status)->toBe(PrintQueuePlateStatus::Printing)
        ->and($plate->fresh()->printer_slot)->toBe(1)
        ->and($item->plates()->count())->toBe(2);
});

it('translates a printer slot uniqueness collision into friendly validation', function () {
    $occupied = PrintQueuePlate::factory()->create();
    $occupied->forceFill(['printer_slot' => 1])->save();
    $plate = PrintQueuePlate::factory()->create();

    $this->patchJson(route('admin.print-queue.plates.start', [$plate->item, $plate]))
        ->assertUnprocessable()->assertJsonValidationErrors('status')
        ->assertJsonPath('errors.status.0', 'Another plate is already printing. Finish or requeue it first.');

    expect($plate->fresh()->status)->toBe(PrintQueuePlateStatus::Queued)
        ->and($plate->fresh()->printer_slot)->toBeNull()
        ->and($occupied->fresh()->printer_slot)->toBe(1);
});

it('uses readable plate transition flashes', function (string $initial, string $action, string $message) {
    $item = PrintQueueItem::factory()->withPlates(2)->create();
    $plate = $item->plates[0];
    $plate->forceFill(['status' => $initial, 'printer_slot' => $initial === 'printing' ? 1 : null])->save();

    $this->from(route('admin.print-queue.show', $item))
        ->patch(route('admin.print-queue.plates.'.$action, [$item, $plate]))
        ->assertSessionHasNoErrors()->assertSessionHas('status', $message);
})->with([
    ['queued', 'start', 'Plate 1 started.'],
    ['printing', 'finish', 'Plate 1 marked done.'],
    ['printing', 'fail', 'Plate 1 marked failed.'],
    ['failed', 'retry', 'Plate 1 queued for retry.'],
    ['printing', 'requeue', 'Plate 1 requeued.'],
    ['done', 'reopen', 'Plate 1 reopened.'],
]);

it('capitalizes unavailable actions and uses lowercase status', function () {
    $item = PrintQueueItem::factory()->withPlates(1)->create();
    $plate = $item->plates[0];
    $plate->update(['status' => 'done']);

    $this->patchJson(route('admin.print-queue.plates.start', [$item, $plate]))
        ->assertUnprocessable()->assertJsonPath('errors.status.0', 'Start is not available for a done plate.');
});

it('performs valid plate transitions and timestamp changes', function (string $initial, string $action, string $target) {
    $item = PrintQueueItem::factory()->withPlates(2)->create();
    $plate = $item->plates[0];
    $plate->forceFill(['status' => $initial, 'started_at' => $initial === 'queued' ? null : now(), 'finished_at' => in_array($initial, ['done', 'failed'], true) ? now() : null, 'printer_slot' => $initial === 'printing' ? 1 : null])->save();
    $this->patchJson(route('admin.print-queue.plates.'.$action, [$item, $plate]))->assertOk();
    $plate->refresh();
    expect($plate->status->value)->toBe($target)->and($plate->printer_slot)->toBe($target === 'printing' ? 1 : null);
    if ($target === 'queued') {
        expect($plate->started_at)->toBeNull()->and($plate->finished_at)->toBeNull();
    } else {
        expect($plate->started_at)->not->toBeNull();
        expect($plate->finished_at !== null)->toBe(in_array($target, ['done', 'failed'], true));
    }
})->with([['queued', 'start', 'printing'], ['printing', 'finish', 'done'], ['printing', 'fail', 'failed'], ['failed', 'retry', 'queued'], ['printing', 'requeue', 'queued'], ['done', 'reopen', 'queued']]);

it('rejects every invalid plate transition without changes', function (string $initial, string $action) {
    $item = PrintQueueItem::factory()->withPlates(1)->create();
    $plate = $item->plates[0];
    $plate->forceFill(['status' => $initial, 'printer_slot' => $initial === 'printing' ? 1 : null])->save();
    $original = $plate->fresh()->getAttributes();
    $this->patchJson(route('admin.print-queue.plates.'.$action, [$item, $plate]))->assertUnprocessable()->assertJsonValidationErrors('status');
    expect($plate->fresh()->getAttributes())->toBe($original);
})->with([
    ['queued', 'finish'], ['queued', 'fail'], ['queued', 'retry'], ['queued', 'requeue'], ['queued', 'reopen'],
    ['printing', 'start'], ['printing', 'retry'], ['printing', 'reopen'],
    ['done', 'start'], ['done', 'finish'], ['done', 'fail'], ['done', 'retry'], ['done', 'requeue'],
    ['failed', 'start'], ['failed', 'finish'], ['failed', 'fail'], ['failed', 'requeue'], ['failed', 'reopen'],
]);

it('enforces the single printer in HTTP actions and available actions', function () {
    PrintQueuePlate::factory()->printing()->create();
    $item = PrintQueueItem::factory()->withPlates(1)->create();
    $plate = $item->plates[0];
    $this->patchJson(route('admin.print-queue.plates.start', [$item, $plate]))->assertUnprocessable()->assertJsonPath('errors.status.0', 'Another plate is already printing. Finish or requeue it first.');
    expect($plate->fresh()->status)->toBe(PrintQueuePlateStatus::Queued);
    $this->get(route('admin.print-queue.show', $item))->assertInertia(fn (Assert $page) => $page->component('admin/queue/Show', false)->where('printerBusy', true)->where('item.plates.0.availableActions', []));
});

it('enforces the single printer at the database level', function () {
    PrintQueuePlate::factory()->printing()->create();
    expect(fn () => PrintQueuePlate::factory()->printing()->create())->toThrow(UniqueConstraintViolationException::class);
});

it('blocks starts and additions on completed items', function () {
    $item = PrintQueueItem::factory()->completed()->withPlates(1)->create();
    $this->patchJson(route('admin.print-queue.plates.start', [$item, $item->plates[0]]))->assertJsonValidationErrors('status');
    $this->postJson(route('admin.print-queue.plates.store', $item), ['name' => 'Extra'])->assertJsonValidationErrors('plate');
});

it('auto-completes the last finished plate but not when failed plates remain', function (bool $failed) {
    $item = PrintQueueItem::factory()->withPlates(2)->create();
    $other = PrintQueueItem::factory()->withPlates(1)->create();
    $item->plates[0]->update(['status' => $failed ? 'failed' : 'done']);
    $plate = $item->plates[1];
    $plate->forceFill(['status' => 'printing', 'printer_slot' => 1, 'started_at' => now()])->save();
    $this->patchJson(route('admin.print-queue.plates.finish', [$item, $plate]))->assertOk();
    expect($item->fresh()->completed_at !== null)->toBe(! $failed)->and($item->fresh()->position)->toBe($failed ? 1 : null)->and($other->fresh()->position)->toBe($failed ? 2 : 1);
})->with([false, true]);

it('protects the last plate and auto-completes when deleting unfinished leftovers', function () {
    $item = PrintQueueItem::factory()->withPlates(2)->create();
    $item->plates[0]->update(['status' => 'done']);
    $this->deleteJson(route('admin.print-queue.plates.destroy', [$item, $item->plates[1]]))->assertOk();
    expect($item->fresh()->completed_at)->not->toBeNull();
    $this->deleteJson(route('admin.print-queue.plates.destroy', [$item, $item->plates[0]]))->assertJsonValidationErrors('plate');
});

it('blocks manual completion while printing and allows queued leftovers', function () {
    $plate = PrintQueuePlate::factory()->printing()->create();
    $this->patchJson(route('admin.print-queue.complete', $plate->item))->assertJsonValidationErrors('plate');
    $this->patchJson(route('admin.print-queue.plates.requeue', [$plate->item, $plate]))->assertOk();
    $this->patchJson(route('admin.print-queue.complete', $plate->item))->assertOk();
    expect($plate->fresh()->status)->toBe(PrintQueuePlateStatus::Queued)->and($plate->item->fresh()->completed_at)->not->toBeNull();
});

it('adds and edits plate metadata without changing protected state', function () {
    $item = PrintQueueItem::factory()->withPlates(1)->create();
    $this->postJson(route('admin.print-queue.plates.store', $item), ['name' => 'Second', 'estimated_minutes' => 90, 'status' => 'printing', 'printer_slot' => 1])->assertOk();
    $plate = $item->plates()->get()->last();
    expect($plate->position)->toBe(2)->and($plate->status)->toBe(PrintQueuePlateStatus::Queued)->and($plate->printer_slot)->toBeNull();
    $this->patchJson(route('admin.print-queue.plates.update', [$item, $plate]), ['name' => 'Renamed', 'estimated_minutes' => 65, 'filament' => 'PETG', 'notes' => 'Slow'])->assertOk();
    expect($plate->fresh()->name)->toBe('Renamed')->and($plate->fresh()->notes)->toBe('Slow');
    $this->patchJson(route('admin.print-queue.plates.update', [$item, $plate]), ['name' => '', 'estimated_minutes' => 10081])->assertJsonValidationErrors(['name', 'estimated_minutes']);
});
