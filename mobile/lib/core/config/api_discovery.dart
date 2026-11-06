import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'app_config.dart';

const _cacheKey = 'api_base_url';
const _probeTimeout = Duration(milliseconds: 900);

/// Kandidat base URL yang dicoba saat startup, diurutkan per platform.
///
/// Backend dev disajikan Laragon pada port 80:
/// - `127.0.0.1:8080` → hasil `adb reverse tcp:8080 tcp:80` (paling andal di
///   emulator; dicoba lebih dulu).
/// - emulator Android → host `10.0.2.2` di path `StockManagement/public/api`
/// - desktop/web    → `localhost` di path yang sama
/// - vhost Laragon  → `stockmanagement.test/api`
/// `:8000/api` tetap didukung bila menjalankan `php artisan serve`.
List<String> candidateUrls() {
  const laragonPath = 'StockManagement/public/api';
  final isAndroid = !kIsWeb && defaultTargetPlatform == TargetPlatform.android;
  final host = isAndroid ? '10.0.2.2' : 'localhost';

  return [
    'http://127.0.0.1:8080/$laragonPath',
    'http://$host/$laragonPath',
    'http://stockmanagement.test/api',
    'http://$host:8000/api',
    if (isAndroid) 'http://localhost/$laragonPath',
  ];
}

/// Heuristik: respons menyerupai API project ini (envelope JSON + status wajar).
///
/// `GET /me` tanpa token seharusnya membalas 401 JSON; vhost default Laragon
/// membalas 404/HTML sehingga tidak lolos.
bool looksLikeApi(int? status, dynamic data) {
  if (status != 200 && status != 401 && status != 403) return false;

  return data is Map;
}

/// Menemukan base URL API secara otomatis saat startup.
class ApiDiscovery {
  static Future<String> resolve() async {
    if (AppConfig.hasOverride) return AppConfig.overrideBaseUrl;
    if (kReleaseMode) return AppConfig.defaultBaseUrl;

    // Pakai cache bila masih hidup.
    try {
      final prefs = await SharedPreferences.getInstance();
      final cached = prefs.getString(_cacheKey);

      if (cached != null && cached.isNotEmpty && await _probe(cached)) {
        return cached;
      }
    } catch (_) {
      // Lanjut probing.
    }

    for (final candidate in candidateUrls()) {
      if (await _probe(candidate)) {
        try {
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString(_cacheKey, candidate);
        } catch (_) {
          // Cache best-effort.
        }

        return candidate;
      }
    }

    return AppConfig.defaultBaseUrl;
  }

  static Future<bool> _probe(String baseUrl) async {
    final dio = Dio(
      BaseOptions(
        baseUrl: baseUrl,
        connectTimeout: _probeTimeout,
        receiveTimeout: _probeTimeout,
        headers: {'Accept': 'application/json'},
      ),
    );

    try {
      final res = await dio.get(
        '/me',
        options: Options(validateStatus: (_) => true),
      );

      return looksLikeApi(res.statusCode, res.data);
    } catch (_) {
      return false;
    } finally {
      dio.close(force: true);
    }
  }
}
