/// Konfigurasi aplikasi. Base URL dapat di-override saat build:
///   flutter run --dart-define=API_BASE_URL=http://10.0.2.2/api
class AppConfig {
  static const String appName = 'Stock Management';

  /// Android emulator → host `10.0.2.2`. Perangkat fisik → IP LAN host.
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2/api',
  );

  static const Duration connectTimeout = Duration(seconds: 20);
  static const Duration receiveTimeout = Duration(seconds: 30);
}
