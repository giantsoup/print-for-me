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
use Illuminate\Support\Facades\Storage;

class PrintRequestCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected PrintRequest $printRequest,
        protected object $notifiable,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: MailSubject::make('Your print request is complete'),
        );
    }

    public function content(): Content
    {
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
                'inlinePhotoPath' => $this->inlinePhotoPath(),
                'inlinePhotoAlt' => $this->inlinePhotoAlt(),
            ],
        );
    }

    private function inlinePhotoPath(): ?string
    {
        $photo = $this->inlinePhoto();

        if ($photo === null) {
            return null;
        }

        return Storage::disk($photo->disk)->path($photo->path);
    }

    private function inlinePhotoAlt(): string
    {
        $photo = $this->inlinePhoto();

        if ($photo === null) {
            return 'Completion preview';
        }

        return $photo->original_name ?: 'Completion preview';
    }

    private function inlinePhoto(): ?PrintRequestCompletionPhoto
    {
        $this->printRequest->loadMissing(['completionPhotos', 'files']);

        /** @var PrintRequestCompletionPhoto|null $photo */
        $photo = $this->printRequest->completionPhotos->first();

        if ($photo === null) {
            return null;
        }

        if (! Storage::disk($photo->disk)->exists($photo->path)) {
            return null;
        }

        return $photo;
    }
}
