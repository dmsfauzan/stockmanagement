import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../config/app_config.dart';
import '../storage/secure_store.dart';
import 'api_exception.dart';

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

        return handler.next(options);
      },
      onError: (e, handler) {
        final code = e.response?.statusCode;
        final data = e.response?.data;
        String message = e.message ?? 'Koneksi gagal.';

        if (data is Map && data['message'] is String) {
          message = data['message'] as String;
        } else if (e.type == DioExceptionType.connectionTimeout) {
          message = 'Koneksi timeout.';
        } else if (e.type == DioExceptionType.connectionError) {
          message = 'Tidak dapat terhubung ke server. Periksa API_BASE_URL.';
        }

        return handler.reject(
          DioException(
            requestOptions: e.requestOptions,
            response: e.response,
            type: e.type,
            error: ApiException(
              message,
              statusCode: code,
              errors: data is Map
                  ? (data['errors'] as Map<String, dynamic>?)
                  : null,
            ),
          ),
        );
      },
    ),
  );

  return dio;
});

final secureStoreProvider = Provider<SecureStore>((_) => SecureStore());
