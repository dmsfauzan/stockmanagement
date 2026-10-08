<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        @page { margin: 16px 20px; }
        body { font-size: 10px; color: #1e293b; margin: 0; }
        h1 { font-size: 16px; margin: 0 0 2px; color: #0f172a; }
        .meta { font-size: 9px; color: #64748b; margin: 0 0 10px; }
        .filters { font-size: 9px; color: #475569; margin: 0 0 12px; padding: 6px 8px; background: #f1f5f9; border: 1px solid #e2e8f0; }
        .filters strong { color: #334155; }
        table { width: 100%; border-collapse: collapse; }
        thead th { background: #1e293b; color: #ffffff; text-align: left; padding: 5px 6px; font-size: 9px; border: 1px solid #334155; }
        tbody td { padding: 4px 6px; border: 1px solid #e2e8f0; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        .right { text-align: right; }
        .totals { margin-top: 10px; font-size: 10px; font-weight: bold; color: #0f172a; }
        .empty { padding: 18px; text-align: center; color: #94a3b8; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="meta">{{ __('Dibuat:') }} {{ $generatedAt }}</p>
    @if (! empty($filters))
        <div class="filters">
            <strong>{{ __('Filter:') }}</strong>
            @foreach ($filters as $label => $value)
                {{ $label }}: {{ $value }}@if (! $loop->last) &nbsp;|&nbsp; @endif
            @endforeach
        </div>
    @endif
