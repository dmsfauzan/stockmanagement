import 'package:dio/dio.dart';

import '../../core/network/api_exception.dart';
import '../../core/network/pagination.dart';

class RecallRow {
  const RecallRow({
    required this.id,
    this.type,
    this.batchNumber,
    this.serialNumber,
    this.reason,
    required this.status,
    this.recalledAt,
  });

  final int id;
  final String? type;
  final String? batchNumber;
  final String? serialNumber;
  final String? reason;
  final String status;
  final String? recalledAt;

  factory RecallRow.fromJson(Map<String, dynamic> j) => RecallRow(
    id: (j['id'] as num).toInt(),
    type: j['type'] as String?,
    batchNumber: j['batch_number'] as String?,
    serialNumber: j['serial_number'] as String?,
    reason: j['reason'] as String?,
    status: '${j['status'] ?? ''}',
    recalledAt: j['recalled_at'] as String?,
  );

  String get value => batchNumber ?? serialNumber ?? '-';
}

class RecallImpact {
  const RecallImpact({
    this.type,
    this.value,
    this.onHand = 0,
    this.stock = const [],
    this.documents = const [],
    this.activeRecall,
  });

  final String? type;
  final String? value;
  final int onHand;
  final List<Map<String, dynamic>> stock;
  final List<Map<String, dynamic>> documents;
  final Map<String, dynamic>? activeRecall;

  factory RecallImpact.fromJson(Map<String, dynamic> j) => RecallImpact(
    type: j['type'] as String?,
    value: j['value'] as String?,
    onHand: (j['on_hand'] as num?)?.toInt() ?? 0,
    stock: ((j['stock'] as List?) ?? [])
        .map((e) => e as Map<String, dynamic>)
        .toList(),
    documents: ((j['documents'] as List?) ?? [])
        .map((e) => e as Map<String, dynamic>)
        .toList(),
    activeRecall: j['active_recall'] as Map<String, dynamic>?,
  );
}

class RecallRepository {
  RecallRepository(this._dio);

  final Dio _dio;

  Future<PageResult<RecallRow>> list({
    String? status,
    int page = 1,
    int perPage = 20,
  }) async {
    try {
      final res = await _dio.get(
        '/recalls',
        queryParameters: {
          'page': page,
          'per_page': perPage,
          if (status != null && status.isNotEmpty) 'status': status,
        },
      );

      final body = res.data as Map<String, dynamic>;

      return PageResult(
        items: (body['data'] as List? ?? [])
            .map((e) => RecallRow.fromJson(e as Map<String, dynamic>))
            .toList(),
        pagination: Pagination.fromJson(body['meta'] as Map<String, dynamic>?),
      );
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memuat recall.');
    }
  }

  Future<RecallImpact> impact({
    required String type,
    required String value,
  }) async {
    try {
      final res = await _dio.post(
        '/recalls/impact',
        data: {'type': type, 'value': value},
      );
      final data =
          ((res.data as Map<String, dynamic>)['data'] ?? {})
              as Map<String, dynamic>;

      return RecallImpact.fromJson(data);
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memeriksa dampak recall.');
    }
  }

  Future<void> create({
    required String type,
    required String value,
    required String reason,
  }) async {
    try {
      await _dio.post(
        '/recalls',
        data: {'type': type, 'value': value, 'reason': reason},
      );
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal membuat recall.');
    }
  }

  Future<void> lift(int id) async {
    try {
      await _dio.post('/recalls/$id/lift');
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal mencabut recall.');
    }
  }
}
