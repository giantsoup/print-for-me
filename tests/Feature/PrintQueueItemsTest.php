<?php

use App\Enums\PrintQueuePlateStatus;
use App\Http\Middleware\EnforceAbsoluteSession;
use App\Models\PrintQueueItem;
use App\Models\PrintRequest;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutMiddleware([EnforceAbsoluteSession::class, PreventRequestForgery::class]);
    $this->admin = User::factory()->create(['is_admin' => true, 'whitelisted_at' => now(), 'last_login_at' => now()]);
    $this->actingAs($this->admin);
});

it('creates ordered plates with the factory', function () {
    expect(PrintQueueItem::factory()->withPlates(3)->create()->plates->pluck('position')->all())->toBe([1, 2, 3]);
});

it('indexes needed by dates for queue planning', function () {
    expect(Schema::hasIndex('print_queue_items', ['needed_by_date']))->toBeTrue();
});

it('shows ordered items with remaining estimates and latest completed items', function () {
    $second = PrintQueueItem::factory()->withPlates(3)->create(['position' => 2]);
    $first = PrintQueueItem::factory()->withPlates(1)->create(['position' => 1]);
    $second->plates[0]->update(['status' => PrintQueuePlateStatus::Done, 'estimated_minutes' => 900]);
    $second->plates[1]->update(['estimated_minutes' => 120]);
    $second->plates[2]->update(['estimated_minutes' => null]);
    $first->plates[0]->update(['estimated_minutes' => 30]);
    $done = PrintQueueItem::factory()->completed()->withPlates(1)->create();
    $this->get(route('admin.print-queue.index'))->assertInertia(fn (Assert $page) => $page->component('admin/queue/Index', false)->where('items.0.id', $first->id)->where('items.1.id', $second->id)->where('doneItems.0.id', $done->id)->where('totals.remaining_minutes', 150)->where('totals.unestimated_plates', 1)->where('totals.remaining_plates', 3));
});

it('prefills a linkable request and ignores completed or deleted requests', function () {
    $request = PrintRequest::factory()->create(['source_preview' => ['title' => 'Helmet'], 'needed_by_date' => '2026-12-01']);
    $this->get(route('admin.print-queue.create', ['print_request' => $request->id]))->assertInertia(fn (Assert $page) => $page->component('admin/queue/Create', false)->where('prefill.title', 'Helmet')->where('prefill.for_whom', $request->user->name)->where('prefill.needed_by_date', '2026-12-01'));
    $request->update(['source_preview' => null]);
    $this->get(route('admin.print-queue.create', ['print_request' => $request->id]))->assertInertia(fn (Assert $page) => $page->where('prefill.title', 'Request #'.$request->id));
    $request->update(['status' => 'complete']);
    $this->get(route('admin.print-queue.create', ['print_request' => $request->id]))->assertInertia(fn (Assert $page) => $page->where('prefill', null));
    $request->update(['status' => 'pending']);
    $request->delete();
    $this->get(route('admin.print-queue.create', ['print_request' => $request->id]))->assertInertia(fn (Assert $page) => $page->where('prefill', null));
});

it('stores plates at the end or top and autofills the requester', function (bool $top) {
    $old = PrintQueueItem::factory()->withPlates(1)->create();
    $request = PrintRequest::factory()->create();
    $this->post(route('admin.print-queue.store'), ['title' => 'New print', 'for_whom' => '', 'print_request_id' => $request->id, 'add_to_top' => $top, 'plates' => [['name' => 'Front', 'estimated_minutes' => 90, 'filament' => 'PLA'], ['name' => 'Back']]])->assertSessionHasNoErrors()->assertRedirect(route('admin.print-queue.show', PrintQueueItem::query()->latest('id')->first()));
    $item = PrintQueueItem::query()->latest('id')->first();
    expect($item->position)->toBe($top ? 1 : 2)->and($old->fresh()->position)->toBe($top ? 2 : 1)->and($item->for_whom)->toBe($request->user->name)->and($item->plates->pluck('position')->all())->toBe([1, 2])->and($item->plates[0]->estimated_minutes)->toBe(90)->and($item->plates[0]->filament)->toBe('PLA');
})->with([false, true]);

it('validates queue payloads', function (array $override, string $key, string $message) {
    $response = $this->postJson(route('admin.print-queue.store'), array_replace(['title' => 'Print', 'plates' => [['name' => 'Plate']]], $override));
    $response->assertUnprocessable()->assertJsonValidationErrors($key);
    expect($response->json('errors')[$key][0])->toBe($message);
    $this->assertDatabaseCount('print_queue_items', 0);
})->with([
    [['title' => ''], 'title', 'Give this queue item a title.'],
    [['plates' => []], 'plates', 'Add at least one plate.'],
    [['plates' => array_fill(0, 51, ['name' => 'Plate'])], 'plates', 'Add up to 50 plates.'],
    [['plates' => ['first' => ['name' => 'Front'], 'second' => ['name' => 'Back']]], 'plates', 'Add plates as a list.'],
    [['plates' => [3 => ['name' => 'Front'], 9 => ['name' => 'Back']]], 'plates', 'Add plates as a list.'],
    [['plates' => 'invalid'], 'plates', 'Add plates as a list.'],
    [['title' => str_repeat('a', 161)], 'title', 'Keep the title to 160 characters.'],
    [['for_whom' => str_repeat('a', 121)], 'for_whom', 'Keep the name to 120 characters.'],
    [['filament' => str_repeat('a', 161)], 'filament', 'Keep filament details to 160 characters.'],
    [['notes' => str_repeat('a', 5001)], 'notes', 'Keep notes to 5000 characters.'],
    [['print_request_id' => 'invalid'], 'print_request_id', 'Choose a request from the list.'],
    [['print_request_id' => 999999], 'print_request_id', 'Choose a request from the list.'],
    [['add_to_top' => 'invalid'], 'add_to_top', 'Choose whether to add this item to the top.'],
    [['plates' => [['name' => str_repeat('a', 121)]]], 'plates.0.name', 'Keep plate names to 120 characters.'],
    [['plates' => [['name' => 'Plate', 'estimated_minutes' => '90.5']]], 'plates.0.estimated_minutes', 'Estimated time must be whole minutes.'],
    [['plates' => [['name' => 'Plate', 'filament' => str_repeat('a', 161)]]], 'plates.0.filament', 'Keep filament details to 160 characters.'],
    [['plates' => [['name' => '']]], 'plates.0.name', 'Name every plate.'],
    [['plates' => [['name' => 'Plate', 'estimated_minutes' => 0]]], 'plates.0.estimated_minutes', 'Estimated time must be between 1 minute and 7 days.'],
    [['plates' => [['name' => 'Plate', 'estimated_minutes' => 10081]]], 'plates.0.estimated_minutes', 'Estimated time must be between 1 minute and 7 days.'],
    [['needed_by_date' => '13/45/2026'], 'needed_by_date', 'Enter the needed-by date as MM/DD/YYYY.'],
]);

it('rejects unlinked invalid requests', function (string $state) {
    $request = PrintRequest::factory()->create(['status' => $state === 'complete' ? 'complete' : 'pending']);
    if ($state === 'trashed') {
        $request->delete();
    }
    if ($state === 'linked') {
        PrintQueueItem::factory()->linkedTo($request)->withPlates(1)->create();
    }
    $this->postJson(route('admin.print-queue.store'), ['title' => 'Print', 'print_request_id' => $request->id, 'plates' => [['name' => 'Plate']]])->assertUnprocessable()->assertJsonValidationErrors('print_request_id');
})->with(['complete', 'trashed', 'linked']);

it('updates the same linked item and preserves soft deleted links', function () {
    $request = PrintRequest::factory()->create();
    $item = PrintQueueItem::factory()->linkedTo($request)->withPlates(1)->create();
    $this->patchJson(route('admin.print-queue.update', $item), ['title' => 'Changed', 'print_request_id' => $request->id, 'for_whom' => ''])->assertOk()->assertJsonPath('title', 'Changed')->assertJsonPath('for_whom', $request->user->name);
    $request->delete();
    $this->get(route('admin.print-queue.show', $item))->assertInertia(fn (Assert $page) => $page->component('admin/queue/Show', false)->where('item.linked_request.is_deleted', true));
});

it('cascades item deletion and renumbers survivors', function () {
    $item = PrintQueueItem::factory()->withPlates(3)->create();
    $survivor = PrintQueueItem::factory()->withPlates(1)->create();
    $this->delete(route('admin.print-queue.destroy', $item))->assertRedirect(route('admin.print-queue.index'));
    expect($survivor->fresh()->position)->toBe(1);
    $this->assertDatabaseCount('print_queue_plates', 1);
});

it('edits notes without revalidating an unchanged request link', function (string $state) {
    $request = PrintRequest::factory()->create();
    $item = PrintQueueItem::factory()->linkedTo($request)->withPlates(1)->create();
    if ($state === 'complete') {
        $request->update(['status' => 'complete']);
    } else {
        $request->delete();
    }

    $this->patchJson(route('admin.print-queue.update', $item), [
        'title' => $item->title,
        'notes' => 'Updated after request lifecycle changed.',
        'print_request_id' => (string) $request->id,
    ])->assertOk()->assertJsonPath('notes', 'Updated after request lifecycle changed.');

    expect($item->fresh()->print_request_id)->toBe($request->id);
})->with(['complete', 'trashed']);

it('still validates a changed request link', function (string $state) {
    $item = PrintQueueItem::factory()->withPlates(1)->create();
    $request = PrintRequest::factory()->create();
    if ($state === 'complete') {
        $request->update(['status' => 'complete']);
    } elseif ($state === 'trashed') {
        $request->delete();
    } else {
        PrintQueueItem::factory()->linkedTo($request)->withPlates(1)->create();
    }

    $this->patchJson(route('admin.print-queue.update', $item), [
        'title' => $item->title,
        'print_request_id' => $request->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('print_request_id');

    expect($item->fresh()->print_request_id)->toBeNull();
})->with(['complete', 'trashed', 'linked']);

it('refuses deleting an item while its plate is printing', function () {
    $item = PrintQueueItem::factory()->withPlates(2)->create();
    $item->plates[0]->forceFill(['status' => 'printing', 'printer_slot' => 1])->save();

    $this->deleteJson(route('admin.print-queue.destroy', $item))
        ->assertUnprocessable()->assertJsonValidationErrors('item')
        ->assertJsonPath('errors.item.0', 'Finish or requeue the printing plate first.');

    expect($item->fresh())->not->toBeNull()->and($item->plates()->count())->toBe(2);
});

it('refuses reopening a linked item if another active item owns the link', function () {
    $request = PrintRequest::factory()->create();
    $done = PrintQueueItem::factory()->linkedTo($request)->completed()->withPlates(1)->create();
    $active = PrintQueueItem::factory()->linkedTo($request)->withPlates(1)->create();

    $this->patchJson(route('admin.print-queue.reopen', $done))
        ->assertUnprocessable()->assertJsonValidationErrors('item')
        ->assertJsonPath('errors.item.0', 'Another active item is already linked to this request.');

    expect($done->fresh()->completed_at)->not->toBeNull()
        ->and($done->fresh()->position)->toBeNull()
        ->and($active->fresh()->position)->toBe(1);
});

it('scrubs active queue items and deletes completed ones when force deleting a request', function (string $titleSource) {
    $request = PrintRequest::factory()->for($this->admin, 'user')->create([
        'source_preview' => ['title' => 'Private source title'],
        'source_url' => 'https://example.com/private-model',
        'instructions' => 'Private instructions',
    ]);
    $title = match ($titleSource) {
        'fallback' => 'Request #'.$request->id,
        'preview' => $request->source_preview['title'],
        'url' => $request->source_url,
        default => 'Custom project title',
    };
    $item = PrintQueueItem::factory()->linkedTo($request)->withPlates(2)->create([
        'title' => $title,
        'notes' => $request->instructions,
    ]);
    $plate = $item->plates[0];
    $plate->forceFill(['status' => 'printing', 'printer_slot' => 1])->save();
    $completed = PrintQueueItem::factory()->linkedTo($request)->completed()->withPlates(1)->create();
    $completedPlateId = $completed->plates[0]->id;
    $other = PrintQueueItem::factory()->withPlates(1)->create();
    $request->delete();

    $this->delete(route('print-requests.force-destroy', $request))->assertRedirect();

    $this->assertDatabaseMissing('print_requests', ['id' => $request->id]);
    $this->assertDatabaseMissing('print_queue_items', ['id' => $completed->id]);
    $this->assertDatabaseMissing('print_queue_plates', ['id' => $completedPlateId]);
    expect($item->fresh())
        ->print_request_id->toBeNull()
        ->for_whom->toBeNull()
        ->notes->toBeNull()
        ->title->toBe($titleSource === 'custom' ? $title : 'Untitled print')
        ->position->toBe(1)
        ->and($item->plates()->count())->toBe(2)
        ->and($plate->fresh()->status->value)->toBe('printing')
        ->and($plate->fresh()->printer_slot)->toBe(1)
        ->and($other->fresh()->position)->toBe(2);
})->with(['fallback', 'preview', 'url', 'custom']);

it('does not allow nested creation payloads to set plate lifecycle fields', function () {
    $this->postJson(route('admin.print-queue.store'), ['title' => 'Safe', 'position' => 99, 'completed_at' => now()->toIso8601String(), 'plates' => [['name' => 'Plate', 'position' => 99, 'status' => 'printing', 'printer_slot' => 1, 'started_at' => now()->toIso8601String()]]])->assertCreated();
    $item = PrintQueueItem::query()->first();
    expect($item->position)->toBe(1)->and($item->completed_at)->toBeNull()->and($item->plates[0]->position)->toBe(1)->and($item->plates[0]->status)->toBe(PrintQueuePlateStatus::Queued)->and($item->plates[0]->printer_slot)->toBeNull()->and($item->plates[0]->started_at)->toBeNull();
});

it('keeps an older current request in the edit selector', function () {
    $request = PrintRequest::factory()->create(['created_at' => now()->subYear()]);
    $item = PrintQueueItem::factory()->linkedTo($request)->withPlates(1)->create();
    PrintRequest::factory()->count(101)->create();
    $this->get(route('admin.print-queue.show', $item))->assertInertia(fn (Assert $page) => $page->component('admin/queue/Show', false)->where('linkableRequests', fn ($requests): bool => collect($requests)->contains('id', $request->id)));
});

it('limits done rows to the latest ten without loading plates', function () {
    $old = PrintQueueItem::factory()->completed()->withPlates(1)->create(['completed_at' => now()->subMonth()]);
    PrintQueueItem::factory()->count(10)->completed()->withPlates(1)->create();
    $this->get(route('admin.print-queue.index'))->assertInertia(fn (Assert $page) => $page->component('admin/queue/Index', false)->has('doneItems', 10)->where('doneItems', fn ($items): bool => ! collect($items)->contains('id', $old->id) && ! array_key_exists('plates', (array) collect($items)->first())));
});

it('prefills older linkable requests outside the hundred latest options', function () {
    $request = PrintRequest::factory()->create(['created_at' => now()->subYear()]);
    PrintRequest::factory()->count(101)->create();
    $this->get(route('admin.print-queue.create', ['print_request' => $request->id]))->assertInertia(fn (Assert $page) => $page->component('admin/queue/Create', false)->where('prefill.print_request_id', $request->id)->where('linkableRequests', fn ($requests): bool => collect($requests)->contains('id', $request->id)));
});
