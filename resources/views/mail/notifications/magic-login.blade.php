<x-mail::message>
# Sign in to {{ config('app.name') }}

{{ $greeting }}

Use the secure button below to finish signing in. This one-time link expires in 10 minutes and can only be used once.

## Security details

<table class="details-list" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr class="details-list-row">
<td class="details-list-label">Link validity</td>
<td class="details-list-value">10 minutes</td>
</tr>
<tr class="details-list-row">
<td class="details-list-label">Use limit</td>
<td class="details-list-value">One sign-in attempt for this email address</td>
</tr>
</table>

<x-mail::button :url="$loginUrl">
Sign in securely
</x-mail::button>

If you did not request this sign-in link, you can ignore this email and no changes will be made to your account.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
