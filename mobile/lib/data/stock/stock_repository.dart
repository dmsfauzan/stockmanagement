import 'package:dio/dio.dart';

import '../../core/network/api_exception.dart';
import '../../core/network/pagination.dart';

class StockRow {
  const StockRow({
    required this.id,
    required this.itemId,
    required this.sku,
    required this.itemName,
    this.warehouse,
    this.location,
    this.onHand = 0,
    this.reserved = 0,
    this.quarantine = 0,
    this.available = 0,
    this.min = 0,
    this.max = 0,
    this.status,
    this.lastMovementAt,
  });

  final int id;
  final int itemId;
  final String sku;
  final String itemName;
  final String? warehouse;
  final String? location;
  final int onHand;
  final int reserved;
  final int quarantine;
  final int available;
  final int min;
  final int max;
  final String? status;
  final String? lastMovementAt;

  factory StockRow.fromJson(Map<String, dynamic> json) => StockRow(
    id: (json['id'] as num).toInt(),
    itemId: (json['item_id'] as num?)?.toInt() ?? 0,
    sku: '${json['sku'] ?? ''}',
    itemName: '${json['item_name'] ?? ''}',
    warehouse: json['warehouse'] as String?,
    location: json['location'] as String?,
    onHand: (json['on_hand'] as num?)?.toInt() ?? 0,
    reserved: (json['reserved'] as num?)?.toInt() ?? 0,
    quarantine: (json['quarantine'] as num?)?.toInt() ?? 0,
    available: (json['available'] as num?)?.toInt() ?? 0,
    min: (json['min'] as num?)?.toInt() ?? 0,
    max: (json['max'] as num?)?.toInt() ?? 0,
    status: json['status'] as String?,
    lastMovementAt: json['last_movement_at'] as String?,
  );
}

class StockMovement {
  const StockMovement({
    required this.id,
    required this.sku,
    required this.itemName,
    this.warehouse,
    this.location,
    required this.type,
    this.quantityIn = 0,
    this.quantityOut = 0,
    this.balanceAfter = 0,
    this.reference,
    this.createdBy,
    this.createdAt,
  });

  final int id;
  final String sku;
  final String itemName;
  final String? warehouse;
  final String? location;
  final String type;
  final int quantityIn;
  final int quantityOut;
  final int balanceAfter;
  final String? reference;
  final String? createdBy;
  final String? createdAt;

  factory StockMovement.fromJson(Map<String, dynamic> json) => StockMovement(
    id: (json['id'] as num).toInt(),
    sku: '${json['sku'] ?? ''}',
    itemName: '${json['item_name'] ?? ''}',
    warehouse: json['warehouse'] as String?,
    location: json['location'] as String?,
    type: '${json['type'] ?? ''}',
    quantityIn: (json['quantity_in'] as num?)?.toInt() ?? 0,
    quantityOut: (json['quantity_out'] as num?)?.toInt() ?? 0,
    balanceAfter: (json['balance_after'] as num?)?.toInt() ?? 0,
    reference: json['reference'] as String?,
    createdBy: json['created_by'] as String?,
    createdAt: json['created_at'] as String?,
  );
}

class StockRepository {
  StockRepository(this._dio);

  final Dio _dio;

  Future<PageResult<StockRow>> list({
    String? search,
    String? status,
    String? warehouseId,
    int page = 1,
    int perPage = 20,
  }) async {
    try {
      final res = await _dio.get(
        '/stock',
        queryParameters: {
          'page': page,
          'per_page': perPage,
          if (search != null && search.isNotEmpty) 'search': search,
          if (status != null && status.isNotEmpty) 'status': status,
          if (warehouseId != null && warehouseId.isNotEmpty)
            'warehouse_id': warehouseId,
        },
      );

      final body = res.data as Map<String, dynamic>;

      return PageResult(
        items: (body['data'] as List? ?? [])
            .map((e) => StockRow.fromJson(e as Map<String, dynamic>))
            .toList(),
        pagination: Pagination.fromJson(body['meta'] as Map<String, dynamic>?),
      );
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memuat stok.');
    }
  }

  Future<List<StockRow>> low({int perPage = 10}) async {
    try {
      final res = await _dio.get(
        '/stock/low',
        queryParameters: {'per_page': perPage},
      );
      final body = res.data as Map<String, dynamic>;

      return (body['data'] as List? ?? [])
          .map((e) => StockRow.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memuat low stock.');
    }
  }

  Future<List<StockMovement>> movements({int? itemId, int perPage = 50}) async {
    try {
      final params = <String, dynamic>{'per_page': perPage};
      if (itemId != null) {
        params['item_id'] = itemId;
      }

      final res = await _dio.get('/stock/movements', queryParameters: params);
      final body = res.data as Map<String, dynamic>;

      final raw = body['data'] as List?;

      return (raw ?? [])
          .map((e) => StockMovement.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memuat movement.');
    }
  }
}
