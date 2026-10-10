import 'package:dio/dio.dart';

import '../../core/network/api_exception.dart';
import '../../core/network/pagination.dart';
import 'requisition_models.dart';

class RequisitionRepository {
  RequisitionRepository(this._dio);

  final Dio _dio;

  Future<PageResult<RequisitionRow>> list({
    String? search,
    String? status,
    String? warehouseId,
    int page = 1,
    int perPage = 20,
  }) async {
    try {
      final res = await _dio.get(
        '/requisitions',
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
            .map((e) => RequisitionRow.fromJson(e as Map<String, dynamic>))
            .toList(),
        pagination: Pagination.fromJson(body['meta'] as Map<String, dynamic>?),
      );
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memuat requisition.');
    }
  }

  Future<RequisitionDetail> show(int id) async {
    try {
      final res = await _dio.get('/requisitions/$id');
      final data =
          ((res.data as Map<String, dynamic>)['data'] ?? {})
              as Map<String, dynamic>;

      return RequisitionDetail.fromJson(data);
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memuat detail requisition.');
    }
  }

  Future<Map<String, dynamic>> create(Map<String, dynamic> payload) async {
    try {
      final res = await _dio.post('/requisitions', data: payload);

      return ((res.data as Map<String, dynamic>)['data'] ?? {})
          as Map<String, dynamic>;
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal menyimpan requisition.');
    }
  }

  Future<void> action(int id, String verb, {String? reason}) async {
    try {
      await _dio.post(
        '/requisitions/$id/$verb',
        data: reason != null ? {'reason': reason} : null,
      );
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Aksi requisition gagal.');
    }
  }

  /// Konversi PR → PO. Mengembalikan nomor PO bila ada.
  Future<String?> convert(
    int id, {
    int? supplierId,
    String? currencyCode,
  }) async {
    try {
      final res = await _dio.post(
        '/requisitions/$id/convert',
        data: {
          'supplier_id': ?supplierId,
          if (currencyCode != null && currencyCode.isNotEmpty)
            'currency_code': currencyCode,
        },
      );

      final data =
          ((res.data as Map<String, dynamic>)['data'] ?? {})
              as Map<String, dynamic>;

      return data['purchase_order'] as String?;
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Konversi ke PO gagal.');
    }
  }
}
