import 'package:dio/dio.dart';

import '../../core/network/api_exception.dart';
import '../../core/network/pagination.dart';
import 'assembly_models.dart';

class AssemblyRepository {
  AssemblyRepository(this._dio);

  final Dio _dio;

  Future<PageResult<AssemblyRow>> list({
    String? search,
    String? status,
    String? warehouseId,
    int page = 1,
    int perPage = 20,
  }) async {
    try {
      final res = await _dio.get(
        '/assembly-orders',
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
            .map((e) => AssemblyRow.fromJson(e as Map<String, dynamic>))
            .toList(),
        pagination: Pagination.fromJson(body['meta'] as Map<String, dynamic>?),
      );
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memuat assembly order.');
    }
  }

  Future<AssemblyDetail> show(int id) async {
    try {
      final res = await _dio.get('/assembly-orders/$id');
      final data =
          ((res.data as Map<String, dynamic>)['data'] ?? {})
              as Map<String, dynamic>;

      return AssemblyDetail.fromJson(data);
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memuat detail assembly.');
    }
  }

  Future<void> create(Map<String, dynamic> payload) async {
    try {
      await _dio.post('/assembly-orders', data: payload);
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal menyimpan assembly order.');
    }
  }
}
