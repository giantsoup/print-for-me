<?php

use App\Http\Middleware\EnforceAbsoluteSession;
use App\Models\PrintQueueItem;
use App\Models\PrintRequest;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutMiddleware([EnforceAbsoluteSession::class, PreventRequestForgery::class]);
    $this->admin = User::factory()->create(['is_admin' => true, 'whitelisted_at' => now(), 'last_login_at' => now()]);
    $this->actingAs($this->admin);
});

it('returns the request completion prompt to the dashboard after the final plate', function () {
    Notification::fake();
    $request = PrintRequest::factory()->create(['status' => 'printing']);
    $item = PrintQueueItem::factory()->linkedTo($request)->withPlates(1)->create();
    $plate = $item->plates[0];
    $plate->forceFill(['status' => 'printing', 'printer_slot' => 1])->save();

    $this->from(route('dashboard'))
        ->patch(route('admin.print-queue.plates.finish', [$item, $plate]))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'Open request #'.$request->id));

    expect($item->fresh()->completed_at)->not->toBeNull()
        ->and($request->fresh()->status)->toBe('printing');
    Notification::assertNothingSent();
});

it('syncs only accepted requests on start without notifications', function (string $status, bool $trashed) {
    Notification::fake();
    $request = PrintRequest::factory()->create(['status' => $status]);
    $item = PrintQueueItem::factory()->linkedTo($request)->withPlates(1)->create();
    if ($trashed) {
        $request->delete();
    }
    $response = $this->from(route('admin.print-queue.show', $item))->patch(route('admin.print-queue.plates.start', [$item, $item->plates[0]]));
    $response->assertSessionHasNoErrors();
    expect($request->fresh()->status)->toBe($status === 'accepted' && ! $trashed ? 'printing' : $status);
    if ($status === 'pending' && ! $trashed) {
        $response->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'still pending'));
    }
    Notification::assertNothingSent();
})->with([['accepted', false], ['pending', false], ['accepted', true], ['printing', false], ['complete', false]]);

it('never completes or reverts linked requests automatically', function (string $action) {
    Notification::fake();
    $request = PrintRequest::factory()->create(['status' => 'printing']);
    $item = PrintQueueItem::factory()->linkedTo($request)->withPlates(1)->create();
    $plate = $item->plates[0];
    if ($action !== 'manual') {
        $plate->forceFill(['status' => 'printing', 'printer_slot' => 1, 'started_at' => now()])->save();
    }
    $route = $action === 'manual' ? route('admin.print-queue.complete', $item) : route('admin.print-queue.plates.'.$action, [$item, $plate]);
    $response = $this->from(route('admin.print-queue.show', $item))->patch($route)->assertSessionHasNoErrors();
    expect($request->fresh()->status)->toBe('printing');
    if (in_array($action, ['finish', 'manual'], true)) {
        $response->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'Open request #'));
    }
    Notification::assertNothingSent();
})->with(['finish', 'manual', 'fail', 'requeue']);

it('distinguishes manual completion from finishing all plates in the flash', function (bool $linked) {
    $request = $linked ? PrintRequest::factory()->create(['status' => 'printing']) : null;
    $item = PrintQueueItem::factory()->withPlates(1)->create(['print_request_id' => $request?->id]);

    $response = $this->from(route('admin.print-queue.show', $item))
        ->patch(route('admin.print-queue.complete', $item))->assertSessionHasNoErrors();

    $response->assertSessionHas('status', fn (string $message): bool => str_starts_with($message, 'Item marked done.'));
    if ($request !== null) {
        $response->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'Open request #'.$request->id));
    }
    expect($item->fresh()->completed_at)->not->toBeNull()
        ->and($item->plates[0]->fresh()->status->value)->toBe('queued');
})->with([false, true]);

it('moves a request accepted after the first start to printing when the item completes', function () {
    Notification::fake();
    $request = PrintRequest::factory()->create(['status' => 'pending']);
    $item = PrintQueueItem::factory()->linkedTo($request)->withPlates(1)->create();
    $plate = $item->plates[0];
    $this->patch(route('admin.print-queue.plates.start', [$item, $plate]))
        ->assertSessionHasNoErrors()->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'still pending'));
    $request->forceFill(['status' => 'accepted'])->save();
    $this->patch(route('admin.print-queue.plates.finish', [$item, $plate]))
        ->assertSessionHasNoErrors()->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'Open request #'.$request->id));
    expect($request->fresh()->status)->toBe('printing');
    Notification::assertNothingSent();
});

it('keeps a pending request pending on manual completion and explains the next step', function () {
    Notification::fake();
    $request = PrintRequest::factory()->create(['status' => 'pending']);
    $item = PrintQueueItem::factory()->linkedTo($request)->withPlates(1)->create();
    $this->patch(route('admin.print-queue.complete', $item))
        ->assertSessionHasNoErrors()->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'still pending') && str_contains($message, 'then mark it complete'));
    expect($request->fresh()->status)->toBe('pending');
    Notification::assertNothingSent();
});

it('uses soft-deleted requester names in completion notes', function () {
    Notification::fake();
    $request = PrintRequest::factory()->create(['status' => 'accepted']);
    $user = $request->user;
    $user->delete();
    $item = PrintQueueItem::factory()->linkedTo($request)->withPlates(1)->create();
    $this->patch(route('admin.print-queue.complete', $item))
        ->assertSessionHasNoErrors()->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'notify '.$user->name));
    expect($request->fresh()->status)->toBe('printing');
    Notification::assertNothingSent();
});
