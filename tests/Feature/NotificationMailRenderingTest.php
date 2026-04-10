<?php

use App\Enums\PrintRequestStatus;
use App\Mail\PrintRequestCompletedMail;
use App\Models\PrintRequest;
use App\Models\User;
use App\Notifications\MagicLoginLinkNotification;
use App\Notifications\NewPrintRequestNotification;
use App\Notifications\PendingRequestCanceledByUserNotification;
use App\Notifications\PrintRequestAcceptedNotification;
use App\Notifications\PrintRequestCompletedNotification;
use App\Notifications\PrintRequestRevertedToPendingNotification;
use App\Notifications\PrintRequestSoftDeletedPurgeWarningNotification;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

dataset('print request mail notifications', [
    'accepted' => [
        PrintRequestAcceptedNotification::class,
        'Your print request has been accepted',
        'View request details',
        ['Accepted', 'example.com/projects/clean-enclosure', 'Please prioritize a clean finish'],
    ],
    'reverted' => [
        PrintRequestRevertedToPendingNotification::class,
        'Your print request needs another review',
        'Review request',
        ['Pending review', 'example.com/projects/clean-enclosure', 'Please prioritize a clean finish'],
    ],
    'new request' => [
        NewPrintRequestNotification::class,
        'A new print request is ready for review',
        'Review request',
        ['Taylor Example (taylor@example.com)', 'example.com/projects/clean-enclosure', 'Please prioritize a clean finish'],
    ],
    'canceled' => [
        PendingRequestCanceledByUserNotification::class,
        'A pending print request was canceled',
        'Review active queue',
        ['Taylor Example (taylor@example.com)', 'example.com/projects/clean-enclosure', 'Please prioritize a clean finish'],
    ],
    'purge warning' => [
        PrintRequestSoftDeletedPurgeWarningNotification::class,
        'Your deleted print request will be removed in 7 days',
        'Start a new request',
        ['Permanent removal date', 'example.com/projects/clean-enclosure', 'Please prioritize a clean finish'],
    ],
]);

it('renders print request emails with professional summaries instead of raw record labels', function (string $notificationClass, string $headline, string $actionLabel, array $expectedFragments) {
    $requester = User::factory()->create([
        'name' => 'Taylor Example',
        'email' => 'taylor@example.com',
    ]);

    $printRequest = PrintRequest::create([
        'user_id' => $requester->id,
        'status' => PrintRequestStatus::PENDING,
        'source_url' => 'https://example.com/projects/clean-enclosure',
        'instructions' => 'Please prioritize a clean finish and keep support scarring away from the front face.',
    ]);

    $printRequest->files()->create([
        'disk' => 'local',
        'path' => 'prints/2026/03/enclosure.stl',
        'original_name' => 'clean-enclosure.stl',
        'mime_type' => 'application/sla',
        'size_bytes' => 1024,
        'sha256' => hash('sha256', 'clean-enclosure'),
    ]);

    match ($notificationClass) {
        PrintRequestAcceptedNotification::class => $printRequest->forceFill([
            'status' => PrintRequestStatus::ACCEPTED,
            'accepted_at' => now(),
        ])->save(),
        PrintRequestCompletedNotification::class => $printRequest->forceFill([
            'status' => PrintRequestStatus::COMPLETE,
            'completed_at' => now(),
        ])->save(),
        PrintRequestRevertedToPendingNotification::class => $printRequest->forceFill([
            'status' => PrintRequestStatus::PENDING,
            'reverted_at' => now(),
        ])->save(),
        PendingRequestCanceledByUserNotification::class,
        PrintRequestSoftDeletedPurgeWarningNotification::class => tap($printRequest, function (PrintRequest $request): void {
            $request->delete();
            $request->forceFill(['deleted_at' => now()->subDays(83)])->save();
        }),
        default => null,
    };

    $notifiable = in_array($notificationClass, [
        NewPrintRequestNotification::class,
        PendingRequestCanceledByUserNotification::class,
    ], true)
        ? new AnonymousNotifiable
        : $requester;

    $mail = (new $notificationClass($printRequest))->toMail($notifiable);
    $html = $mail instanceof Mailable
        ? $mail->render()
        : $mail->render()->toHtml();

    expect($html)
        ->toContain($headline)
        ->toContain('Request overview')
        ->toContain($actionLabel)
        ->not->toContain('Request ID:')
        ->not->toContain('User ID:');

    foreach ($expectedFragments as $expectedFragment) {
        expect($html)->toContain($expectedFragment);
    }
})->with('print request mail notifications');

it('renders the completed request email with one inline completion photo when available', function () {
    Storage::fake('local');

    $requester = User::factory()->create([
        'name' => 'Taylor Example',
        'email' => 'taylor@example.com',
    ]);

    $printRequest = PrintRequest::create([
        'user_id' => $requester->id,
        'status' => PrintRequestStatus::COMPLETE,
        'source_url' => 'https://example.com/projects/finished-enclosure',
        'instructions' => 'Final finish looks great. Please include the completion preview in the email.',
        'completed_at' => now(),
    ]);

    $printRequest->files()->create([
        'disk' => 'local',
        'path' => 'prints/2026/03/finished-enclosure.stl',
        'original_name' => 'finished-enclosure.stl',
        'mime_type' => 'application/sla',
        'size_bytes' => 1024,
        'sha256' => hash('sha256', 'finished-enclosure'),
    ]);

    $jpegData = realisticImageData(1280, 960);

    Storage::disk('local')->put('prints/completions/2026/03/preview.jpg', $jpegData);

    $printRequest->completionPhotos()->create([
        'disk' => 'local',
        'path' => 'prints/completions/2026/03/preview.jpg',
        'original_name' => 'preview.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => strlen($jpegData),
        'width' => 1280,
        'height' => 960,
        'sort_order' => 1,
        'sha256' => hash('sha256', $jpegData),
    ]);

    $mail = new PrintRequestCompletedMail($printRequest, $requester);
    $html = $mail->render();

    $mail->assertHasSubject('[Print for Me] Your print request is complete');
    $mail->assertSeeInHtml('Completion preview');
    $mail->assertSeeInHtml('data:image/jpeg;base64');
    $mail->assertSeeInHtml('inline-photo-copy-gap');
    $mail->assertSeeInHtml('preview.jpg');
    $mail->assertSeeInText('Your print request is complete');

    expect(mb_strpos($html, 'Completion preview'))->toBeLessThan(mb_strpos($html, 'Request overview'));
});

it('falls back to the next completion photo when the first stored photo is missing', function () {
    Storage::fake('local');
    Log::spy();

    config([
        'mail.default' => 'array',
    ]);

    app()->forgetInstance('mail.manager');
    app()->forgetInstance('mailer');

    $requester = User::factory()->create([
        'name' => 'Taylor Example',
        'email' => 'taylor@example.com',
    ]);

    $printRequest = PrintRequest::create([
        'user_id' => $requester->id,
        'status' => PrintRequestStatus::COMPLETE,
        'source_url' => 'https://example.com/projects/fallback-preview',
        'instructions' => 'Use the next available completion preview if the first file is unavailable.',
        'completed_at' => now(),
    ]);

    $printRequest->completionPhotos()->create([
        'disk' => 'local',
        'path' => 'prints/completions/2026/04/missing-preview.jpg',
        'original_name' => 'missing-preview.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 1024,
        'width' => 1280,
        'height' => 960,
        'sort_order' => 1,
        'sha256' => hash('sha256', 'missing-preview'),
    ]);

    $jpegData = realisticImageData(1280, 960);

    Storage::disk('local')->put('prints/completions/2026/04/fallback-preview.jpg', $jpegData);

    $printRequest->completionPhotos()->create([
        'disk' => 'local',
        'path' => 'prints/completions/2026/04/fallback-preview.jpg',
        'original_name' => 'fallback-preview.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => strlen($jpegData),
        'width' => 1280,
        'height' => 960,
        'sort_order' => 2,
        'sha256' => hash('sha256', $jpegData),
    ]);

    $mailer = app('mail.manager')->mailer('array');
    $transport = $mailer->getSymfonyTransport();

    if (method_exists($transport, 'flush')) {
        $transport->flush();
    }

    $mailer->sendNow(
        (new PrintRequestCompletedMail($printRequest, $requester))->to($requester->email)
    );

    $message = $transport->messages()->last()->getOriginalMessage();
    $html = (string) $message->getHtmlBody();

    expect($html)
        ->toContain('Completion preview')
        ->toContain('src="cid:');

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
        return $message === 'completion_email.inline_photo_unavailable'
            && $context['reason'] === 'missing_storage_file'
            && $context['photo_original_name'] === 'missing-preview.jpg'
            && $context['photo_position'] === 1
            && $context['photo_count'] === 2
            && $context['photo_disk_exists_result'] === false
            && $context['resolved_storage_parent_directory_exists'] === true
            && array_key_exists('suspected_read_permission_issue', $context)
            && array_key_exists('worker_effective_user_id', $context);
    })->once();

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
        return $message === 'completion_email.inline_photo_ready'
            && $context['photo_original_name'] === 'fallback-preview.jpg'
            && $context['photo_position'] === 2
            && $context['photo_count'] === 2
            && $context['embedded_mime_type'] === 'image/jpeg';
    })->once();
});

it('logs inline photo resolution and symfony message details', function () {
    Storage::fake('local');
    Log::spy();

    config([
        'mail.default' => 'array',
    ]);

    app()->forgetInstance('mail.manager');
    app()->forgetInstance('mailer');

    $requester = User::factory()->create([
        'name' => 'Taylor Example',
        'email' => 'taylor@example.com',
    ]);

    $printRequest = PrintRequest::create([
        'user_id' => $requester->id,
        'status' => PrintRequestStatus::COMPLETE,
        'source_url' => 'https://example.com/projects/logged-preview',
        'instructions' => 'Capture embedded image diagnostics.',
        'completed_at' => now(),
    ]);

    $jpegData = realisticImageData(1280, 960);

    Storage::disk('local')->put('prints/completions/2026/03/preview.jpg', $jpegData);

    $printRequest->completionPhotos()->create([
        'disk' => 'local',
        'path' => 'prints/completions/2026/03/preview.jpg',
        'original_name' => 'preview.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => strlen($jpegData),
        'width' => 1280,
        'height' => 960,
        'sort_order' => 1,
        'sha256' => hash('sha256', $jpegData),
    ]);

    $mailer = app('mail.manager')->mailer('array');
    $transport = $mailer->getSymfonyTransport();

    if (method_exists($transport, 'flush')) {
        $transport->flush();
    }

    $mailer->sendNow(
        (new PrintRequestCompletedMail($printRequest, $requester))->to($requester->email)
    );

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
        return $message === 'completion_email.inline_photo_ready'
            && $context['print_request_id'] > 0
            && $context['photo_original_name'] === 'preview.jpg'
            && $context['embedded_mime_type'] === 'image/jpeg';
    })->once();

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
        return $message === 'completion_email.symfony_message_built'
            && $context['recipient_email'] === 'taylor@example.com'
            && $context['attachment_count'] === 1
            && $context['html_contains_cid'] === true
            && count($context['html_cid_matches']) === 1
            && $context['attachments'][0]['content_type'] === 'image/jpeg';
    })->once();
});

it('renders the completed request email with a jpeg inline preview when the stored completion photo is webp', function () {
    if (! function_exists('imagewebp')) {
        $this->markTestSkipped('WebP generation is not available in this environment.');
    }

    Storage::fake('local');

    $requester = User::factory()->create([
        'name' => 'Taylor Example',
        'email' => 'taylor@example.com',
    ]);

    $printRequest = PrintRequest::create([
        'user_id' => $requester->id,
        'status' => PrintRequestStatus::COMPLETE,
        'source_url' => 'https://example.com/projects/webp-preview',
        'instructions' => 'Embed the completion preview in a client-safe format.',
        'completed_at' => now(),
    ]);

    $webpData = realisticImageData(1280, 960, 'webp');

    Storage::disk('local')->put('prints/completions/2026/03/preview.webp', $webpData);

    $printRequest->completionPhotos()->create([
        'disk' => 'local',
        'path' => 'prints/completions/2026/03/preview.webp',
        'original_name' => 'preview.webp',
        'mime_type' => 'image/webp',
        'size_bytes' => strlen($webpData),
        'width' => 1280,
        'height' => 960,
        'sort_order' => 1,
        'sha256' => hash('sha256', $webpData),
    ]);

    $mail = new PrintRequestCompletedMail($printRequest, $requester);

    $mail->assertSeeInHtml('Completion preview');
    $mail->assertSeeInHtml('data:image/jpeg;base64');
    $mail->assertDontSeeInHtml('data:image/webp;base64');
});

it('does not render a completion preview section when no completion photo exists', function () {
    $requester = User::factory()->create([
        'name' => 'Taylor Example',
        'email' => 'taylor@example.com',
    ]);

    $printRequest = PrintRequest::create([
        'user_id' => $requester->id,
        'status' => PrintRequestStatus::COMPLETE,
        'source_url' => 'https://example.com/projects/no-photo',
        'instructions' => 'This request completed without a photo.',
        'completed_at' => now(),
    ]);

    $mail = new PrintRequestCompletedMail($printRequest, $requester);

    $mail->assertDontSeeInHtml('Completion preview');
    $mail->assertDontSeeInHtml('data:image/');
    $mail->assertDontSeeInHtml('inline-photo-copy-gap');
});

it('queues completed request notifications after the surrounding transaction commits', function () {
    $requester = User::factory()->create([
        'name' => 'Taylor Example',
        'email' => 'taylor@example.com',
    ]);

    $printRequest = PrintRequest::create([
        'user_id' => $requester->id,
        'status' => PrintRequestStatus::COMPLETE,
        'source_url' => 'https://example.com/projects/after-commit-preview',
        'completed_at' => now(),
    ]);

    $notification = new PrintRequestCompletedNotification($printRequest);

    expect($notification->afterCommit)->toBeTrue();
});

it('renders the magic login email with security guidance and a clear call to action', function () {
    $user = User::factory()->create([
        'name' => 'Taylor Example',
    ]);

    $html = (new MagicLoginLinkNotification('https://print-for-me.test/magic-login?token=abc123'))
        ->toMail($user)
        ->render()
        ->toHtml();

    expect($html)
        ->toContain('Sign in to')
        ->toContain('Sign in securely')
        ->toContain('Link validity')
        ->toContain('One sign-in attempt for this email address')
        ->not->toContain('Log in now');
});
