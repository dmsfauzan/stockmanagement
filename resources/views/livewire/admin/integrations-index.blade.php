<div>
    <x-ui.page-header title="Integrations" subtitle="Webhook keluar & ekspor akuntansi untuk ERP">
        <x-slot:actions>
            <a href="{{ route('admin.settings') }}" class="app-btn app-btn-secondary">Pengaturan</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-ui.card>
            <h2 class="app-card-title">Webhook Secret</h2>
            <p class="mt-1 text-sm text-app-muted">Dipakai untuk header <code class="rounded bg-app-surface-2 px-1">X-Signature</code> = HMAC-SHA256(body, secret).</p>
            <div class="mt-3">
                <label class="app-label">Secret baru</label>
                <input type="text" wire:model="webhookSecret" class="app-input mt-1" placeholder="{{ $secretSet ? '•••••••• (tersimpan)' : 'belum diatur' }}" autocomplete="off">
                @error('webhookSecret') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                <div class="mt-3 flex gap-2">
                    <button type="button" wire:click="saveSecret" class="app-btn app-btn-primary">Simpan Secret</button>
                    <button type="button" wire:click="sendTest" class="app-btn app-btn-secondary">Test Webhook</button>
                </div>
            </div>
            <p class="mt-3 text-xs text-app-muted">Aktifkan webhook & set URL di <a href="{{ route('admin.settings') }}" class="app-link">Settings → Integration</a>.</p>
        </x-ui.card>

        <x-ui.card>
            <h2 class="app-card-title">Event yang dikirim</h2>
            <p class="mt-1 text-sm text-app-muted">Pilih event yang memicu webhook.</p>
            <div class="mt-3 grid grid-cols-1 gap-1 sm:grid-cols-2">
                @foreach ($allEvents as $event)
                    <label class="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-app-surface-2">
                        <input type="checkbox" wire:model.live="events" value="{{ $event }}" class="rounded border-app-border text-primary-600 focus:ring-primary-500">
                        <span class="font-mono text-xs">{{ $event }}</span>
                    </label>
                @endforeach
            </div>
        </x-ui.card>
    </div>

    <x-ui.card padding="p-0" class="mt-4">
        <div class="flex items-center justify-between border-b border-app-border px-4 py-3">
            <h2 class="app-card-title">Webhook Deliveries</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Event</th>
                        <th>URL</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Attempts</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deliveries as $delivery)
                        <tr>
                            <td class="whitespace-nowrap text-app-muted">{{ $delivery->created_at?->format('d M Y H:i') }}</td>
                            <td class="whitespace-nowrap font-mono text-xs">{{ $delivery->event }}</td>
                            <td class="max-w-xs truncate text-app-muted">{{ $delivery->url }}</td>
                            <td class="text-center">
                                @if ($delivery->success)
                                    <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ $delivery->response_status ?? 'OK' }}</span>
                                @else
                                    <span class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ $delivery->response_status ?? 'FAIL' }}</span>
                                @endif
                            </td>
                            <td class="text-center text-app-muted">{{ $delivery->attempts }}</td>
                            <td class="text-right">
                                <button type="button" wire:click="retry({{ $delivery->id }})" class="app-btn app-btn-ghost app-btn-sm">Retry</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty-state title="Belum ada pengiriman" message="Webhook yang dikirim akan muncul di sini." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($deliveries->hasPages())
            <div class="border-t border-app-border px-4 py-3">{{ $deliveries->links() }}</div>
        @endif
    </x-ui-card>
</div>
