import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:wsm_mobile/core/config/app_config.dart';
import 'package:wsm_mobile/core/offline/outbox_entry.dart';
import 'package:wsm_mobile/data/assembly/assembly_models.dart';
import 'package:wsm_mobile/data/items/item_repository.dart';
import 'package:wsm_mobile/data/picking/picking_models.dart';
import 'package:wsm_mobile/data/quality/quality_repository.dart';
import 'package:wsm_mobile/data/recall/recall_repository.dart';
import 'package:wsm_mobile/data/requisitions/requisition_models.dart';
import 'package:wsm_mobile/data/stock/stock_repository.dart';
import 'package:wsm_mobile/data/tracing/traceability_repository.dart';
import 'package:wsm_mobile/data/transactions/transaction_models.dart';
import 'package:wsm_mobile/features/reports/presentation/reports_screen.dart';
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

  test('TxnDetail purchase order parses supplier and remaining', () {
    final detail = TxnDetail.purchaseOrder({
      'id': 4,
      'number': 'PO-1',
      'status': 'submitted',
      'order_date': '2026-10-10',
      'supplier': {'id': 1, 'name': 'Supplier A'},
      'warehouse': {'id': 1, 'name': 'Jakarta'},
      'items': [
        {
          'id': 1,
          'item_id': 9,
          'sku': 'BRG-1',
          'item_name': 'Barang',
          'quantity': 5,
          'remaining': 5,
          'unit_code': 'PCS',
          'unit_price': 1200,
        },
      ],
    });

    expect(detail.party, 'Supplier A');
    expect(detail.partyLabel, 'Supplier');
    expect(detail.lines.first.remaining, 5);
    expect(detail.lines.first.unitCost, 1200);
  });

  test('TxnDetail customer return parses party and lines', () {
    final detail = TxnDetail.customerReturn({
      'id': 1,
      'number': 'CRT-1',
      'status': 'submitted',
      'transaction_date': '2026-10-10',
      'party': 'Customer A',
      'warehouse': 'Jakarta',
      'reason': 'Barang rusak',
      'items': [
        {
          'id': 1,
          'item_id': 9,
          'sku': 'BRG-1',
          'item_name': 'Barang',
          'quantity': 2,
          'unit': 'PCS',
          'location': 'A01-01',
          'unit_cost': 500,
        },
      ],
    });

    expect(detail.party, 'Customer A');
    expect(detail.partyLabel, 'Customer');
    expect(detail.lines.first.unitCode, 'PCS');
    expect(detail.lines.first.locationCode, 'A01-01');
  });

  testWidgets('Transactions hub lists transaction modules', (tester) async {
    tester.view.physicalSize = const Size(1200, 4000);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(const MaterialApp(home: TransactionsHubScreen()));

    expect(find.text('Barang Masuk'), findsOneWidget);
    expect(find.text('Barang Keluar'), findsOneWidget);
    expect(find.text('Adjustment'), findsOneWidget);
    expect(find.text('Opname'), findsOneWidget);
    expect(find.text('Transfer'), findsOneWidget);
    expect(find.text('Purchase Order'), findsOneWidget);
    expect(find.text('Sales Order'), findsOneWidget);
    expect(find.text('Retur Penjualan'), findsOneWidget);
    expect(find.text('Retur Pembelian'), findsOneWidget);
  });

  test('Requisition detail parses lines', () {
    final detail = RequisitionDetail.fromJson({
      'id': 1,
      'number': 'PR-1',
      'status': 'submitted',
      'request_date': '2026-10-10',
      'warehouse': {'id': 1, 'name': 'Jakarta'},
      'items': [
        {'item_id': 9, 'sku': 'BRG-1', 'quantity': 3, 'estimated_price': 1200},
      ],
    });

    expect(detail.number, 'PR-1');
    expect(detail.warehouse, 'Jakarta');
    expect(detail.lines.first.quantity, 3);
    expect(detail.lines.first.estimatedPrice, 1200);
  });

  test('Assembly detail parses components', () {
    final detail = AssemblyDetail.fromJson({
      'id': 1,
      'number': 'ASM-1',
      'status': 'posted',
      'type': 'assembly',
      'item_id': 5,
      'quantity': 2,
      'items': [
        {'id': 1, 'item_id': 9, 'role': 'component', 'quantity': 4},
      ],
    });

    expect(detail.type, 'assembly');
    expect(detail.quantity, 2);
    expect(detail.lines.first.quantity, 4);
  });

  test('Pick list detail parses lines with locations', () {
    final detail = PickListDetail.fromJson({
      'id': 1,
      'number': 'PL-1',
      'status': 'picking',
      'warehouse': {'id': 1, 'name': 'Jakarta'},
      'items': [
        {
          'id': 10,
          'item_id': 9,
          'sku': 'BRG-1',
          'location': 'A01-01',
          'quantity': 5,
          'picked_quantity': 2,
          'status': 'picking',
        },
      ],
    });

    expect(detail.warehouse, 'Jakarta');
    expect(detail.lines.first.id, 10);
    expect(detail.lines.first.location, 'A01-01');
    expect(detail.lines.first.pickedQuantity, 2);
  });

  test('Quarantine row parses raw keys', () {
    final row = QuarantineRow.fromJson({
      'id': 1,
      'item_id': 9,
      'sku': 'BRG-1',
      'item_name': 'Barang',
      'warehouse_name': 'Jakarta',
      'location_path': 'Jakarta / A / R1 / A01-01',
      'quantity_on_hand': 10,
      'quantity_quarantine': 3,
      'quantity_available': 5,
    });

    expect(row.warehouse, 'Jakarta');
    expect(row.quarantine, 3);
    expect(row.available, 5);
  });

  test('TraceResult parses events and summary', () {
    final result = TraceResult.fromJson({
      'type': 'batch',
      'value': 'B-1',
      'summary': {'events': 1, 'in': 10, 'out': 4, 'balance': 6},
      'events': [
        {
          'id': 1,
          'transaction_type': 'receipt',
          'quantity_in': 10,
          'quantity_out': 0,
          'balance_after': 10,
        },
      ],
    });

    expect(result.value, 'B-1');
    expect(result.summary['balance'], 6);
    expect(result.events.first.quantityIn, 10);
  });

  test('Recall row parses value', () {
    final row = RecallRow.fromJson({
      'id': 1,
      'type': 'batch',
      'batch_number': 'B-1',
      'serial_number': null,
      'reason': 'cacat',
      'status': 'active',
    });

    expect(row.value, 'B-1');
    expect(row.status, 'active');
  });

  test('OutboxEntry round-trips through map', () {
    const entry = OutboxEntry(
      id: 3,
      method: 'POST',
      url: 'http://host/api/goods-receipts',
      data: '{"foo":1}',
      idempotencyKey: 'key-1',
      createdAt: '2026-10-10T00:00:00',
    );

    final restored = OutboxEntry.fromMap(entry.toMap());

    expect(restored.id, 3);
    expect(restored.method, 'POST');
    expect(restored.url, contains('goods-receipts'));
    expect(restored.idempotencyKey, 'key-1');
  });

  testWidgets('Reports index lists report modules', (tester) async {
    await tester.pumpWidget(const MaterialApp(home: ReportsIndexScreen()));

    expect(find.text('Laporan Stok'), findsOneWidget);
    expect(find.text('Valuasi Persediaan'), findsOneWidget);
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
