<div>
    <x-ui.page-header title="Preferensi Notifikasi" subtitle="Atur apakah notifikasi dikirim ke email dan ringkasan harian">
        <x-slot:actions>
            <a href="{{ route('profile.edit') }}" class="app-btn app-btn-secondary">Kembali ke Profil</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if (! $globalEmailEnabled)
        <div class="app-card mb-4 border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-900/20">
            <p class="text-sm text-amber-800 dark:text-amber-200">Email notifikasi saat ini dimatikan secara global oleh admin (Settings → Notifications). Anda masih menerima in-app notification.</p>
        </div>
    @endif

    <x-ui.card>
        <div class="border-b border-app-border pb-3">
            <h2 class="app-card-title">Email per tipe</h2>
            <p class="mt-1 text-sm text-app-muted">Kontrol masing-masing jenis notifikasi. In-app tetap masuk meskipun email dimatikan.</p>
        </div>

        <div class="mt-4 divide-y divide-app-border">
            @foreach ($types as $type => $meta)
                <label class="flex items-center justify-between gap-3 py-3">
                    <div>
                        <p class="text-sm font-medium text-app-text">{{ $meta['label'] }} <code class="rounded bg-app-surface-2 px-1 py-0.5 font-mono text-xs text-app-muted">{{ $type }}</code></p>
                        <p class="text-xs text-app-muted">{{ $meta['group'] }} · default: {{ $meta['email_default'] ? 'on' : 'off' }}</p>
                    </div>
                    <input type="checkbox" wire:model.live="email.{{ $type }}" class="h-5 w-5 rounded border-app-border text-primary-600 focus:ring-primary-500">
                </label>
            @endforeach
        </div>

        <div class="mt-6 rounded-xl border border-app-border bg-app-surface-2/50 p-4">
            <label class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-app-text">Ringkasan harian (digest)</p>
                    <p class="text-xs text-app-muted">
                        @if (! $globalDigestEnabled)
                            Dimatikan global oleh admin.
                        @else
                            Kirim email ringkasan low/out &amp; batch kedaluwarsa setiap hari.
                        @endif
                    </p>
                </div>
                <input type="checkbox" wire:model.live="digest" class="h-5 w-5 rounded border-app-border text-primary-600 focus:ring-primary-500">
            </label>
        </div>

        <div class="mt-6 flex justify-end">
            <button type="button" wire:click="save" class="app-btn app-btn-primary">Simpan</button>
        </div>
    </x-ui.card>
</div>
