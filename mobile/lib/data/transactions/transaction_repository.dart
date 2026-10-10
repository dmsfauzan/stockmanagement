import 'package:dio/dio.dart';

import '../../core/network/api_exception.dart';
import '../../core/network/pagination.dart';
import 'transaction_models.dart';

class TransactionRepository {
  TransactionRepository(this._dio);

  final Dio _dio;

  Future<PageResult<TxnListRow>> list(
    TxnType type, {
    String? search,
    String? status,
    String? warehouseId,
    int page = 1,
    int perPage = 20,
  }) async {
    try {
      final res = await _dio.get(
        type.listPath,
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
            .map((e) => TxnListRow.fromJson(e as Map<String, dynamic>))
            .toList(),
        pagination: Pagination.fromJson(body['meta'] as Map<String, dynamic>?),
      );
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memuat ${type.label}.');
    }
  }

  Future<TxnDetail> show(TxnType type, int id) async {
    try {
      final res = await _dio.get(type.detailPath(id));
      final data =
          ((res.data as Map<String, dynamic>)['data'] ?? {})
              as Map<String, dynamic>;

      return switch (type) {
        TxnType.receipt => TxnDetail.receipt(data),
        TxnType.issue => TxnDetail.issue(data),
        TxnType.adjustment => TxnDetail.adjustment(data),
        TxnType.opname => TxnDetail.opname(data),
        TxnType.transfer => TxnDetail.transfer(data),
      };
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memuat detail.');
    }
  }

  Future<void> action(
    TxnType type,
    int id,
    String verb, {
    String? reason,
  }) async {
    try {
      await _dio.post(
        '${type.detailPath(id)}/$verb',
        data: reason != null ? {'reason': reason} : null,
      );
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Aksi gagal.');
    }
  }

  Future<Map<String, dynamic>> create(
    TxnType type,
    Map<String, dynamic> payload,
  ) async {
    try {
      final res = await _dio.post(type.listPath, data: payload);
      final data =
          ((res.data as Map<String, dynamic>)['data'] ?? {})
              as Map<String, dynamic>;

      return data;
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal menyimpan.');
    }
  }
}
