@props(['url'])

@php
    $configuredLogoPath = config('mail.branding.logo_path');
    $logoUrl = null;

    if (filter_var($configuredLogoPath, FILTER_VALIDATE_URL)) {
        $logoUrl = $configuredLogoPath;
    } elseif (filled($configuredLogoPath) && is_file(public_path($configuredLogoPath))) {
        $logoUrl = asset($configuredLogoPath);
    }
@endphp

<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($logoUrl)
<img src="{{ $logoUrl }}" class="logo" alt="{{ config('mail.branding.logo_alt', config('app.name')) }}">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
