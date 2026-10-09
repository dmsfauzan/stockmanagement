<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    /*
     * Which routes to document. String or array form; use Scramble::routes() for custom selection.
     *
     * 'api_path' => [
     *     'include' => 'api',
     *     'exclude' => ['api/internal'],
     * ],
     *
     * Without *, patterns match path segments (api matches api and api/users, not apiary).
     * With *, Str::is is used (e.g. api/v*).
     *
     * One static include → default server is /{include} and paths are stripped (/users).
     * Multiple includes or wildcards → server defaults to / and paths stay full (/api/users).
     * Override with `servers`, or use Scramble::registerApi() for separate bases.
     */
    'api_path' => 'api',

    /*
     * Your API domain. By default, app domain is used. This is also a part of the default API routes
     * matcher, so when implementing your own, make sure you use this config if needed.
     */
    'api_domain' => null,

    /*
     * The path where your OpenAPI specification will be exported.
     */
    'export_path' => 'api.json',

    /*
     * Cache configuration for the generated OpenAPI document.
     *
     * Use `scramble:cache` to warm the cache and `scramble:clear` to invalidate it.
     */
    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    'info' => [
        /*
         * API version.
         */
        'version' => env('API_VERSION', '1.0.0'),

        /*
         * Description rendered on the home page of the API documentation (`/docs/api`).
         */
        'description' => <<<'MD'
        # Warehouse Stock Management — REST API

        > Kelola gudang *end-to-end* lewat HTTP: master data, transaksi (Barang Masuk/Keluar, Adjustment, Opname, Transfer, PO, SO), laporan, dan posting **ledger** yang sama persis dengan UI — atomik, anti double-post, dan cek stok.

        ![version](https://img.shields.io/badge/version-1.0.0-blue) ![auth](https://img.shields.io/badge/auth-Bearer%20token-6f42c1) ![rate-limit](https://img.shields.io/badge/rate%20limit-60%20req%2Fmenit-orange) ![format](https://img.shields.io/badge/format-JSON%20envelope-2ea043)

        ## Base URL

        Setiap path di bawah ini relatif terhadap:

        ```
        {APP_URL}/api
        ```

        ## Autentikasi

        API memakai **Laravel Sanctum bearer token** dengan otorisasi berbasis **permission slug** yang sama dengan aplikasi web.

        1. Buat token lewat **Admin → API Tokens**, atau via CLI:

        ```bash
        php artisan api:token admin@stock.test \
          --abilities=items.view goods_receipt.post \
          --expires=30
        ```

        2. Kirim token di setiap request:

        ```
        Authorization: Bearer <PLAIN_TEXT_TOKEN>
        Accept: application/json
        ```

        > Token hanya ditampilkan **sekali** saat dibuat. Bila `--abilities` dikosongkan, token mewarisi seluruh permission user. Cek identitas dengan `GET /me`.

        ## Format Respons

        Semua respons memakai envelope yang konsisten. **Sukses:**

        ```json
        {
          "success": true,
          "message": "OK",
          "data": {},
          "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 }
        }
        ```

        **Error:**

        ```json
        { "success": false, "message": "Insufficient stock", "errors": {} }
        ```

        | Status | Arti |
        | --- | --- |
        | `200` | OK |
        | `201` | Data/dokumen dibuat |
        | `401` | Belum terautentikasi / token tidak valid |
        | `403` | Tidak punya permission |
        | `404` | Data tidak ditemukan |
        | `422` | Validasi gagal atau aturan bisnis dilanggar (mis. stok kurang) |
        | `429` | Melebihi rate limit |

        ## Pagination & Filter Umum

        Endpoint *list* mendukung:

        | Parameter | Deskripsi |
        | --- | --- |
        | `page` | Nomor halaman (default `1`) |
        | `per_page` | Jumlah per halaman (maks `100`) |
        | `date_from` / `date_to` | Rentang tanggal `YYYY-MM-DD` (endpoint transaksi & laporan) |
        | `search` | Pencarian teks |
        | `warehouse_id`, `category_id`, `status`, dll | Filter spesifik per endpoint (lihat tiap operasi) |

        ## Peta Endpoint

        | Grup | Prefiks | Permission |
        | --- | --- | --- |
        | Identitas | `/me` | — |
        | Master data | `/items` (`?include=conversions\|bom`), `/categories`, `/units`, `/suppliers`, `/customers`, `/warehouses`, `/locations`, `/currencies` | `items.view`, `warehouse.view`, `location.view` |
        | Stok | `/stock`, `/stock/movements`, `/stock/low` | `stock.view` |
        | Transaksi | `/goods-receipts`, `/goods-issues`, `/stock-adjustments`, `/stock-opnames`, `/stock-transfers`, `/purchase-orders`, `/sales-orders` | `*.view` / `*.create` |
        | Picking | `/pick-lists`, `/pick-lists/{id}`, `POST /goods-issues/{id}/pick-list`, `/pick-lists/{id}/items/{itemId}/confirm`, `/{id}/complete`, `/{id}/pack` | `picking.view` / `.create` / `.pick` / `.pack` |
        | Perakitan (Kitting/BOM) | `/assembly-orders`, `/assembly-orders/{id}` | `assembly.view` / `.create` |
        | Requisition | `/requisitions`, `/requisitions/{id}`, `/{id}/{submit\|approve\|reject\|convert}` | `requisition.view` / `.create` / `.submit` / `.approve` / `.convert` |
        | Karantina (QC) | `/quarantine`, `/quarantine/{id}/release`, `/{id}/reject` | `stock.quarantine` |
        | Lacak & Recall | `/traceability/{type}/{value}`, `/recalls`, `/recalls/impact`, `/recalls/{id}/lift` | `stock.movement` |
        | Mata uang | `/currencies`, `/currency/convert` | `items.view` |
        | Analitik & Kapasitas | `/reports/aging`, `/abc`, `/turnover`, `/slow-moving`, `/forecast`, `/forecast/{itemId}`, `/capacity`, `/put-away-suggestion` | `reports.view` |
        | Laporan | `/reports/*`, `/accounting/*` | `reports.view` |

        ## Quick start

        ```bash
        export APP_URL="http://stockmanagement.test"
        export TOKEN="<token-anda>"

        # 1) cek identitas & permission
        curl "$APP_URL/api/me" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"

        # 2) daftar barang
        curl "$APP_URL/api/items?search=mouse&per_page=5" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"

        # 3) laporan stok rendah
        curl "$APP_URL/api/stock/low" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
        ```

        ## Alur Transaksi (Write)

        Pola semua dokumen: **buat** (status `draft`) → jalankan **workflow** (`submit → approve → post`). Posting memakai `LedgerService` yang sama dengan UI.

        ```bash
        # Buat Barang Masuk
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

        # Jalankan workflow
        curl -X POST "$APP_URL/api/goods-receipts/1/submit"  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
        curl -X POST "$APP_URL/api/goods-receipts/1/approve" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
        curl -X POST "$APP_URL/api/goods-receipts/1/post"    -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
        ```

        Setelah `post`, `stock_balances` bertambah dan satu baris `stock_movements` (ledger) tercatat. Aksi `reject` memerlukan body `{ "reason": "..." }`.

        ## Integrasi Lanjutan

        - **Webhook keluar** — event `goods_receipt.posted`, `goods_issue.posted`, `adjustment.posted`, `stock_opname.completed`, `transfer.dispatched`, `transfer.received`, `sales_order.fulfilled`. Body ditandatangani **HMAC-SHA256** (`X-Signature`), header `X-Webhook-Event` & `X-Timestamp`. Atur di **Admin → Integrations**.
        - **Ekspor jurnal terjadwal** — `accounting:export --period=daily|monthly` menulis CSV/XLSX ke `storage/app/accounting-exports` dan opsional email lampiran.
        - **Rate limit** — `60` request/menit per token/user (header `Retry-After` saat `429`).

        Dokumentasi statis tambahan: lihat [`API.md`](/API.md) di repositori.
        MD,
    ],

    'ui' => [
        'title' => 'Warehouse Stock API',
    ],

    /*
     * Load Scramble's development tools on documentation pages. An explicit
     * SCRAMBLE_DEV_TOOLS value takes precedence over APP_DEBUG.
     */
    'dev_tools' => [
        'enabled' => env('SCRAMBLE_DEV_TOOLS', env('APP_DEBUG', false)),
    ],

    'renderer' => 'elements',

    'renderers' => [
        /*
         * Stoplight Elements config options: https://docs.stoplight.io/docs/elements/b074dc47b2826-elements-configuration-options
         */
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
        /*
         * Scalar API reference config options: https://scalar.com/products/api-references/configuration
         */
        'scalar' => [
            'view' => 'scramble::scalar',
            'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
            'theme' => 'laravel',
            'proxyUrl' => 'https://proxy.scalar.com',
            'darkMode' => false,
            'showDeveloperTools' => 'never',
            'agent' => ['disabled' => true],
            'credentials' => 'include',
        ],
    ],

    /*
     * The list of servers of the API. By default, when `null`, server URL will be created from
     * `scramble.api_path` and `scramble.api_domain` config variables. When providing an array, you
     * will need to specify the local server URL manually (if needed).
     *
     * Example of non-default config (final URLs are generated using Laravel `url` helper):
     *
     * ```php
     * 'servers' => [
     *     'Live' => 'api',
     *     'Prod' => 'https://scramble.dedoc.co/api',
     * ],
     * ```
     */
    'servers' => null,

    /**
     * Determines how Scramble stores the descriptions of enum cases.
     * Available options:
     * - 'description' – Case descriptions are stored as the enum schema's description using table formatting.
     * - 'extension' – Case descriptions are stored in the `x-enumDescriptions` enum schema extension.
     *
     *    @see https://redocly.com/docs-legacy/api-reference-docs/specification-extensions/x-enum-descriptions
     * - false - Case descriptions are ignored.
     */
    'enum_cases_description_strategy' => 'description',

    /**
     * Determines how Scramble stores the names of enum cases.
     * Available options:
     * - 'names' – Case names are stored in the `x-enumNames` enum schema extension.
     * - 'varnames' - Case names are stored in the `x-enum-varnames` enum schema extension.
     * - false - Case names are not stored.
     */
    'enum_cases_names_strategy' => false,

    /**
     * When Scramble encounters deep objects in query parameters, it flattens the parameters so the generated
     * OpenAPI document correctly describes the API. Flattening deep query parameters is relevant until
     * OpenAPI 3.2 is released and query string structure can be described properly.
     *
     * For example, this nested validation rule describes the object with `bar` property:
     * `['foo.bar' => ['required', 'int']]`.
     *
     * When `flatten_deep_query_parameters` is `true`, Scramble will document the parameter like so:
     * `{"name":"foo[bar]", "schema":{"type":"int"}, "required":true}`.
     *
     * When `flatten_deep_query_parameters` is `false`, Scramble will document the parameter like so:
     *  `{"name":"foo", "schema": {"type":"object", "properties":{"bar":{"type": "int"}}, "required": ["bar"]}, "required":true}`.
     */
    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],

    /*
     * Automatically document API security (OpenAPI `security` / `securitySchemes`) based on route
     * middleware.
     *
     * Disabled by default. Uncomment the line below to enable `MiddlewareAuthSecurityStrategy`.
     * When at least one documented route uses middleware matching the configured patterns (by default
     * `auth` and `auth:*`), bearer auth is applied globally. Routes without matching middleware are
     * marked as public (`security: []`).
     *
     * Set to `null` explicitly to disable. If you already configure security manually via
     * `afterOpenApiGenerated` / `extendOpenApi`, keep this disabled to avoid duplicate schemes.
     *
     * Customize with a class-string or [class, options]:
     *
     * 'security_strategy' => [
     *     \Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy::class,
     *     [
     *         'middleware' => ['auth', 'auth:*'],
     *         'scheme' => \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer'),
     *     ],
     * ],
     */
    // 'security_strategy' => \Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy::class,
    'security_strategy' => null,
];
