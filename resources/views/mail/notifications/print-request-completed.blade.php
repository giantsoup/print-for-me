<x-mail::message>
# {{ $headline }}

@if (filled($inlinePhotoData ?? null))
## Completion preview

<div class="inline-photo">
    <img src="{{ $message->embedData($inlinePhotoData, $inlinePhotoFilename ?? 'completion-preview.jpg', $inlinePhotoMimeType ?? 'image/jpeg') }}" alt="{{ $inlinePhotoAlt ?? 'Completion preview' }}" class="inline-photo-image">
</div>

<div class="inline-photo-copy-gap" aria-hidden="true">&nbsp;</div>
@endif

{{ $greeting }}

{{ $intro }}

## Request overview

@include('mail.notifications.partials.details-panel', ['details' => $details])

@if (filled($instructions ?? null))
## Request notes

<p class="section-copy">
{{ $instructions }}
</p>
@endif

@if (filled($nextSteps ?? null))
## What happens next

<p class="section-copy">
{{ $nextSteps }}
</p>
@endif

@if (filled($actionLabel ?? null) && filled($actionUrl ?? null))
<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>
@endif

{{ $closing }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
