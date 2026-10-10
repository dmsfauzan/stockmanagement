import 'package:dio/dio.dart';

import '../../core/network/api_exception.dart';
import '../../core/network/pagination.dart';

class QuarantineRow {
  const QuarantineRow({
    required this.id,
    this.itemId,
    this.sku,
    this.itemName,
    this.warehouse,
    this.location,
    this.onHand = 0,
    this.quarantine = 0,
    this.available = 0,
  });

  final int id;
  final int? itemId;
  final String? sku;
  final String? itemName;
  final String? warehouse;
  final String? location;
  final int onHand;
  final int quarantine;
  final int available;

  factory QuarantineRow.fromJson(Map<String, dynamic> j) => QuarantineRow(
    id: (j['id'] as num).toInt(),
    itemId: (j['item_id'] as num?)?.toInt(),
    sku: j['sku'] as String?,
    itemName: j['item_name'] as String?,
    warehouse: j['warehouse_name'] as String?,
    location: j['location_path'] as String?,
    onHand: (j['quantity_on_hand'] as num?)?.toInt() ?? 0,
    quarantine: (j['quantity_quarantine'] as num?)?.toInt() ?? 0,
    available: (j['quantity_available'] as num?)?.toInt() ?? 0,
  );
}

class QualityRepository {
  QualityRepository(this._dio);

  final Dio _dio;

  Future<PageResult<QuarantineRow>> quarantine({
    String? search,
    String? warehouseId,
    int page = 1,
    int perPage = 20,
  }) async {
    try {
      final res = await _dio.get(
        '/quarantine',
        queryParameters: {
          'page': page,
          'per_page': perPage,
          if (search != null && search.isNotEmpty) 'search': search,
          if (warehouseId != null && warehouseId.isNotEmpty)
            'warehouse_id': warehouseId,
        },
      );

      final body = res.data as Map<String, dynamic>;

      return PageResult(
        items: (body['data'] as List? ?? [])
            .map((e) => QuarantineRow.fromJson(e as Map<String, dynamic>))
            .toList(),
        pagination: Pagination.fromJson(body['meta'] as Map<String, dynamic>?),
      );
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memuat karantina.');
    }
  }

  Future<void> release(int id, {required int quantity, String? reason}) =>
      _action(id, 'release', quantity: quantity, reason: reason);

  Future<void> reject(int id, {required int quantity, String? reason}) =>
      _action(id, 'reject', quantity: quantity, reason: reason);

  Future<void> _action(
    int id,
    String verb, {
    required int quantity,
    String? reason,
  }) async {
    try {
      await _dio.post(
        '/quarantine/$id/$verb',
        data: {
          'quantity': quantity,
          if (reason != null && reason.isNotEmpty) 'reason': reason,
        },
      );
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Aksi karantina gagal.');
    }
  }
}
