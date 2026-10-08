<div>
    <x-ui.page-header title="Scan" subtitle="Scan barcode atau masukkan SKU / kode lokasi" />

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-ui.card>
                <form wire:submit="lookup" class="space-y-3">
                    <label class="app-label" for="scan-code">Barcode / SKU / Kode Lokasi</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input
                            id="scan-code"
                            type="text"
                            wire:model.lazy="code"
                            autofocus
                            autocomplete="off"
                            placeholder="Scan atau ketik kode lalu Enter..."
                            class="app-input"
                        />
                        <button type="submit" class="app-btn app-btn-primary shrink-0 gap-2">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                            Cari
                        </button>
                    </div>
                    @error('code') <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </form>

                <div
                    class="mt-4 border-t border-app-border pt-4"
                    x-data="{
                        camera: false,
                        scanner: null,
                        async start() {
                            if (!window.Html5Qrcode) {
                                $dispatch('toast', { type: 'error', message: 'Library scanner belum termuat. Coba lagi.' });
                                return;
                            }
                            this.camera = true;
                            await this.$nextTick();
                            this.scanner = new window.Html5Qrcode('scan-camera');
                            try {
                                await this.scanner.start(
                                    { facingMode: 'environment' },
                                    { fps: 10, qrbox: { width: 220, height: 220 } },
                                    (decoded) => {
                                        const code = decoded;
                                        this.stop();
                                        $wire.lookupByCode(code);
                                    },
                                    () => {}
                                );
                            } catch (e) {
                                this.camera = false;
                                $dispatch('toast', { type: 'error', message: 'Tidak dapat mengakses kamera.' });
                            }
                        },
                        async stop() {
                            if (this.scanner) {
                                try { await this.scanner.stop(); this.scanner.clear(); } catch (e) {}
                                this.scanner = null;
                            }
                            this.camera = false;
                        }
                    }"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="start()" x-show="!camera" class="app-btn app-btn-secondary gap-2">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.51 2.25 9.575v7.35c0 1.29 1.01 2.325 2.25 2.325h15c1.24 0 2.25-1.035 2.25-2.325v-7.35c0-1.065-.749-1.995-1.802-2.17a57.37 57.37 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.823-1.316a2.25 2.25 0 00-1.9-1.055h-1.516a2.25 2.25 0 00-1.9 1.055l-.822 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                            Scan Kamera
                        </button>
                        <button type="button" @click="stop()" x-show="camera" x-cloak class="app-btn app-btn-secondary gap-2">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5A2.25 2.25 0 017.5 5.25h9a2.25 2.25 0 012.25 2.25v9a2.25 2.25 0 01-2.25 2.25h-9a2.25 2.25 0 01-2.25-2.25v-9z"/></svg>
                            Stop
                        </button>
                    </div>
                    <div x-show="camera" x-cloak class="mt-3 overflow-hidden rounded-xl border border-app-border bg-black">
                        <div id="scan-camera" class="mx-auto max-w-sm"></div>
                    </div>
                </div>
            </x-ui.card>

            @if ($result)
                @php($scanned = $result['item'])
                <x-ui.card padding="p-0">
                    <div class="flex flex-col gap-3 border-b border-app-border p-5 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex min-w-0 gap-3 sm:items-start">
                            @if(($result['item'] ?? null) && $result['item']->thumbUrl())
                                <img src="{{ $result['item']->thumbUrl() }}" alt="{{ $result['item']->name }}" class="h-16 w-16 shrink-0 rounded object-cover" loading="lazy">
                            @endif
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-base font-semibold text-app-text">{{ $scanned->name }}</h2>
                                    <x-ui.status-badge :status="$result['status']" :label="$result['status']->label()" />
                                </div>
                                <p class="mt-1 text-sm text-app-muted">SKU {{ $scanned->sku }} &middot; Barcode {{ $scanned->barcode ?? '-' }}</p>
                                <p class="mt-0.5 text-xs text-app-muted">{{ $scanned->category?->name ?? 'Tanpa kategori' }} &middot; {{ $scanned->brand ?? 'Tanpa brand' }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('items.show', $scanned) }}" class="app-btn app-btn-secondary">Detail</a>
                            <a href="{{ route('stock.index', ['search' => $scanned->sku]) }}" class="app-btn app-btn-secondary">Stock On Hand</a>
                        </div>
                    </div>

                    <div class="border-b border-app-border px-5 py-3">
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('goods-receipts.create', ['scan' => $scanned->sku]) }}" class="app-btn app-btn-secondary !py-1.5 text-xs">Tambah ke Barang Masuk</a>
                            <a href="{{ route('goods-issues.create', ['scan' => $scanned->sku]) }}" class="app-btn app-btn-secondary !py-1.5 text-xs">Tambah ke Barang Keluar</a>
                            <a href="{{ route('stock-adjustments.create', ['scan' => $scanned->sku]) }}" class="app-btn app-btn-secondary !py-1.5 text-xs">Stock Adjustment</a>
                        </div>
                    </div>

                    @if ($result['balances']->isEmpty())
                        <div class="p-5">
                            <x-ui.empty-state title="Belum ada stok" message="Tidak ada stock balance untuk barang ini." />
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="app-table">
                                <thead>
                                    <tr>
                                        <th>Warehouse</th>
                                        <th>Location</th>
                                        <th class="text-right">On Hand</th>
                                        <th class="text-right">Reserved</th>
                                        <th class="text-right">Available</th>
                                        <th class="text-right">Min</th>
                                        <th class="text-right">Max</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($result['balances'] as $balance)
                                        @php($balanceStatus = \App\Enums\StockStatus::evaluate((int) $balance->quantity_on_hand, (int) $scanned->minimum_stock, (int) $scanned->maximum_stock))
                                        <tr>
                                            <td>{{ $balance->warehouse?->name ?? '-' }}</td>
                                            <td class="text-app-muted">{{ $balance->location?->fullPath() ?? '-' }}</td>
                                            <td class="text-right font-medium">{{ $balance->quantity_on_hand }}</td>
                                            <td class="text-right text-app-muted">{{ $balance->quantity_reserved }}</td>
                                            <td class="text-right text-app-muted">{{ $balance->quantity_on_hand - $balance->quantity_reserved }}</td>
                                            <td class="text-right text-app-muted">{{ $scanned->minimum_stock }}</td>
                                            <td class="text-right text-app-muted">{{ $scanned->maximum_stock }}</td>
                                            <td><x-ui.status-badge :status="$balanceStatus" :label="$balanceStatus->label()" /></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-ui.card>
            @endif

            @if ($locationResult)
                @php($loc = $locationResult['location'])
                <x-ui.card padding="p-0">
                    <div class="border-b border-app-border p-5">
                        <h2 class="text-base font-semibold text-app-text">{{ $loc->fullPath() }}</h2>
                        <p class="mt-1 text-sm text-app-muted">Kode lokasi: {{ $loc->code ?? '-' }} &middot; Rak: {{ $loc->rack?->name ?? '-' }}</p>
                    </div>
                    @if ($locationResult['items']->isEmpty())
                        <div class="p-5">
                            <x-ui.empty-state title="Lokasi kosong" message="Tidak ada stock balance pada lokasi ini." />
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="app-table">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>{{ __('Barang') }}</th>
                                        <th class="text-right">On Hand</th>
                                        <th class="text-right">Reserved</th>
                                        <th class="text-right">Available</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($locationResult['items'] as $balance)
                                        <tr>
                                            <td class="whitespace-nowrap font-medium">{{ $balance->item?->sku ?? '-' }}</td>
                                            <td>
                                                @if ($balance->item)
                                                    <a href="{{ route('items.show', $balance->item) }}" class="app-link">{{ $balance->item->name }}</a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="text-right font-medium">{{ $balance->quantity_on_hand }}</td>
                                            <td class="text-right text-app-muted">{{ $balance->quantity_reserved }}</td>
                                            <td class="text-right text-app-muted">{{ $balance->quantity_on_hand - $balance->quantity_reserved }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-4">
            <x-ui.card>
                <h2 class="app-card-title">Riwayat Scan</h2>
                <p class="mt-1 text-xs text-app-muted">5 kode terakhir (klik untuk cari ulang).</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @forelse ($recent as $recentCode)
                        <button type="button" wire:click="lookupByCode(@js($recentCode))" class="app-badge bg-app-surface-2 text-app-text ring-app-border hover:bg-app-surface-2/70">
                            {{ $recentCode }}
                        </button>
                    @empty
                        <p class="text-sm text-app-muted">Belum ada scan.</p>
                    @endforelse
                </div>
            </x-ui.card>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</div>
