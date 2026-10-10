import 'package:dio/dio.dart';

import '../../core/network/api_exception.dart';
import '../../core/network/pagination.dart';

class StockRow {
  const StockRow({
    required this.id,
    required this.sku,
    required this.itemName,
    required this.warehouse,
    required this.location,
    required this.onHand,
    required this.available,
    required this.min,
    required this.status,
  });

  final int id;
  final String sku;
  final String itemName;
  final String? warehouse;
  final String? location;
  final int onHand;
  final int available;
  final int min;
  final String? status;

  factory StockRow.fromJson(Map<String, dynamic> json) => StockRow(
    id: (json['id'] as num).toInt(),
    sku: '${json['sku'] ?? ''}',
    itemName: '${json['item_name'] ?? ''}',
    warehouse: json['warehouse'] as String?,
    location: json['location'] as String?,
    onHand: (json['on_hand'] as num?)?.toInt() ?? 0,
    available: (json['available'] as num?)?.toInt() ?? 0,
    min: (json['min'] as num?)?.toInt() ?? 0,
    status: json['status'] as String?,
  );
}

class StockRepository {
  StockRepository(this._dio);

  final Dio _dio;

  Future<PageResult<StockRow>> list({
    String? search,
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
        },
      );

      final body = res.data as Map<String, dynamic>;
      final items = (body['data'] as List? ?? [])
          .map((e) => StockRow.fromJson(e as Map<String, dynamic>))
          .toList();

      return PageResult(
        items: items,
        pagination: Pagination.fromJson(body['meta'] as Map<String, dynamic>?),
      );
    } on DioException catch (e) {
      if (e.error is ApiException) throw e.error as ApiException;
      rethrow;
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
      if (e.error is ApiException) throw e.error as ApiException;
      rethrow;
    }
  }
}
