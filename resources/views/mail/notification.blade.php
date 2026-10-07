@component('mail::message')
# {{ $title }}

{{ $message }}

@component('mail::button', ['url' => rtrim($appUrl, '/').'/notifications', 'color' => 'primary'])
Buka Notifikasi
@endcomponent

Terima kasih,<br>
{{ $appName }}
@endcomponent
