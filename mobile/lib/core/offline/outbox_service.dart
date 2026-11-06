import 'dart:convert';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/app_config.dart';
import '../storage/secure_store.dart';
import 'outbox.dart';
import 'outbox_entry.dart';

/// Jeda backoff (detik) berdasarkan jumlah percobaan: 30s, 60s, … maks 30m.
int backoffSeconds(int attempts) => min(30 * (1 << min(attempts, 6)), 1800);

class OutboxFlushResult {
  const OutboxFlushResult({
    required this.sent,
    required this.failed,
    required this.remaining,
  });

  final int sent;
  final int failed;
  final int remaining;
}

/// Antrean offline: menyimpan request tulis yang gagal karena jaringan dan
/// mengirim ulang (idempotent) saat koneksi kembali.
class OutboxService {
  OutboxService(this._outbox, [SecureStore? store])
    : _store = store ?? SecureStore(),
      _dio = Dio(
        BaseOptions(
          baseUrl: '',
          connectTimeout: AppConfig.connectTimeout,
          receiveTimeout: AppConfig.receiveTimeout,
        ),
      );

  final Outbox _outbox;
  final SecureStore _store;
  final Dio _dio;

  bool _flushing = false;

  Future<void> enqueue(DioException e) => enqueueOptions(e.requestOptions);

  Future<void> enqueueOptions(RequestOptions req) async {
    final idempotencyKey = req.headers['Idempotency-Key']?.toString();

    String? data;
    if (req.data != null) {
      try {
        data = jsonEncode(req.data);
      } catch (_) {
        return; // Payload tak dapat diserialisasi → jangan antre.
      }
    }

    await _outbox.add(
      OutboxEntry(
        method: req.method,
        url: req.uri.toString(),
        data: data,
        idempotencyKey: idempotencyKey,
        createdAt: DateTime.now().toIso8601String(),
      ),
    );
  }

  Future<int> pending() => _outbox.count();

  Future<void> clear() => _outbox.clear();

  Future<int> failedCount() => _outbox.failedCount();

  Future<void> discardFailed() => _outbox.discardFailed();

  /// Kirim ulang seluruh antrean. Berhenti pada error jaringan berikutnya.
  Future<OutboxFlushResult> flush() async {
    if (_flushing) {
      return OutboxFlushResult(
        sent: 0,
        failed: 0,
        remaining: await _outbox.count(),
      );
    }

    _flushing = true;

    var sent = 0;
    var failed = 0;

    try {
      final token = await _store.readToken();
      final entries = await _outbox.due();

      for (final entry in entries) {
        try {
          final res = await _dio.request(
            entry.url,
            data: entry.data != null ? jsonDecode(entry.data!) : null,
            options: Options(
              method: entry.method,
              headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                if (token != null && token.isNotEmpty)
                  'Authorization': 'Bearer $token',
                if (entry.idempotencyKey != null)
                  'Idempotency-Key': entry.idempotencyKey,
              },
            ),
          );

          final code = res.statusCode ?? 0;
          if (code >= 200 && code < 400) {
            await _outbox.remove(entry.id!);
            sent++;
          }
        } on DioException catch (e) {
          final code = e.response?.statusCode;

          // 4xx (kecuali 401/429) = permanen → tandai gagal agar tetap terlihat
          // dan bisa ditinjau/dihapus pengguna (bukan dibuang diam-diam).
          if (code != null &&
              code >= 400 &&
              code < 500 &&
              code != 401 &&
              code != 429) {
            await _outbox.markFailed(entry.id!);
            failed++;
          } else {
            // Masih offline / server error → backoff lalu coba lagi nanti.
            final attempts = entry.attempts + 1;
            await _outbox.markAttempt(
              entry.id!,
              attempts,
              DateTime.now()
                  .add(Duration(seconds: backoffSeconds(entry.attempts)))
                  .toIso8601String(),
            );
            failed++;
            // Lanjut ke entri berikutnya; jangan blokir seluruh antrean.
            continue;
          }
        }
      }
    } finally {
      _flushing = false;
    }

    return OutboxFlushResult(
      sent: sent,
      failed: failed,
      remaining: await _outbox.count(),
    );
  }
}

final outboxProvider = Provider<Outbox>((ref) => Outbox());

final outboxServiceProvider = Provider<OutboxService>(
  (ref) => OutboxService(ref.watch(outboxProvider)),
);

final outboxCountProvider = FutureProvider<int>(
  (ref) => ref.watch(outboxProvider).count(),
);

final outboxFailedCountProvider = FutureProvider<int>(
  (ref) => ref.watch(outboxProvider).failedCount(),
);
