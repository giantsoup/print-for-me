<?php

use App\Models\PrintRequest;
use App\Models\PrintRequestFile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
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
