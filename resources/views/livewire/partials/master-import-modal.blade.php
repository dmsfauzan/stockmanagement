@if ($showImportModal)
    <div class="fixed inset-0 z-[55] flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showImportModal', false)"></div>
        <div class="relative w-full max-w-xl rounded-xl border border-app-border bg-app-surface p-6 shadow-popover">
            <h3 class="text-base font-semibold text-app-text">Import {{ $importLabel }}</h3>
            <p class="mt-1 text-sm text-app-muted">Unggah file Excel/CSV dengan kolom: <code class="rounded bg-app-surface-2 px-1 text-xs">{{ implode(', ', $importHeadings) }}</code>.</p>

            <div class="mt-4">
                <button type="button" wire:click="downloadImportTemplate" class="app-btn app-btn-ghost gap-1.5 text-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Unduh Template CSV
                </button>
            </div>

            <div class="mt-4 space-y-3">
                <label class="app-label">File</label>
                <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv" class="app-input text-sm">
                @error('importFile') <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                <div wire:loading wire:target="importFile,import" class="text-xs text-app-muted">Memproses…</div>

                <p class="text-xs text-app-muted">File diproses di latar belakang. Anda akan menerima notifikasi saat selesai (periksa bell notifikasi).</p>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" wire:click="$set('showImportModal', false)" class="app-btn app-btn-secondary">Tutup</button>
                <button type="button" wire:click="import" wire:loading.attr="disabled" class="app-btn app-btn-primary">Import</button>
            </div>
        </div>
    </div>
@endif
