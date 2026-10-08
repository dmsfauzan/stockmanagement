<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <meta name="color-scheme" content="light dark">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
    <title>Offline — {{ config('app.name', 'Warehouse Stock Management') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #f1f5f9; }
            .card { background: #1e293b !important; border-color: #334155 !important; }
            .muted { color: #94a3b8 !important; }
            .ghost { background: #0f172a !important; border-color: #334155 !important; color: #e2e8f0 !important; }
        }
        .card {
            width: 100%; max-width: 30rem; text-align: center;
            background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem;
            padding: 2rem; box-shadow: 0 1px 3px rgba(15,23,42,.08);
        }
        .badge { width: 3rem; height: 3rem; border-radius: .9rem; background: #4f46e5; color: #fff;
            display: inline-flex; align-items: center; justify-content: center; }
        h1 { font-size: 1.125rem; margin: 1rem 0 0; }
        p { font-size: .875rem; line-height: 1.6; color: #64748b; margin: .5rem 0 0; }
        .actions { display: flex; gap: .5rem; justify-content: center; margin-top: 1.5rem; flex-wrap: wrap; }
        a, button {
            font: inherit; font-size: .875rem; font-weight: 500; cursor: pointer;
            padding: .5rem 1rem; border-radius: .625rem; text-decoration: none; border: 1px solid transparent;
        }
        .primary { background: #4f46e5; color: #fff; }
        .ghost { background: #fff; border-color: #e2e8f0; color: #334155; }
        .dark { background: #0f172a; color: #fff; }
        .foot { margin-top: 1.5rem; font-size: .75rem; color: #94a3b8; }
        svg { width: 1.5rem; height: 1.5rem; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge" aria-hidden="true">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 3h.01"/><path stroke-linecap="round" stroke-linejoin="round" d="M10.39 3.08A9 9 0 1021.92 12 9 9 0 0010.39 3.08z"/></svg>
        </span>
        <h1>Anda sedang offline</h1>
        <p class="muted">Tidak ada koneksi internet. Perubahan stok tidak dikirim saat offline — buka kembali halaman ini setelah tersambung untuk sinkronisasi.</p>
        <div class="actions">
            <button type="button" class="ghost" onclick="history.back()">{{ __('Kembali') }}</button>
            <button type="button" class="dark" onclick="location.reload()">Coba lagi</button>
            <a href="{{ route('dashboard') }}" class="primary">Ke Dashboard</a>
        </div>
        <p class="foot">PWA {{ config('app.name', 'Warehouse Stock Management') }}</p>
    </div>
</body>
</html>
