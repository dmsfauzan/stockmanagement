# WSM Mobile (Flutter — Android)

Aplikasi Android untuk Warehouse Stock Management, mengonsumsi REST API project ini
(`/api`, Sanctum bearer token). UI bahasa Indonesia, Material 3, light + dark.

## Prasyarat

- Flutter 3.38+ / Dart 3.10+
- Android SDK / emulator atau perangkat fisik
- Backend berjalan (lihat root `README.md`)

## Menjalankan

Base URL API dikonfigurasi saat build via `--dart-define`:

```bash
# Android emulator → host
flutter run --dart-define=API_BASE_URL=http://10.0.2.2/api

# Perangkat fisik → IP LAN host
flutter run --dart-define=API_BASE_URL=http://192.168.1.10/api
```

Default: `http://10.0.2.2/api`. Akun demo: `admin@stock.test` / `password`.

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
- E3 — transaksi (GR/GI/Adjustment/Opname/Transfer)
- E4 — PO/SO/retur, requisition, perakitan, picking
- E5 — karantina, lacak, recall, laporan, lainnya
- E6 — scan kamera (`mobile_scanner`), finalisasi grafik, build APK
- Menyusul: offline (outbox), push (FCM), cetak label Bluetooth, biometrik

## Perintah pengembangan

```bash
dart format lib test
flutter analyze
flutter test
```
