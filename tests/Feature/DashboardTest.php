<?php

use App\Http\Middleware\EnforceAbsoluteSession;
use App\Models\PrintQueueItem;
use App\Models\PrintRequest;
use App\Models\PrintRequestFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

it('builds the dashboard queue without loading completed queue items', function () {
    $this->withoutMiddleware(EnforceAbsoluteSession::class);
    $admin = User::factory()->create(['is_admin' => true]);
    $active = PrintQueueItem::factory()->withPlates(2)->create();
    $active->plates[0]->update(['estimated_minutes' => 60]);
    $active->plates[1]->update(['estimated_minutes' => null]);
    PrintQueueItem::factory()->linkedTo(PrintRequest::factory()->create(['status' => 'printing']))->completed()->withPlates(1)->create();

    DB::enableQueryLog();
    try {
        $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('queue.upNext.item.id', $active->id)
            ->where('queue.totals.active_items', 1)
            ->where('queue.totals.remaining_plates', 2)
            ->where('queue.totals.remaining_minutes', 60)
            ->where('queue.totals.unestimated_plates', 1)
            ->missing('queue.items')->missing('queue.doneItems'));
        $queries = collect(DB::getQueryLog())->pluck('query')
            ->filter(fn (string $query): bool => str_contains($query, 'print_queue_items'));
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($queries)->not->toBeEmpty();
    foreach ($queries as $query) {
        if (str_contains($query, '"completed_at" is not null')) {
            expect($query)->toMatch('/^select .* from "print_requests"/');
        }
        expect($query)->not->toStartWith('select * from "print_queue_items" where "completed_at" is not null');
    }
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertStatus(200);
});

test('dashboard only shows the signed in user queue summary and activity', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $pendingRequest = PrintRequest::create([
        'user_id' => $user->id,
        'status' => 'pending',
        'instructions' => 'Front plate reprint',
    ]);
    $pendingRequest->forceFill([
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ])->saveQuietly();

    $completedRequest = PrintRequest::create([
        'user_id' => $user->id,
        'status' => 'complete',
        'source_url' => 'https://printables.com/model/123',
    ]);
    $completedRequest->forceFill([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subMinutes(20),
        'completed_at' => now()->subMinutes(20),
    ])->saveQuietly();

    PrintRequestFile::create([
        'print_request_id' => $completedRequest->id,
        'disk' => 'local',
        'path' => 'print-requests/test-file.stl',
        'original_name' => 'test-file.stl',
        'mime_type' => 'model/stl',
        'size_bytes' => 1024,
        'sha256' => str_repeat('a', 64),
    ]);

    PrintRequest::create([
        'user_id' => $otherUser->id,
        'status' => 'printing',
        'instructions' => 'Other user request',
    ])->forceFill([
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ])->saveQuietly();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('isAdmin', false)
            ->where('statusCounts.all', 2)
            ->where('statusCounts.pending', 1)
            ->where('statusCounts.printing', 0)
            ->where('statusCounts.complete', 1)
            ->has('recentRequests', 2)
            ->where('recentRequests.0.id', $pendingRequest->id)
            ->where('recentRequests.1.id', $completedRequest->id)
            ->where('recentRequests.1.files_count', 1)
            ->has('recentActivity', 3)
            ->where('recentActivity.0.title', 'Print completed')
            ->where('recentActivity.0.request_id', $completedRequest->id)
            ->where('recentActivity.1.title', 'Request submitted')
            ->where('recentActivity.1.request_id', $pendingRequest->id)
        );
});

it('shows the next queued plate and totals only to admins', function () {
    $this->withoutMiddleware([EnforceAbsoluteSession::class]);
    $item = PrintQueueItem::factory()->withPlates(2)->create();
    $item->plates[0]->forceFill(['status' => 'failed'])->save();
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('queue.upNext.plate.id', $item->plates[1]->id)->where('queue.nowPrinting', null)->where('queue.totals.active_items', 1));
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('queue', null));
});

test('users promoted with the make admin command receive admin dashboard props', function () {
    $user = User::factory()->create([
        'email' => 'member@example.com',
        'is_admin' => false,
    ]);

    $this->artisan('auth:make-admin member@example.com')
        ->expectsOutput('Updated admin: member@example.com')
        ->assertExitCode(0);

    $user->refresh();

    expect($user->is_admin)->toBeTrue();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('isAdmin', true)
            ->where('auth.user.email', 'member@example.com')
            ->where('auth.user.is_admin', true)
        );
});

it('lists requests whose queue work is finished but are not complete', function () {
    $this->withoutMiddleware(EnforceAbsoluteSession::class);
    $request = PrintRequest::factory()->create(['status' => 'printing']);
    $done = PrintQueueItem::factory()->linkedTo($request)->completed()->withPlates(1)->create();
    $reprint = PrintRequest::factory()->create(['status' => 'printing']);
    PrintQueueItem::factory()->linkedTo($reprint)->completed()->withPlates(1)->create();
    PrintQueueItem::factory()->linkedTo($reprint)->withPlates(1)->create();
    $complete = PrintRequest::factory()->create(['status' => 'complete']);
    PrintQueueItem::factory()->linkedTo($complete)->completed()->withPlates(1)->create();
    $trashed = PrintRequest::factory()->create(['status' => 'printing']);
    PrintQueueItem::factory()->linkedTo($trashed)->completed()->withPlates(1)->create();
    $trashed->delete();
    PrintRequest::factory()->create(['status' => 'printing']);

    $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('queue.awaitingCompletion.count', 1)->has('queue.awaitingCompletion.requests', 1)
            ->where('queue.awaitingCompletion.requests.0.id', $request->id)
            ->where('queue.awaitingCompletion.requests.0.item_id', $done->id)
            ->where('queue.awaitingCompletion.requests.0.item_title', $done->title));
});

it('caps completion reminders at three requests and uses the latest completed item', function () {
    $this->withoutMiddleware(EnforceAbsoluteSession::class);
    $old = PrintRequest::factory()->create(['status' => 'pending', 'updated_at' => now()->subMonth()]);
    PrintQueueItem::factory()->linkedTo($old)->completed()->withPlates(1)->create();
    $request = PrintRequest::factory()->create(['status' => 'accepted', 'updated_at' => now()]);
    $user = $request->user;
    $user->delete();
    PrintQueueItem::factory()->linkedTo($request)->completed()->withPlates(1)->create(['completed_at' => now()->subDays(2)]);
    $latest = PrintQueueItem::factory()->linkedTo($request)->completed()->withPlates(1)->create(['completed_at' => now()]);
    PrintQueueItem::factory()->linkedTo($request)->completed()->withPlates(1)->create(['completed_at' => now()->subDay()]);
    foreach ([1, 2] as $days) {
        $other = PrintRequest::factory()->create(['status' => 'printing', 'updated_at' => now()->subDays($days)]);
        PrintQueueItem::factory()->linkedTo($other)->completed()->withPlates(1)->create();
    }

    $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('queue.awaitingCompletion.count', 4)->has('queue.awaitingCompletion.requests', 3)
            ->where('queue.awaitingCompletion.requests.0.id', $request->id)
            ->where('queue.awaitingCompletion.requests.0.user_name', $user->name)
            ->where('queue.awaitingCompletion.requests.0.item_id', $latest->id)
            ->where('queue.awaitingCompletion.requests', fn ($requests): bool => ! collect($requests)->contains('id', $old->id)));
});
