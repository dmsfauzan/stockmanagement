@component('mail::message')
# Ringkasan Harian Gudang — {{ $date }}

Berikut rincian peringatan terbaru untuk gudang Anda.

@forelse ($items as $item)
@component('mail::panel')
**{{ $item['title'] }}** · `{{ $item['type'] }}`<br>
{{ $item['message'] }}
@endcomponent
@empty
Tidak ada peringatan baru hari ini.
@endforelse

@component('mail::button', ['url' => rtrim($appUrl, '/').'/dashboard', 'color' => 'primary'])
Buka Dashboard
@endcomponent

Terima kasih,<br>
{{ $appName }}
@endcomponent
