import 'package:dio/dio.dart';

import '../../core/network/api_exception.dart';

class ToolsRepository {
  ToolsRepository(this._dio);

  final Dio _dio;

  Future<Map<String, dynamic>> convert({
    required double amount,
    required String from,
    required String to,
    String? date,
  }) async {
    try {
      final res = await _dio.get(
        '/currency/convert',
        queryParameters: {
          'amount': amount,
          'from': from,
          'to': to,
          if (date != null && date.isNotEmpty) 'date': date,
        },
      );

      return ((res.data as Map<String, dynamic>)['data'] ?? {})
          as Map<String, dynamic>;
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal mengonversi mata uang.');
    }
  }

  Future<dynamic> putAwaySuggestion({
    required int warehouseId,
    required int itemId,
    required int quantity,
  }) async {
    try {
      final res = await _dio.get(
        '/put-away-suggestion',
        queryParameters: {
          'warehouse_id': warehouseId,
          'item_id': itemId,
          'quantity': quantity,
        },
      );

      return (res.data as Map<String, dynamic>)['data'];
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memuat saran put-away.');
    }
  }
}
