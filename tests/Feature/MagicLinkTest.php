<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\MagicLoginLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MagicLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_whitelisted_email_cannot_request_magic_link(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        $response = $this->post(route('magic.send'), [
            'email' => 'nope@example.com',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('magic_login_tokens', 0);
    }

    public function test_whitelisted_user_receives_magic_link_notification_and_token_created(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        Notification::fake();

        $user = User::factory()->create([
            'email' => 'person@example.com',
        ]);
        // whitelist the user
        $user->whitelisted_at = now();
        $user->save();

        $response = $this->post(route('magic.send'), [
            'email' => 'person@example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('magic_login_tokens', [
            'email' => 'person@example.com',
        ]);
        Notification::assertSentTo($user, MagicLoginLinkNotification::class);
    }
}
