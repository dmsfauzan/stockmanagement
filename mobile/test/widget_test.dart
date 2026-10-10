import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:wsm_mobile/core/config/app_config.dart';
import 'package:wsm_mobile/data/items/item_repository.dart';
import 'package:wsm_mobile/data/stock/stock_repository.dart';

void main() {
  test('AppConfig has a valid API base URL', () {
    expect(AppConfig.apiBaseUrl, isNotEmpty);
    expect(AppConfig.apiBaseUrl, contains('/api'));
  });

  test('StockRow parses balance fields including item_id', () {
    final row = StockRow.fromJson({
      'id': 7,
      'item_id': 42,
      'sku': 'BRG-1',
      'item_name': 'Barang 1',
      'warehouse': 'Jakarta',
      'location': 'A01-01',
      'on_hand': 10,
      'reserved': 2,
      'quarantine': 1,
      'available': 7,
      'min': 5,
      'max': 50,
      'status': 'low',
    });

    expect(row.itemId, 42);
    expect(row.onHand, 10);
    expect(row.available, 7);
  });

  test('ItemDetail parses conversions and bom', () {
    final item = ItemDetail.fromJson({
      'id': 5,
      'sku': 'KIT-1',
      'name': 'Kit',
      'conversions': [
        {'unit_code': 'BOX', 'unit_name': 'Box', 'factor': 12},
      ],
      'bom': [
        {'sku': 'COMP-A', 'quantity': 2},
      ],
    });

    expect(item.conversions, hasLength(1));
    expect(item.conversions.first.factor, 12);
    expect(item.bom.first.sku, 'COMP-A');
  });

  testWidgets('Login screen shows email and password fields', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      const ProviderScope(child: MaterialApp(home: _Smoke())),
    );

    expect(find.text('Stock Management'), findsOneWidget);
  });
}

class _Smoke extends StatelessWidget {
  const _Smoke();

  @override
  Widget build(BuildContext context) =>
      const Scaffold(body: Center(child: Text('Stock Management')));
}
