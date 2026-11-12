<?php

use App\Http\Middleware\EnforceAbsoluteSession;
use App\Models\PrintQueueItem;
use App\Models\PrintRequest;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutMiddleware([EnforceAbsoluteSession::class, PreventRequestForgery::class]);
    $this->admin = User::factory()->create(['is_admin' => true, 'whitelisted_at' => now(), 'last_login_at' => now()]);
    $this->actingAs($this->admin);
});

it('exposes the latest linked item to admins only and prefers active items', function () {
    $request = PrintRequest::factory()->create();
    $done = PrintQueueItem::factory()->linkedTo($request)->completed()->withPlates(1)->create();
    $this->get(route('print-requests.show', $request))->assertInertia(fn (Assert $page) => $page->where('queueItem.id', $done->id));
    $active = PrintQueueItem::factory()->linkedTo($request)->withPlates(1)->create();
    $this->get(route('print-requests.show', $request))->assertInertia(fn (Assert $page) => $page->where('queueItem.id', $active->id));
    $this->actingAs($request->user);
    $this->get(route('print-requests.show', $request))->assertInertia(fn (Assert $page) => $page->where('queueItem', null));
});

it('shows no queue link for unlinked requests', function () {
    $this->get(route('print-requests.show', PrintRequest::factory()->create()))->assertInertia(fn (Assert $page) => $page->where('queueItem', null));
});
