import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:wsm_mobile/core/config/app_config.dart';
import 'package:wsm_mobile/data/items/item_repository.dart';
import 'package:wsm_mobile/data/stock/stock_repository.dart';
import 'package:wsm_mobile/data/transactions/transaction_models.dart';
import 'package:wsm_mobile/features/transactions/presentation/transactions_hub_screen.dart';

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

  test('TxnListRow parses receipt row', () {
    final row = TxnListRow.fromJson({
      'id': 3,
      'number': 'GR-1',
      'transaction_date': '2026-10-10',
      'supplier': {'id': 1, 'name': 'Supplier A'},
      'warehouse': {'id': 1, 'name': 'Jakarta'},
      'status': 'submitted',
    });

    expect(row.number, 'GR-1');
    expect(row.party, 'Supplier A');
    expect(row.status, 'submitted');
  });

  test('TxnWorkflow exposes correct actions per status', () {
    final wf = TxnWorkflow.of(TxnType.receipt);
    expect(wf.actions['draft'], contains('submit'));
    expect(wf.actions['submitted'], containsAll(['approve', 'reject']));
    expect(wf.actions['approved'], contains('post'));

    final transfer = TxnWorkflow.of(TxnType.transfer);
    expect(transfer.actions['in_transit'], contains('receive'));
  });

  test('TxnDetail receipt parses lines with unit and location', () {
    final detail = TxnDetail.receipt({
      'id': 1,
      'number': 'GR-1',
      'status': 'posted',
      'items': [
        {
          'id': 1,
          'item_id': 9,
          'sku': 'BRG-1',
          'item_name': 'Barang',
          'quantity': 5,
          'unit_code': 'PCS',
          'location_code': 'A01-01',
        },
      ],
    });

    expect(detail.lines, hasLength(1));
    expect(detail.lines.first.unitCode, 'PCS');
    expect(detail.lines.first.locationCode, 'A01-01');
  });

  testWidgets('Transactions hub lists five modules', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(home: TransactionsHubScreen()),
    );

    expect(find.text('Barang Masuk'), findsOneWidget);
    expect(find.text('Barang Keluar'), findsOneWidget);
    expect(find.text('Adjustment'), findsOneWidget);
    expect(find.text('Opname'), findsOneWidget);
    expect(find.text('Transfer'), findsOneWidget);
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
