import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http_mock_adapter/http_mock_adapter.dart';
import 'package:wsm_mobile/core/network/api_exception.dart';
import 'package:wsm_mobile/data/transactions/transaction_models.dart';
import 'package:wsm_mobile/data/transactions/transaction_repository.dart';

void main() {
  late Dio dio;
  late DioAdapter adapter;
  late TransactionRepository repo;

  setUp(() {
    dio = Dio(BaseOptions(baseUrl: 'http://test/api'));
    adapter = DioAdapter(dio: dio);
    repo = TransactionRepository(dio);
  });

  test('list parses rows and pagination', () async {
    adapter.onGet(
      '/goods-receipts',
      (server) => server.reply(200, {
        'success': true,
        'data': [
          {
            'id': 1,
            'number': 'GR-1',
            'transaction_date': '2026-10-10',
            'supplier': {'id': 1, 'name': 'Supplier A'},
            'warehouse': {'id': 1, 'name': 'Jakarta'},
            'status': 'draft',
          },
        ],
        'meta': {'current_page': 1, 'per_page': 20, 'total': 1, 'last_page': 1},
      }),
    );

    final result = await repo.list(TxnType.receipt);

    expect(result.items, hasLength(1));
    expect(result.items.first.number, 'GR-1');
    expect(result.items.first.party, 'Supplier A');
    expect(result.pagination.hasMore, isFalse);
  });

  test('show parses detail lines', () async {
    adapter.onGet(
      '/goods-receipts/5',
      (server) => server.reply(200, {
        'success': true,
        'data': {
          'id': 5,
          'number': 'GR-5',
          'status': 'posted',
          'transaction_date': '2026-10-10',
          'supplier': {'id': 1, 'name': 'Supplier A'},
          'warehouse': {'id': 1, 'name': 'Jakarta'},
          'items': [
            {
              'id': 1,
              'item_id': 9,
              'quantity': 4,
              'unit_code': 'PCS',
              'location_code': 'A01',
            },
          ],
        },
      }),
    );

    final detail = await repo.show(TxnType.receipt, 5);

    expect(detail.number, 'GR-5');
    expect(detail.lines, hasLength(1));
    expect(detail.lines.first.unitCode, 'PCS');
  });

  test('list throws ApiException on failure', () async {
    adapter.onGet(
      '/goods-receipts',
      (server) => server.reply(403, {'message': 'Forbidden'}),
    );

    expect(() => repo.list(TxnType.receipt), throwsA(isA<ApiException>()));
  });
}
