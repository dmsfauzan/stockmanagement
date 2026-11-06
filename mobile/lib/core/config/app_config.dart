/// Konfigurasi aplikasi.
///
/// Base URL dapat di-override saat build:
///   flutter run --dart-define=API_BASE_URL=http://10.0.2.2/StockManagement/public/api
///
/// Bila tidak di-override, saat startup [ApiDiscovery] akan mencoba menemukan
/// backend otomatis (emulator Android → host `10.0.2.2`, Laragon port 80).
class AppConfig {
  static const String appName = 'Stock Management';

  /// Override eksplisit via `--dart-define` (kosong bila tidak diberikan).
  static const String overrideBaseUrl = String.fromEnvironment('API_BASE_URL');

  /// Default: `adb reverse tcp:8080 tcp:80` → Laragon port 80 di host.
  static const String defaultBaseUrl =
      'http://127.0.0.1:8080/StockManagement/public/api';

  static String _baseUrl = overrideBaseUrl.isNotEmpty
      ? overrideBaseUrl
      : defaultBaseUrl;

  /// Base URL API yang sedang dipakai.
  static String get apiBaseUrl => _baseUrl;

  /// `true` bila base URL dipaksa lewat `--dart-define` (lewati discovery).
  static bool get hasOverride => overrideBaseUrl.isNotEmpty;

  static void setBaseUrl(String url) {
    final trimmed = url.trim();
    if (trimmed.isNotEmpty) _baseUrl = trimmed;
  }

  static const Duration connectTimeout = Duration(seconds: 20);
  static const Duration receiveTimeout = Duration(seconds: 30);
}
