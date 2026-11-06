import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../config/app_config.dart';
import '../offline/outbox_service.dart';
import '../offline/response_cache.dart';
import '../storage/secure_store.dart';
import 'api_exception.dart';
import 'connectivity.dart';
import 'session_events.dart';

final dioProvider = Provider<Dio>((ref) {
  final dio = Dio(
    BaseOptions(
      baseUrl: AppConfig.apiBaseUrl,
      connectTimeout: AppConfig.connectTimeout,
      receiveTimeout: AppConfig.receiveTimeout,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ),
  );

  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) async {
        if (options.method != 'GET') {
          options.headers['Idempotency-Key'] ??= const Uuid().v4();
        }

        final store = SecureStore();
        final token = await store.readToken();

        if (token != null && token.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $token';
        }

        // Bila diketahui offline, hindari menunggu timeout.
        final offline = ref.read(connectivityProvider).valueOrNull == false;

        if (offline && options.method == 'GET') {
          final cached = await ref
              .read(responseCacheProvider)
              .get(_cacheKey(options));

          if (cached != null) {
            return handler.resolve(_cachedResponse(options, cached));
          }

          return handler.reject(
            DioException(
              requestOptions: options,
              type: DioExceptionType.connectionError,
              error: ApiException(
                'Offline — data ini belum tersimpan untuk dibaca.',
              ),
            ),
          );
        }

        if (offline && _canQueue(options)) {
          try {
            await ref.read(outboxServiceProvider).enqueueOptions(options);
            ref.invalidate(outboxCountProvider);
          } catch (_) {
            // Bila gagal mengantre, lanjutkan request (akan gagal wajar).
          }

          return handler.reject(
            DioException(
              requestOptions: options,
              type: DioExceptionType.connectionError,
              error: ApiException(
                'Offline — disimpan ke antrean, akan dikirim saat online.',
                queued: true,
              ),
            ),
          );
        }

        return handler.next(options);
      },
      onResponse: (response, handler) async {
        final options = response.requestOptions;
        final code = response.statusCode ?? 0;

        if (options.method == 'GET' &&
            code >= 200 &&
            code < 300 &&
            response.data != null) {
          try {
            await ref
                .read(responseCacheProvider)
                .put(_cacheKey(options), jsonEncode(response.data));
          } catch (_) {
            // Cache bersifat best-effort.
          }
        }

        return handler.next(response);
      },
      onError: (e, handler) async {
        // Error yang sudah dibungkus (mis. dari offline guard) diteruskan apa adanya.
        if (e.error is ApiException) return handler.next(e);

        final code = e.response?.statusCode;
        final data = e.response?.data;

        if (code == 401 && !_isAuthPath(e.requestOptions.path)) {
          ref.read(sessionExpiredProvider.notifier).state++;
        }

        // Sajikan cache bila GET gagal karena jaringan.
        if (_isNetworkError(e) && e.requestOptions.method == 'GET') {
          try {
            final cached = await ref
                .read(responseCacheProvider)
                .get(_cacheKey(e.requestOptions));

            if (cached != null) {
              return handler.resolve(_cachedResponse(e.requestOptions, cached));
            }
          } catch (_) {
            // Lanjut ke penanganan error biasa.
          }
        }

        String message = e.message ?? 'Koneksi gagal.';

        if (data is Map && data['message'] is String) {
          message = data['message'] as String;
        } else if (e.type == DioExceptionType.connectionTimeout) {
          message =
              'Koneksi timeout. Server tidak merespons '
              '(${AppConfig.apiBaseUrl}).';
        } else if (e.type == DioExceptionType.connectionError) {
          message = 'Tidak dapat terhubung ke server. Periksa API_BASE_URL.';
        }

        var queued = false;
        if (_shouldQueue(e)) {
          try {
            await ref.read(outboxServiceProvider).enqueue(e);
            ref.invalidate(outboxCountProvider);
            queued = true;
          } catch (_) {
            // Gagal mengantre (mis. penyimpanan penuh) → laporkan error asli.
          }
        }

        return handler.reject(
          DioException(
            requestOptions: e.requestOptions,
            response: e.response,
            type: e.type,
            error: ApiException(
              queued
                  ? 'Disimpan ke antrean offline — akan dikirim saat online.'
                  : message,
              statusCode: code,
              errors: data is Map
                  ? (data['errors'] as Map<String, dynamic>?)
                  : null,
              queued: queued,
            ),
          ),
        );
      },
    ),
  );

  return dio;
});

String _cacheKey(RequestOptions options) => options.uri.toString();

Response<dynamic> _cachedResponse(RequestOptions options, String body) =>
    Response(
      requestOptions: options,
      statusCode: 200,
      data: jsonDecode(body),
      headers: Headers.fromMap({
        Headers.contentTypeHeader: ['application/json'],
        'x-from-cache': ['1'],
      }),
    );

bool _isNetworkError(DioException e) => const {
  DioExceptionType.connectionError,
  DioExceptionType.connectionTimeout,
  DioExceptionType.receiveTimeout,
  DioExceptionType.sendTimeout,
}.contains(e.type);

bool _isAuthPath(String path) {
  for (final p in const ['/login', '/logout', '/two-factor', '/me']) {
    if (path.startsWith(p)) return true;
  }

  return false;
}

bool _canQueue(RequestOptions options) {
  if (options.method == 'GET') return false;

  for (final skip in const ['/login', '/logout', '/two-factor']) {
    if (options.path.startsWith(skip)) return false;
  }

  return true;
}

bool _shouldQueue(DioException e) =>
    _isNetworkError(e) && _canQueue(e.requestOptions);

final secureStoreProvider = Provider<SecureStore>((_) => SecureStore());
