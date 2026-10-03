# Warehouse Stock Management System

Sistem manajemen gudang (WMS) end-to-end — transaksi inventory → ledger → balance — dengan prinsip inventori akurat, auditable, tidak pernah negatif.

> **Akurasi stok, bukan kosmetik:** setiap perubahan stok berasal dari transaksi yang tercatat; tidak pernah di-edit angka balance secara manual.

## Fitur

| Area | Status |
|---|---|
| **Master Barang** (SKU unik, barcode, kategori, supplier, unit, status) | ✅ |
| **Kategori, Unit, Supplier, Customer/Department** | ✅ |
| **Warehouse → Zone → Rack → Location/Bin** | ✅ |
| **Barang Masuk** (`GR-YYYYMMDD-XXXX`): DRAFT→SUBMITTED→APPROVED→POSTED→REJECTED | ✅ |
| **Barang Keluar** (`GI-YYYYMMDD-XXXX`): alur sama + cek stok | ✅ |
| **Stock On Hand** + Stock Movement + Low Stock (auto `Available = On Hand - Reserved`) | ✅ |
| **Inventory Ledger** (append-only) + **document numbering** concurrency-safe | ✅ |
| **Stock Adjustment** (`ADJ-...`): draft→submitted→approved→posted (REJECTED, reversed) | ✅ |
| **Stock Opname** (`OPN-...`): generate daftar item → count fisik → variance → approve → auto-generate & post Adjustment | ✅ |
| **Transfer Barang** (`TR-...`): DRAFT→REQUESTED→APPROVED→IN_TRANSIT→RECEIVED→COMPLETED (REJECTED) + `TRANSFER_OUT/IN` | ✅ |
| **Reserved Stock** (`stock_reservations` + `quantity_reserved`; reserve saat Submit/Request, lepas saat Reject/Post/Dispatch) | ✅ |
| **Batch & Expiry** (Opsi B: tracking di `stock_movements.expiry_date`; Report `reports/expiry` + alert dashboard + expiry ≤7d saat posting) | ✅ |
| **Barcode/QR**: halaman `Scan` (hardware + kamera), label item/location + cetak bulk, prefill `?scan=` di form | ✅ |
| **Notifikasi** in-app (low/out, approval request, approved/rejected, opname/transfer) | ✅ |
| **Reversal** (koreksi transaksi posted tanpa hapus histori) | ✅ |
| **Dashboard** (KPI + movement chart + inventory by category + low stock + recent activity) | ✅ |
| **Reports** Stock/Incoming/Outgoing/Movement/Expiry → CSV/Excel/PDF | ✅ |
| **RBAC** + granular permissions + audit trail + validation + error handling + responsive | ✅ |
| **UI** Metronic-inspired, light/dark toggle, sidebar grup accordion (tersimpan) | ✅ |
| Purchase Order, valuation/COGS, Mobile/PWA, ERP/Accounting | ⏳ Phase 3 |

## Prinsip Inventori

```
Posting transaksi
        ↓
Stock Movement (Ledger, immutable)
        ↓
Stock Balance (cached: quantity_on_hand, quantity_reserved, quantity_available generated, last_movement_at)
```

Nomor dokumen via `document_sequences` dengan `SELECT ... FOR UPDATE`. Concurrent posting lock `stock_balances` + check `quantity_out <= available`; rollback on insufficient.

## Persyaratan

| Tool | Versi |
|---|---|
| PHP | ^8.3 |
| Composer | ^2.10 |
| Node + npm | ^24 + ^11 |
| MySQL | 8.0.30 (bundel Laragon) |

Ekstensi PHP: `pdo_mysql`, `mbstring`, `zip`, `gd`, `bcmath`, `intl`, `xml`, `fileinfo`. Verifikasi: `php -m`.

## Setup Cepat

```bash
composer install
cp .env.example .env

# buat database stockmanagement di MySQL (utf8mb4_unicode_ci)
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force

npm install
npm run build

php artisan serve
```

Laragon (Windows PowerShell) — php/mysql sudah bundel:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Skrip `composer setup` juga tersedia: `composer install && key && migrate && npm build` — lihat `composer.json` → `scripts.setup`. Dev: `composer dev`. Test: `composer test`.

### Konfigurasi DB

`.env.example` sudah diset ke MySQL `stockmanagement` (Laragon: `DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=root DB_PASSWORD=`). Untuk `sqlite`/`pgsql`, ubah `DB_CONNECTION`/`DB_DATABASE` di `.env`.

## Akun Seed

| Email | Password | Peran |
|---|---|---|
| `admin@stock.test` | `password` | Administrator (all permissions) |
| `staff@stock.test` | `password` | Warehouse Staff |
| `supervisor@stock.test` | `password` | Supervisor |
| `manager@stock.test` | `password` | Manager (read-only) |

Seed juga membuat: 6 kategori, 10 item, 2 warehouse+zone+rack+location, 3 supplier, 3 customer/department, transaksi demo (GR/GI posted/draft/submitted), adjustment/opname/transfer demo, batch `BATCH-DEMO-1` +5 hari (untuk expiry alert), `status=inactive` diabaikan saat seed.

> Self-registration nonaktif (route `register` dihapus). Akun dibuat via Admin → Users atau seeder.

## Struktur Penting

```
app/Livewire/{Dashboard|MasterData|Inventory|Transactions|Reports|Admin|Scanning}
app/Services/Inventory/{InventoryService,LedgerService,ExpiryService,ReservationService}
app/Services/Barcode/LabelService
app/Services/Support/{DocumentNumberService,AuditLogger,NotificationService}
app/Livewire/NotificationsBell.php
app/Http/Controllers/LabelController.php — handles /labels/* (QR/Barcode)
resources/views/labels/*, livewire/*, layouts/app.blade.php
```

## Penggunaan

| Halaman | Route |
|---|---|
| Dashboard | `GET /dashboard` |
| Master Barang / Kategori / Unit / Supplier / Customer / Warehouse / Location | `GET /items /categories /units /suppliers /customers /warehouses /locations` |
| Barang Masuk / Keluar | `GET /goods-receipts /goods-issues` |
| Stock On Hand / Movement / Low Stock | `GET /stock /stock/movements /stock/low-stock` |
| Adjustment / Opname / Transfer | `GET /stock-adjustments /stock-opnames /stock-transfers` |
| Scan | `GET /scan` |
| Reports | `GET /reports/{stock,incoming,outgoing,movement,expiry}` |
| Labels (QR/Barcode) | `GET /labels/items/{item} /labels/locations/{location} /labels/bulk?ids[]=1&ids[]=2` |
| Admin Users / Roles / Audit Logs / Settings | `GET /admin/{users,roles,audit-logs,settings}` |

Prefill transaksi dari Scan: `/goods-receipts/create?scan=BRG-001` (otomatis tambah baris).

## RBAC

43 permission (matrix: `dashboard.view`, `items.*`, `warehouse.*`, `goods_receipt.*`, `goods_issue.*`, `stock.*`, `stock_opname.*`, `transfer.*`, `reports.*`, `users.manage`, `roles.manage`, `settings.manage`, `audit_logs.view`). `Gate::before` mengizinkan `admin` bypass; `hasPermission()` cek `permissions.slug`.

## Keamanan & Validitas

- Session auth + CSRF, `password: 'hashed'`, rate-limit login via `LoginRequest::ensureIsNotRateLimited` (5/menit key `email|ip`).
- **Akun inactive diblokir** saat login (`LoginRequest::authenticate` + middleware `EnsureAccountActive` pada grup `auth`) dan dicatat (`last_login_at`).
- Validasi FE+BE; error teknis tidak diekspos; semua aktivitas penting via `AuditLogger`.

## Export & Chart

Excel via `maatwebsite/excel`, PDF via `barryvdh/laravel-dompdf`, chart dashboard via `apexcharts`.

## Pengujian

```bash
php artisan test
# filter spesifik
php artisan test --filter="StockOpnameTest|StockTransferTest|BarcodeQrTest"
```

Suite: 80 test, ~249 assertion (Inventory, RBAC, Page smoke, Import, Reversal, Reserved Stock, Expiry, Notifications, dsb.).

## Build & Deploy

```bash
npm run build        # produksi (manifest → public/build)
php artisan storage:link
```

## Lisensi

MIT. Template UI Metronic-inspired (bukan salinan kode Metronic — aman lisensi).
