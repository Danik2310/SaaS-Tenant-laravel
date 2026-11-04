@component('mail::message')
# Welcome to {{ $tenantName }}

@if ($name !== '')
Hi {{ $name }},
@else
Hi,
@endif

Thanks for creating {{ $tenantName }}. Confirm this email address to activate
**{{ $domain }}**.

@component('mail::button', ['url' => $url])
Verify my email
@endcomponent

This link expires in {{ $expiresInMinutes }} minutes. If you did not create this
workspace, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
