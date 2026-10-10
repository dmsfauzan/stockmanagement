import 'package:dio/dio.dart';

import '../../core/network/api_exception.dart';
import '../../core/network/pagination.dart';
import 'picking_models.dart';

class PickingRepository {
  PickingRepository(this._dio);

  final Dio _dio;

  Future<PageResult<PickListRow>> list({
    String? search,
    String? status,
    String? warehouseId,
    int page = 1,
    int perPage = 20,
  }) async {
    try {
      final res = await _dio.get(
        '/pick-lists',
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
            .map((e) => PickListRow.fromJson(e as Map<String, dynamic>))
            .toList(),
        pagination: Pagination.fromJson(body['meta'] as Map<String, dynamic>?),
      );
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memuat pick list.');
    }
  }

  Future<PickListDetail> show(int id) async {
    try {
      final res = await _dio.get('/pick-lists/$id');
      final data =
          ((res.data as Map<String, dynamic>)['data'] ?? {})
              as Map<String, dynamic>;

      return PickListDetail.fromJson(data);
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memuat detail pick list.');
    }
  }

  /// Buat pick list dari sebuah Goods Issue (id = GI id).
  Future<void> generate(int goodsIssueId, {int? assignedTo}) async {
    try {
      await _dio.post(
        '/goods-issues/$goodsIssueId/pick-list',
        data: {'assigned_to': ?assignedTo},
      );
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal membuat pick list.');
    }
  }

  Future<void> confirm(
    int pickListId,
    int lineId, {
    required int pickedQuantity,
    String? batchNumber,
    String? serialNumber,
  }) async {
    try {
      await _dio.post(
        '/pick-lists/$pickListId/items/$lineId/confirm',
        data: {
          'picked_quantity': pickedQuantity,
          if (batchNumber != null && batchNumber.isNotEmpty)
            'batch_number': batchNumber,
          if (serialNumber != null && serialNumber.isNotEmpty)
            'serial_number': serialNumber,
        },
      );
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal konfirmasi item.');
    }
  }

  Future<void> complete(int pickListId) async {
    try {
      await _dio.post('/pick-lists/$pickListId/complete');
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal menyelesaikan pick list.');
    }
  }

  Future<void> pack(int pickListId) async {
    try {
      await _dio.post('/pick-lists/$pickListId/pack');
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal pack pick list.');
    }
  }
}
