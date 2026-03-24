<?php

use App\Enums\SourcePreviewFetchPolicy;
use App\Http\Middleware\EnforceAbsoluteSession;
use App\Models\PrintRequest;
use App\Models\SourcePreviewDomain;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->withoutMiddleware([EnforceAbsoluteSession::class]);
    $this->withoutMiddleware([PreventRequestForgery::class]);
});

function previewAdmin(array $attributes = []): User
{
    return User::factory()->create([
        'is_admin' => true,
        'whitelisted_at' => now(),
        'last_login_at' => now(),
        ...$attributes,
    ]);
}

it('blocks non-admins from source preview domain routes', function () {
    $user = User::factory()->create(['whitelisted_at' => now()]);
    $domain = SourcePreviewDomain::factory()->create();

    actingAs($user);

    get(route('admin.source-preview-domains.index'))->assertForbidden();
    patch(route('admin.source-preview-domains.update', $domain), ['policy' => 'allow'])->assertForbidden();
    post(route('admin.source-preview-domains.attempt', $domain))->assertForbidden();
});

it('loads the source preview domain workspace with seeded popular domains', function () {
    $admin = previewAdmin();

    actingAs($admin);

    get(route('admin.source-preview-domains.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/source-previews/Index')
            ->where('domains', fn ($domains) => collect($domains)->pluck('domain')->contains('makerworld.com')
                && collect($domains)->pluck('domain')->contains('printables.com'))
            ->where('summary.tracked', fn (int $count) => $count >= 6)
        );

    assertDatabaseHas('source_preview_domains', [
        'domain' => 'makerworld.com',
        'policy' => SourcePreviewFetchPolicy::Block->value,
    ]);
});

it('updates the preview policy for a tracked domain', function () {
    $admin = previewAdmin();
    $domain = SourcePreviewDomain::factory()->create([
        'domain' => 'makerworld.com',
        'label' => 'MakerWorld',
        'policy' => SourcePreviewFetchPolicy::Block,
    ]);

    actingAs($admin);

    patch(route('admin.source-preview-domains.update', $domain), [
        'policy' => SourcePreviewFetchPolicy::Allow->value,
    ])->assertRedirect()
        ->assertSessionHas('status', 'Preview policy updated for MakerWorld.');

    assertDatabaseHas('source_preview_domains', [
        'id' => $domain->id,
        'policy' => SourcePreviewFetchPolicy::Allow->value,
    ]);
});

it('manually retries the latest tracked request URL for a domain', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://thingiverse.com/*' => Http::response(
            <<<'HTML'
            <html>
                <head>
                    <title>Ignored title</title>
                    <meta property="og:site_name" content="Thingiverse">
                    <meta property="og:title" content="Wall Hook">
                    <meta property="og:description" content="A sturdy wall hook for shop organization.">
                    <meta property="og:image" content="/assets/wall-hook.png">
                </head>
            </html>
            HTML,
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        ),
    ]);

    $admin = previewAdmin();
    $request = PrintRequest::create([
        'user_id' => User::factory()->create()->id,
        'status' => 'pending',
        'source_url' => 'https://thingiverse.com/thing:123456',
    ]);

    $domain = SourcePreviewDomain::factory()->create([
        'domain' => 'thingiverse.com',
        'label' => 'Thingiverse',
        'policy' => SourcePreviewFetchPolicy::Block,
        'last_seen_print_request_id' => $request->id,
        'last_seen_url' => $request->source_url,
        'last_seen_at' => now(),
    ]);

    actingAs($admin);

    post(route('admin.source-preview-domains.attempt', $domain))
        ->assertRedirect()
        ->assertSessionHas('status', 'Preview fetch succeeded for Thingiverse.');

    $request->refresh();
    $domain->refresh();

    expect($request->source_preview)->toMatchArray([
        'url' => 'https://thingiverse.com/thing:123456',
        'domain' => 'thingiverse.com',
        'site_name' => 'Thingiverse',
        'title' => 'Wall Hook',
        'description' => 'A sturdy wall hook for shop organization.',
        'image_url' => 'https://thingiverse.com/assets/wall-hook.png',
    ]);

    expect($request->source_preview_fetched_at)->not->toBeNull();
    expect($request->source_preview_failed_at)->toBeNull();
    expect($domain->last_attempt_status)->toBe('success');
    expect($domain->last_success_at)->not->toBeNull();
});

it('tests an arbitrary admin-provided url for a domain without a tracked request', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://printables.com/*' => Http::response(
            <<<'HTML'
            <html>
                <head>
                    <meta property="og:site_name" content="Printables">
                    <meta property="og:title" content="Cable Clip">
                    <meta property="og:description" content="A simple clip for routing USB cables.">
                </head>
            </html>
            HTML,
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        ),
    ]);

    $admin = previewAdmin();
    $domain = SourcePreviewDomain::factory()->create([
        'domain' => 'printables.com',
        'label' => 'Printables',
        'policy' => SourcePreviewFetchPolicy::Allow,
        'last_seen_print_request_id' => null,
        'last_seen_url' => null,
        'last_seen_at' => null,
    ]);

    actingAs($admin);

    post(route('admin.source-preview-domains.attempt-url', $domain), [
        'url' => 'https://printables.com/model/123-cable-clip',
    ])->assertRedirect()
        ->assertSessionHas('status', 'Preview fetch succeeded for Printables.');

    $domain->refresh();

    expect($domain->last_attempt_status)->toBe('success');
    expect($domain->last_attempted_at)->not->toBeNull();
    expect($domain->last_success_at)->not->toBeNull();
    expect($domain->last_seen_url)->toBeNull();
});

it('rejects admin-provided test urls that do not match the selected domain', function () {
    Http::preventStrayRequests();

    $admin = previewAdmin();
    $domain = SourcePreviewDomain::factory()->create([
        'domain' => 'printables.com',
        'label' => 'Printables',
        'policy' => SourcePreviewFetchPolicy::Allow,
    ]);

    actingAs($admin);

    post(route('admin.source-preview-domains.attempt-url', $domain), [
        'url' => 'https://thingiverse.com/thing:123456',
    ])->assertRedirect()
        ->assertSessionHas('status', 'Test URL must belong to printables.com.');

    $domain->refresh();

    expect($domain->last_attempt_status)->toBeNull();
    Http::assertNothingSent();
});
