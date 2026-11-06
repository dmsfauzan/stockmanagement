# WSM Mobile (Flutter — Android)

Aplikasi Android untuk Warehouse Stock Management, mengonsumsi REST API project ini
(`/api`, Sanctum bearer token). UI bahasa Indonesia, Material 3, light + dark.

## Prasyarat

- Flutter 3.38+ / Dart 3.10+
- Android SDK / emulator atau perangkat fisik
- Backend berjalan (lihat root `README.md`)

## Menjalankan

Cukup:

```bash
flutter run
```

Saat startup, app **menemukan base URL API secara otomatis** berdasarkan
platform:

- **Emulator Android (paling andal)** → `http://127.0.0.1:8080/StockManagement/public/api`
  via `adb reverse tcp:8080 tcp:80` (lihat catatan di bawah).
- Emulator Android tanpa reverse → `http://10.0.2.2/StockManagement/public/api`.
- **Desktop/web** → `http://localhost/StockManagement/public/api`.
- **Vhost Laragon** → `http://stockmanagement.test/api`.
- `http://<host>:8000/api` tetap dicoba bila memakai `php artisan serve`.

> **Penting (emulator):** jalankan sekali per emulator agar port host diteruskan
> ke device — mengatasi kasus `10.0.2.2` tidak bisa dijangkau
> (Hyper-V/VPN/mode jaringan tertentu):
>
> ```bash
> adb reverse tcp:8080 tcp:80
> ```
>
> Ulangi setelah emulator restart. Hasil yang berhasil disimpan (di-cache)
> untuk startup berikutnya.

Prasyarat backend: cukup Laragon berjalan (Apache port 80). Alternatif tanpa
Laragon:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Override manual bila perlu (mis. perangkat fisik / LAN / rilis):

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.10/StockManagement/public/api
```

Akun demo: `admin@stock.test` / `password`.

## Build APK

```bash
flutter build apk --release --dart-define=API_BASE_URL=https://gudang.contoh.com/api
# hasil: build/app/outputs/flutter-apk/app-release.apk
```

Distribusi: sideload/internal (belum ke Play Store).

## Struktur

```
lib/
  core/        config, network (Dio + envelope), storage, theme (tokens+AppTheme), widgets, router
  data/        repositori per domain (auth, stock, ...)
  features/    layar per modul: auth, dashboard, stock, transactions, ...
```

## Status fase

- E1 — design tokens, widget library, auth (login/2FA), dashboard ✅
- E2 — stok list (filter + paging) + detail barang (tab) + master data ✅
- E3a — transaksi: hub, list, detail + workflow (GR/GI/Adjustment/Opname/Transfer) ✅
- E3b — form pembuatan + picker (item/satuan/lokasi/gudang/supplier/customer) ✅
- Fase 0 — laporan/settings, tautan dashboard, label picker, debounce/filter list ✅
- E4 — PO/SO/retur, requisition, perakitan, picking ✅
- E5 — karantina, lacak, recall, laporan (viewer generik), alat ✅
- E6 — scan kamera (`mobile_scanner`) ✅, banner + outbox offline ✅, finalisasi grafik, build APK
- Menyusul: push (FCM), cetak label Bluetooth, biometrik

## Offline (outbox + cache baca)

- **Tulis**: request (POST/PUT/PATCH/DELETE) yang gagal/ketika offline otomatis
  di-antrekan ke SQLite (`wsm_offline.db`) dengan `Idempotency-Key`, lalu dikirim
  ulang (dengan backoff) saat koneksi kembali atau via **Pengaturan →
  Sinkronisasi Offline**. Jumlah antre tampil sebagai badge pada tab "Lainnya".
- **Baca**: respons GET di-cache (`wsm_cache.db`); saat offline atau gagal
  jaringan, cache disajikan agar daftar/detail tetap dapat dibuka.

## CI

Job `mobile` di `.github/workflows/tests.yml` menjalankan `flutter pub get`,
`dart format --set-exit-if-changed`, `flutter analyze`, dan `flutter test`.

## Perintah pengembangan

```bash
dart format lib test
flutter analyze
flutter test
```
