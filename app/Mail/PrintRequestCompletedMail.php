<?php

namespace App\Mail;

use App\Models\PrintRequest;
use App\Models\PrintRequestCompletionPhoto;
use App\Support\MailSubject;
use App\Support\PrintRequestMailData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class PrintRequestCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var array{data: string, filename: string, mime_type: string, alt: string}|null
     */
    private ?array $inlinePhotoPayload = null;

    private bool $hasResolvedInlinePhotoPayload = false;

    public function __construct(
        protected PrintRequest $printRequest,
        protected object $notifiable,
    ) {
        $this->withSymfonyMessage([$this, 'logSymfonyMessageDebug']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: MailSubject::make('Your print request is complete'),
        );
    }

    public function content(): Content
    {
        $inlinePhotoPayload = $this->inlinePhotoPayload();

        $this->logCompletionEmailDebug('completion_email.preparing', [
            'recipient_email' => $this->recipientEmail(),
            'recipient_type' => $this->notifiable::class,
            'has_inline_photo_payload' => $inlinePhotoPayload !== null,
            'inline_photo_filename' => $inlinePhotoPayload['filename'] ?? null,
            'inline_photo_mime_type' => $inlinePhotoPayload['mime_type'] ?? null,
            'inline_photo_size_bytes' => isset($inlinePhotoPayload['data']) ? strlen($inlinePhotoPayload['data']) : null,
        ]);

        return new Content(
            markdown: 'mail.notifications.print-request-completed',
            with: [
                'headline' => 'Your print request is complete',
                'greeting' => PrintRequestMailData::greetingFor($this->notifiable),
                'intro' => 'Your print request has been marked complete. Open the request to review the latest details and any completion photos that were added.',
                'details' => PrintRequestMailData::requesterDetails($this->printRequest),
                'instructions' => PrintRequestMailData::instructionsExcerpt($this->printRequest->instructions),
                'nextSteps' => 'If you need to reference the original files or notes, they remain available from the request page.',
                'actionLabel' => 'Open completed request',
                'actionUrl' => PrintRequestMailData::requestUrl($this->printRequest),
                'closing' => 'We appreciate the opportunity to help with your project.',
                'inlinePhotoData' => $inlinePhotoPayload['data'] ?? null,
                'inlinePhotoFilename' => $inlinePhotoPayload['filename'] ?? null,
                'inlinePhotoMimeType' => $inlinePhotoPayload['mime_type'] ?? null,
                'inlinePhotoAlt' => $inlinePhotoPayload['alt'] ?? 'Completion preview',
            ],
        );
    }

    private function inlinePhotoData(): ?string
    {
        return $this->inlinePhotoPayload()['data'] ?? null;
    }

    private function inlinePhotoFilename(): ?string
    {
        return $this->inlinePhotoPayload()['filename'] ?? null;
    }

    private function inlinePhotoMimeType(): ?string
    {
        return $this->inlinePhotoPayload()['mime_type'] ?? null;
    }

    private function inlinePhotoAlt(): string
    {
        return $this->inlinePhotoPayload()['alt'] ?? 'Completion preview';
    }

    /**
     * @return array{data: string, filename: string, mime_type: string, alt: string}|null
     */
    private function inlinePhotoPayload(): ?array
    {
        if ($this->hasResolvedInlinePhotoPayload) {
            return $this->inlinePhotoPayload;
        }

        $this->hasResolvedInlinePhotoPayload = true;

        $this->printRequest->loadMissing(['completionPhotos', 'files']);

        $photos = $this->printRequest->completionPhotos->values();

        if ($photos->isEmpty()) {
            $this->logCompletionEmailDebug('completion_email.inline_photo_unavailable', [
                'reason' => 'missing_completion_photo_record',
            ]);

            return null;
        }

        foreach ($photos as $index => $photo) {
            $payload = $this->inlinePhotoPayloadForPhoto($photo, $index + 1, $photos->count());

            if ($payload !== null) {
                return $this->inlinePhotoPayload = $payload;
            }
        }

        $this->logCompletionEmailDebug('completion_email.inline_photo_unavailable', [
            'reason' => 'no_usable_completion_photo_records',
            'attempted_photo_count' => $photos->count(),
        ]);

        return null;
    }

    /**
     * @return array{data: string, filename: string, mime_type: string, alt: string}|null
     */
    private function inlinePhotoPayloadForPhoto(PrintRequestCompletionPhoto $photo, int $photoPosition, int $photoCount): ?array
    {
        $diskExists = Storage::disk($photo->disk)->exists($photo->path);

        if (! $diskExists) {
            $this->logCompletionEmailDebug('completion_email.inline_photo_unavailable', [
                'reason' => 'missing_storage_file',
                'photo_position' => $photoPosition,
                'photo_count' => $photoCount,
                ...$this->completionPhotoLogContext($photo, $diskExists),
            ]);

            return null;
        }

        $contents = Storage::disk($photo->disk)->get($photo->path);

        if (! is_string($contents) || $contents === '') {
            $this->logCompletionEmailDebug('completion_email.inline_photo_unavailable', [
                'reason' => 'empty_storage_contents',
                'photo_position' => $photoPosition,
                'photo_count' => $photoCount,
                ...$this->completionPhotoLogContext($photo, $diskExists),
            ]);

            return null;
        }

        $filename = $photo->original_name ?: basename($photo->path);
        $mimeType = $photo->mime_type ?: Storage::disk($photo->disk)->mimeType($photo->path) ?: 'application/octet-stream';

        if ($mimeType === 'image/webp') {
            $jpegContents = $this->convertInlinePhotoToJpeg($contents);

            if ($jpegContents !== null) {
                $payload = [
                    'data' => $jpegContents,
                    'filename' => $this->jpegFilename($filename),
                    'mime_type' => 'image/jpeg',
                    'alt' => $filename,
                ];

                $this->logCompletionEmailDebug('completion_email.inline_photo_ready', [
                    'photo_position' => $photoPosition,
                    'photo_count' => $photoCount,
                    'photo_stored_mime_type' => $mimeType,
                    'embedded_filename' => $payload['filename'],
                    'embedded_mime_type' => $payload['mime_type'],
                    'embedded_size_bytes' => strlen($payload['data']),
                    'converted_from_webp' => true,
                    ...$this->completionPhotoLogContext($photo, $diskExists),
                ]);

                return $payload;
            }
        }

        $payload = [
            'data' => $contents,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'alt' => $filename,
        ];

        $this->logCompletionEmailDebug('completion_email.inline_photo_ready', [
            'photo_position' => $photoPosition,
            'photo_count' => $photoCount,
            'photo_stored_mime_type' => $mimeType,
            'embedded_filename' => $payload['filename'],
            'embedded_mime_type' => $payload['mime_type'],
            'embedded_size_bytes' => strlen($payload['data']),
            'converted_from_webp' => false,
            ...$this->completionPhotoLogContext($photo, $diskExists),
        ]);

        return $payload;
    }

    private function jpegFilename(string $filename): string
    {
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        if ($basename === '') {
            return 'completion-preview.jpg';
        }

        return $basename.'.jpg';
    }

    private function convertInlinePhotoToJpeg(string $contents): ?string
    {
        if (class_exists(\Imagick::class)) {
            try {
                $image = new \Imagick;
                $image->readImageBlob($contents);
                $image->setIteratorIndex(0);
                $image = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $image->setImageBackgroundColor('white');
                $image->setImageFormat('jpeg');
                $image->setImageCompression(\Imagick::COMPRESSION_JPEG);
                $image->setImageCompressionQuality(82);
                $image->stripImage();

                $jpegContents = $image->getImagesBlob();
                $image->destroy();

                if ($jpegContents !== '') {
                    return $jpegContents;
                }
            } catch (RuntimeException|\ImagickException) {
            }
        }

        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = imagecreatefromstring($contents);

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            imagedestroy($source);

            return null;
        }

        $background = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $background);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

        ob_start();
        $encoded = imagejpeg($canvas, null, 82);
        $jpegContents = (string) ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        if (! $encoded || $jpegContents === '') {
            return null;
        }

        return $jpegContents;
    }

    public function logSymfonyMessageDebug(Email $message): void
    {
        $attachments = collect($message->getAttachments())
            ->map(function (DataPart $attachment): array {
                return [
                    'content_id' => $attachment->getContentId(),
                    'content_type' => $attachment->getContentType(),
                    'filename' => $attachment->getFilename(),
                ];
            })
            ->values()
            ->all();

        $htmlBody = (string) ($message->getHtmlBody() ?? '');

        $this->logCompletionEmailDebug('completion_email.symfony_message_built', [
            'recipient_email' => $this->recipientEmail(),
            'attachment_count' => count($attachments),
            'attachments' => $attachments,
            'html_contains_cid' => str_contains($htmlBody, 'cid:'),
            'html_cid_matches' => preg_match_all('/cid:([^"\']+)/', $htmlBody, $matches) ? array_values(array_unique($matches[1])) : [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logCompletionEmailDebug(string $message, array $context = []): void
    {
        Log::info($message, array_merge([
            'print_request_id' => $this->printRequest->getKey(),
            'print_request_status' => (string) $this->printRequest->status,
            'completion_photo_count' => $this->printRequest->completionPhotos()->count(),
            'queue_connection' => config('queue.default'),
        ], $context));
    }

    private function recipientEmail(): ?string
    {
        $email = trim((string) data_get($this->notifiable, 'email', ''));

        return $email !== '' ? $email : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function completionPhotoLogContext(PrintRequestCompletionPhoto $photo, ?bool $diskExists = null): array
    {
        $context = [
            'photo_id' => $photo->id,
            'photo_disk' => $photo->disk,
            'photo_path' => $photo->path,
            'photo_original_name' => $photo->original_name,
        ];

        if ($diskExists !== null) {
            $context['photo_disk_exists_result'] = $diskExists;
        }

        $storageRoot = config("filesystems.disks.{$photo->disk}.root");

        if (is_string($storageRoot) && $storageRoot !== '') {
            $context['photo_disk_root'] = $storageRoot;
        }

        $resolvedStoragePath = $this->resolvedStoragePath($photo);

        if ($resolvedStoragePath !== null) {
            clearstatcache(true, $resolvedStoragePath);

            $resolvedParentDirectory = dirname($resolvedStoragePath);
            $resolvedStorageExists = is_file($resolvedStoragePath);
            $resolvedStorageIsReadable = is_readable($resolvedStoragePath);
            $resolvedParentDirectoryExists = is_dir($resolvedParentDirectory);
            $resolvedParentDirectoryIsReadable = is_readable($resolvedParentDirectory);
            $resolvedParentDirectoryIsExecutable = is_executable($resolvedParentDirectory);

            $context['resolved_storage_path'] = $resolvedStoragePath;
            $context['resolved_storage_exists'] = $resolvedStorageExists;
            $context['resolved_storage_is_readable'] = $resolvedStorageIsReadable;
            $context['resolved_storage_parent_directory'] = $resolvedParentDirectory;
            $context['resolved_storage_parent_directory_exists'] = $resolvedParentDirectoryExists;
            $context['resolved_storage_parent_directory_is_readable'] = $resolvedParentDirectoryIsReadable;
            $context['resolved_storage_parent_directory_is_executable'] = $resolvedParentDirectoryIsExecutable;
            $context['suspected_read_permission_issue'] = $this->suspectedReadPermissionIssue(
                $resolvedStorageExists,
                $resolvedStorageIsReadable,
                $resolvedParentDirectoryExists,
                $resolvedParentDirectoryIsReadable,
                $resolvedParentDirectoryIsExecutable,
            );

            $resolvedStoragePermissions = $this->permissionsOctal($resolvedStoragePath);

            if ($resolvedStoragePermissions !== null) {
                $context['resolved_storage_permissions'] = $resolvedStoragePermissions;
            }

            $resolvedParentPermissions = $this->permissionsOctal($resolvedParentDirectory);

            if ($resolvedParentPermissions !== null) {
                $context['resolved_storage_parent_directory_permissions'] = $resolvedParentPermissions;
            }
        }

        return array_merge($context, $this->workerIdentityLogContext());
    }

    private function resolvedStoragePath(PrintRequestCompletionPhoto $photo): ?string
    {
        try {
            return Storage::disk($photo->disk)->path($photo->path);
        } catch (RuntimeException) {
            return null;
        }
    }

    private function suspectedReadPermissionIssue(
        bool $resolvedStorageExists,
        bool $resolvedStorageIsReadable,
        bool $resolvedParentDirectoryExists,
        bool $resolvedParentDirectoryIsReadable,
        bool $resolvedParentDirectoryIsExecutable,
    ): bool {
        if ($resolvedStorageExists && ! $resolvedStorageIsReadable) {
            return true;
        }

        if ($resolvedParentDirectoryExists && (! $resolvedParentDirectoryIsReadable || ! $resolvedParentDirectoryIsExecutable)) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function workerIdentityLogContext(): array
    {
        if (! function_exists('posix_geteuid') || ! function_exists('posix_getegid')) {
            return [];
        }

        $effectiveUserId = posix_geteuid();
        $effectiveGroupId = posix_getegid();
        $effectiveUser = function_exists('posix_getpwuid') ? posix_getpwuid($effectiveUserId) : false;
        $effectiveGroup = function_exists('posix_getgrgid') ? posix_getgrgid($effectiveGroupId) : false;

        return [
            'worker_effective_user_id' => $effectiveUserId,
            'worker_effective_group_id' => $effectiveGroupId,
            'worker_effective_user_name' => is_array($effectiveUser) ? ($effectiveUser['name'] ?? null) : null,
            'worker_effective_group_name' => is_array($effectiveGroup) ? ($effectiveGroup['name'] ?? null) : null,
        ];
    }

    private function permissionsOctal(string $path): ?string
    {
        $permissions = @fileperms($path);

        if ($permissions === false) {
            return null;
        }

        return substr(sprintf('%o', $permissions), -4);
    }
}
