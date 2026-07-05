@component('mail::message')
# You've been added to {{ $orgName }}

You have been added to **{{ $orgName }}** as a **{{ $role }}**.

@component('mail::button', ['url' => $orgUrl])
View Organization
@endcomponent

Thanks,
{{ config('app.name') }}
@endcomponent
