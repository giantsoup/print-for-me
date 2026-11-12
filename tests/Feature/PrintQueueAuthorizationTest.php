<?php

use App\Enums\PrintQueuePlateStatus;
use App\Http\Middleware\EnforceAbsoluteSession;
use App\Models\PrintQueueItem;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

beforeEach(function () {
    $this->withoutMiddleware([EnforceAbsoluteSession::class, PreventRequestForgery::class]);
    $this->admin = User::factory()->create(['is_admin' => true, 'whitelisted_at' => now(), 'last_login_at' => now()]);
});

it('rejects every route for members and guests without side effects', function (string $method, string $action, bool $needsPlate, bool $needsItem, string $actor) {
    $item = PrintQueueItem::factory()->withPlates(1)->create();
    $plate = $item->plates->first();
    if ($actor === 'member') {
        $this->actingAs(User::factory()->create(['whitelisted_at' => now(), 'last_login_at' => now()]));
    }
    $params = $needsPlate ? [$item, $plate] : ($needsItem ? [$item] : []);
    $this->json($method, route('admin.print-queue.'.$action, $params), ['title' => 'Blocked', 'direction' => 'top', 'plates' => [['name' => 'Plate']]])->assertStatus($actor === 'member' ? 403 : 401);
    expect($plate->fresh()->status)->toBe(PrintQueuePlateStatus::Queued)->and($item->fresh()->title)->toBe($item->title);
    $this->assertDatabaseCount('print_queue_items', 1);
    $this->assertDatabaseCount('print_queue_plates', 1);
})->with([
    ['GET', 'index', false, false],
    ['GET', 'create', false, false],
    ['POST', 'store', false, false],
    ['GET', 'show', false, true],
    ['PATCH', 'update', false, true],
    ['DELETE', 'destroy', false, true],
    ['PATCH', 'move', false, true],
    ['PATCH', 'complete', false, true],
    ['PATCH', 'reopen', false, true],
    ['POST', 'plates.store', false, true],
    ['PATCH', 'plates.update', true, true],
    ['DELETE', 'plates.destroy', true, true],
    ['PATCH', 'plates.move', true, true],
    ['PATCH', 'plates.start', true, true],
    ['PATCH', 'plates.finish', true, true],
    ['PATCH', 'plates.fail', true, true],
    ['PATCH', 'plates.retry', true, true],
    ['PATCH', 'plates.requeue', true, true],
    ['PATCH', 'plates.reopen', true, true],
])->with(['member', 'guest']);

it('scopes all nested plate mutations to their parent', function (string $method, string $action) {
    $this->actingAs($this->admin);
    $item = PrintQueueItem::factory()->withPlates(1)->create();
    $other = PrintQueueItem::factory()->withPlates(1)->create();
    $this->json($method, route('admin.print-queue.plates.'.$action, [$other, $item->plates->first()]), ['name' => 'Wrong', 'direction' => 'up'])->assertNotFound();
    expect($item->plates->first()->fresh()->name)->toBe('Plate 1');
})->with([
    ['PATCH', 'update'], ['DELETE', 'destroy'], ['PATCH', 'move'], ['PATCH', 'start'], ['PATCH', 'finish'], ['PATCH', 'fail'], ['PATCH', 'retry'], ['PATCH', 'requeue'], ['PATCH', 'reopen'],
]);
