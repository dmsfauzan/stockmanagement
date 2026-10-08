@component('mail::message')
# {{ $title }}

Periode: **{{ $periodLabel }}** ({{ $fromLabel }} s/d {{ $toLabel }})

@if (! empty($summary))
@foreach ($summary as $label => $value)
- **{{ $label }}:** {{ $value }}
@endforeach
@endif

@if (count($rows) > 0)
@php
    $table = '| '.implode(' | ', $columns).' |'."\n";
    $table .= '| '.implode(' | ', array_fill(0, count($columns), '---')).' |';
    foreach ($rows as $row) {
        $table .= "\n".'| '.implode(' | ', array_map(fn ($cell) => (string) $cell, $row)).' |';
    }
@endphp

{{ $table }}

@if ($total > count($rows))
_Menampilkan {{ count($rows) }} dari {{ $total }} baris._
@endif
@else
Tidak ada data untuk periode ini.
@endif

@component('mail::button', ['url' => rtrim($appUrl, '/').'/dashboard', 'color' => 'primary'])
Buka Dashboard
@endcomponent

Terima kasih,<br>
{{ $appName }}
@endcomponent
