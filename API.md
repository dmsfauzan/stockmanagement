# REST API

API untuk integrasi (ERP, mobile, dsb). Autentikasi **Sanctum bearer token**, otorisasi berbasis **permission slug** yang sama dengan aplikasi web, dan rate limit **60 req/menit** per token/user.

Base URL: `{APP_URL}/api`

## Autentikasi

1. Buat token lewat **Admin → API Tokens** (menu Administration) atau CLI:

```bash
php artisan api:token admin@stock.test --abilities=items.view goods_receipt.post --expires=30
```

2. Kirim token pada header:

```
Authorization: Bearer <PLAIN_TEXT_TOKEN>
Accept: application/json
```

Token hanya ditampilkan sekali saat dibuat. `--abilities` menentukan permission yang boleh dipakai token; jika dikosongkan, token mewarisi semua permission user.

Cek identitas: `GET /api/me` → `{ data: { id, name, email, roles, permissions } }`.

## Format Respons

Sukses:
```json
{ "success": true, "message": "OK", "data": { }, "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 } }
```

Error:
```json
{ "success": false, "message": "Insufficient stock", "errors": { } }
```

Status: `200` OK · `201` dibuat · `401` belum auth · `403` tanpa permission · `409` konflik idempotency · `422` validasi/aturan bisnis · `429` rate limit.

Query umum untuk endpoint list: `?page=`, `?per_page=` (maks 100), plus filter spesifik (lihat tabel).

## Idempotency (aman retry)

Untuk mencegah dokumen ganda saat retry/network, kirim header **`Idempotency-Key`** (bebas, unik) pada request **write** (POST/PUT/PATCH/DELETE):

```
POST /api/goods-receipts HTTP/1.1
Authorization: Bearer <token>
Idempotency-Key: 6f1e2c3a-...
```

- Request pertama diproses; respons disimpan.
- Request ulang dengan key **sama & payload sama** → respons asli di-replay + header `Idempotent-Replay: true` (tidak membuat dokumen baru).
- Key sama tapi **payload/endpoint berbeda** → `409 Conflict`.
- Tanpa header → perilaku normal (tanpa proteksi idempotency).
- Kunci disimpan `IDEMPOTENCY_TTL_HOURS` (default 24 jam); respons `2xx` & `4xx` di-cache, `5xx` tidak (agar bisa retry).

## CORS

Origin yang diizinkan diatur via `CORS_ALLOWED_ORIGINS` (comma-separated; `*` = semua). Endpoint: `/api/*`, `sanctum/csrf-cookie`. Header `Idempotent-Replay` diekspos ke browser.

## Endpoint — Read

| Method | Path | Permission | Filter |
|---|---|---|---|
| GET | `/me` | — | |
| GET | `/items` · `/items/{id}` | `items.view` | `search`, `category_id`, `status`, `include=conversions\|bom` |
| GET | `/categories` | `items.view` | `search` |
| GET | `/units` | `items.view` | `search` |
| GET | `/suppliers` | `items.view` | `search`, `status` |
| GET | `/customers` | `items.view` | `search`, `type` |
| GET | `/customers/{id}/prices` | `items.view` | `search` |
| GET | `/warehouses` | `warehouse.view` | `search` |
| GET | `/locations` | `location.view` | `warehouse_id`, `search` |
| GET | `/stock` | `stock.view` | `warehouse_id`, `category_id`, `search`, `status` |
| GET | `/quarantine` | `stock.quarantine` | `warehouse_id`, `item_id`, `search` |
| POST | `/quarantine/{id}/release` · `/{id}/reject` | `stock.quarantine` | `quantity`, `reason` |
| GET | `/stock/movements` | `stock.view` | `item_id`, `warehouse_id`, `transaction_type`, `date_from`, `date_to` |
| GET | `/stock/low` | `stock.view` | `warehouse_id`, `category_id` |
| GET | `/goods-receipts` · `/{id}` | `goods_receipt.view` | `status`, `warehouse_id`, `date_from`, `date_to`, `search` |
| GET | `/goods-issues` · `/{id}` | `goods_issue.view` | sama |
| GET | `/stock-adjustments` · `/{id}` | `stock.adjustment` | `status`, `warehouse_id` |
| GET | `/stock-opnames` · `/{id}` | `stock_opname.view` | `status`, `warehouse_id` |
| GET | `/stock-transfers` · `/{id}` | `transfer.view` | `status`, `warehouse_id` |
| GET | `/purchase-orders` · `/{id}` | `purchase_order.view` | `status`, `supplier_id`, `warehouse_id` |
| GET | `/sales-orders` · `/{id}` | `sales_order.view` | `status`, `customer_id`, `warehouse_id` |
| GET | `/reports/stock` | `reports.view` | `warehouse_id`, `category_id`, `status` |
| GET | `/reports/incoming` | `reports.view` | `date_from`, `date_to`, `supplier_id`, `warehouse_id` |
| GET | `/reports/outgoing` | `reports.view` | `date_from`, `date_to`, `warehouse_id` |
| GET | `/reports/movement` | `reports.view` | `date_from`, `date_to`, `item_id`, `warehouse_id`, `transaction_type` |
| GET | `/reports/expiry` | `reports.view` | `status`, `warehouse_id` |
| GET | `/reports/opname` | `reports.view` | `status`, `warehouse_id`, `date_from`, `date_to` |
| GET | `/reports/adjustment` | `reports.view` | `status`, `warehouse_id`, `date_from`, `date_to` |
| GET | `/reports/transfer` | `reports.view` | `status`, `from_warehouse_id`, `to_warehouse_id` |
| GET | `/reports/warehouse-comparison` | `reports.view` | `search` |
| GET | `/reports/valuation` | `reports.view` | `warehouse_id`, `category_id`, `search` |
| GET | `/reports/cogs` | `reports.view` | `date_from`, `date_to`, `warehouse_id` |
| GET | `/reports/journal` | `reports.view` | `date_from`, `date_to`, `warehouse_id`, `transaction_type` |
| GET | `/reports/replenishment` | `reports.view` | `warehouse_id`, `category_id`, `search` |
| GET | `/reports/aging` | `reports.view` | `warehouse_id`, `category_id` |
| GET | `/reports/abc` | `reports.view` | `warehouse_id`, `date_from`, `date_to` |
| GET | `/reports/turnover` | `reports.view` | `warehouse_id`, `date_from`, `date_to` |
| GET | `/reports/slow-moving` | `reports.view` | `warehouse_id`, `days` |
| GET | `/reports/forecast` · `/reports/forecast/{itemId}` | `reports.view` | `warehouse_id`, `limit` |
| GET | `/reports/capacity` | `reports.view` | `warehouse_id` |
| GET | `/put-away-suggestion` | `reports.view` | `warehouse_id`, `item_id`, `quantity` |
| GET | `/currencies` | `items.view` | |
| GET | `/currency/convert` | `items.view` | `amount`, `from`, `to`, `date` |
| GET | `/traceability/{batch|serial}/{value}` | `stock.movement` | |
| GET | `/pick-lists` · `/pick-lists/{id}` | `picking.view` | `status`, `warehouse_id` |
| POST | `/goods-issues/{id}/pick-list` | `picking.create` | `assigned_to` |
| POST | `/pick-lists/{id}/items/{itemId}/confirm` · `/{id}/complete` | `picking.pick` | `picked_quantity`, `batch_number`, `serial_number` |
| POST | `/pick-lists/{id}/pack` | `picking.pack` | |
| GET | `/items/{id}?include=bom` | `items.view` | |
| GET | `/assembly-orders` · `/assembly-orders/{id}` | `assembly.view` | `status`, `warehouse_id` |
| POST | `/assembly-orders` | `assembly.create` | `type`, `item_id`, `quantity`, `warehouse_id`, `location_id` |
| GET | `/requisitions` · `/requisitions/{id}` | `requisition.view` | `status`, `warehouse_id` |
| POST | `/requisitions` | `requisition.create` | `request_date`, `warehouse_id`, `items[]`, `submit` |
| POST | `/requisitions/{id}/submit` · `/approve` · `/reject` · `/convert` | `requisition.submit/approve/convert` | `reason`, `supplier_id` |
| GET | `/recalls` | `stock.movement` | `status` |
| POST | `/recalls/impact` | `stock.movement` | `type`, `value` |
| POST | `/recalls` · `/{id}/lift` | `stock.movement` | `type`, `value`, `reason` |
| GET | `/accounting/journal` | `reports.view` | `date_from`, `date_to`, `warehouse_id`, `transaction_type` |
| GET | `/accounting/summary` | `reports.view` | `date_from`, `date_to`, `warehouse_id` |

## Endpoint — Write (Transaksi)

Pola: buat dokumen (status `draft`) lalu jalankan workflow. Semua posting memakai ledger yang sama dengan UI (atomic, anti double-post, cek stok).

| Aksi | Method & Path | Permission |
|---|---|---|
| Baca Retur Penjualan | `GET /customer-returns` · `/{id}` | `customer_return.view` |
| Buat/Workflow Retur Penjualan | `POST /customer-returns` · `/{id}/{submit\|approve\|reject\|post}` | `customer_return.create` / `.submit` / `.approve` / `.post` |
| Baca Retur Pembelian | `GET /supplier-returns` · `/{id}` | `supplier_return.view` |
| Buat/Workflow Retur Pembelian | `POST /supplier-returns` · `/{id}/{submit\|approve\|reject\|post}` | `supplier_return.create` / `.submit` / `.approve` / `.post` |
| Laporan Retur | `GET /reports/returns` | `reports.view` |
| Baca Price List Pelanggan | `GET /customers/{id}/prices` | `items.view` |
| Buat GR | `POST /goods-receipts` | `goods_receipt.create` |
| Submit/Approve/Reject/Post GR | `POST /goods-receipts/{id}/{submit\|approve\|reject\|post}` | `.submit` / `.approve` / `.approve` / `.post` |
| Buat GI | `POST /goods-issues` | `goods_issue.create` |
| Workflow GI | `POST /goods-issues/{id}/{submit\|approve\|reject\|post}` | IDEM |
| Buat/Workflow Adjustment | `POST /stock-adjustments` · `/{id}/{submit\|approve\|reject\|post}` | `stock.adjustment` / `stock.adjustment.approve` |
| Buat/Workflow Opname | `POST /stock-opnames` · `/{id}/{start\|submit\|approve\|reject}` | `stock_opname.create` / `.submit` / `.approve` |
| Buat/Workflow Transfer | `POST /stock-transfers` · `/{id}/{request\|approve\|reject\|dispatch\|receive\|complete}` | `transfer.create` / `.approve` / `.receive` |
| Buat/Workflow PO | `POST /purchase-orders` · `/{id}/{submit\|approve\|reject\|close}` | `purchase_order.create` / `.submit` / `.approve` |
| Buat/Workflow Sales Order | `POST /sales-orders` · `/{id}/{submit\|approve\|reject\|close}` | `sales_order.create` / `.submit` / `.approve` |

`reject` memerlukan body `{ "reason": "..." }`.

### Contoh: membuat & memposting Barang Masuk

```bash
curl -X POST "$APP_URL/api/goods-receipts" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{
    "transaction_date": "2026-10-07",
    "supplier_id": 1,
    "warehouse_id": 1,
    "items": [
      { "item_id": 1, "quantity": 10, "unit_id": 1, "location_id": 1, "unit_cost": 15000 }
    ]
  }'

curl -X POST "$APP_URL/api/goods-receipts/1/submit"  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
curl -X POST "$APP_URL/api/goods-receipts/1/approve" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
curl -X POST "$APP_URL/api/goods-receipts/1/post"    -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

Setelah `post`, `stock_balances` bertambah dan satu baris `stock_movements` (ledger) tercatat.
