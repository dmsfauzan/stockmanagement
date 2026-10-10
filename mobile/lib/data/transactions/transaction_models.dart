/// Jenis dokumen transaksi yang didukung mobile.
enum TxnType {
  receipt,
  issue,
  adjustment,
  opname,
  transfer,
  purchaseOrder,
  salesOrder,
  customerReturn,
  supplierReturn,
}

extension TxnTypeX on TxnType {
  String get key => switch (this) {
    TxnType.receipt => 'receipts',
    TxnType.issue => 'issues',
    TxnType.adjustment => 'adjustments',
    TxnType.opname => 'opnames',
    TxnType.transfer => 'transfers',
    TxnType.purchaseOrder => 'purchase-orders',
    TxnType.salesOrder => 'sales-orders',
    TxnType.customerReturn => 'customer-returns',
    TxnType.supplierReturn => 'supplier-returns',
  };

  String get label => switch (this) {
    TxnType.receipt => 'Barang Masuk',
    TxnType.issue => 'Barang Keluar',
    TxnType.adjustment => 'Adjustment',
    TxnType.opname => 'Opname',
    TxnType.transfer => 'Transfer',
    TxnType.purchaseOrder => 'Purchase Order',
    TxnType.salesOrder => 'Sales Order',
    TxnType.customerReturn => 'Retur Penjualan',
    TxnType.supplierReturn => 'Retur Pembelian',
  };

  String get listPath => switch (this) {
    TxnType.receipt => '/goods-receipts',
    TxnType.issue => '/goods-issues',
    TxnType.adjustment => '/stock-adjustments',
    TxnType.opname => '/stock-opnames',
    TxnType.transfer => '/stock-transfers',
    TxnType.purchaseOrder => '/purchase-orders',
    TxnType.salesOrder => '/sales-orders',
    TxnType.customerReturn => '/customer-returns',
    TxnType.supplierReturn => '/supplier-returns',
  };

  String detailPath(int id) => '$listPath/$id';
}

class TxnListRow {
  const TxnListRow({
    required this.id,
    required this.number,
    this.date,
    this.party,
    this.warehouse,
    required this.status,
    this.itemCount = 0,
  });

  final int id;
  final String number;
  final String? date;
  final String? party;
  final String? warehouse;
  final String status;
  final int itemCount;

  factory TxnListRow.fromJson(Map<String, dynamic> j) => TxnListRow(
    id: (j['id'] as num).toInt(),
    number: '${j['number'] ?? ''}',
    date:
        (j['transaction_date'] ??
                j['transfer_date'] ??
                j['opname_date'] ??
                j['order_date'])
            as String?,
    party:
        (j['supplier'] as Map<String, dynamic>?)?['name'] as String? ??
        (j['customer'] as Map<String, dynamic>?)?['name'] as String?,
    warehouse:
        (j['warehouse'] as Map<String, dynamic>?)?['name'] as String? ??
        (j['from_warehouse'] as Map<String, dynamic>?)?['name'] as String?,
    status: '${j['status'] ?? ''}',
  );
}

class TxnLineItem {
  const TxnLineItem({
    required this.id,
    this.itemId,
    this.sku,
    this.itemName,
    this.quantity = 0,
    this.remaining,
    this.unitCost,
    this.unitCode,
    this.locationCode,
    this.batchNumber,
    this.expiryDate,
    this.systemQuantity,
    this.actualQuantity,
    this.physicalQuantity,
    this.difference,
    this.reason,
    this.notes,
  });

  final int id;
  final int? itemId;
  final String? sku;
  final String? itemName;
  final int quantity;
  final int? remaining;
  final dynamic unitCost;
  final String? unitCode;
  final String? locationCode;
  final String? batchNumber;
  final String? expiryDate;
  final int? systemQuantity;
  final int? actualQuantity;
  final int? physicalQuantity;
  final int? difference;
  final String? reason;
  final String? notes;

  factory TxnLineItem.fromJson(Map<String, dynamic> j) => TxnLineItem(
    id: (j['id'] as num?)?.toInt() ?? 0,
    itemId: (j['item_id'] as num?)?.toInt(),
    sku: j['sku'] as String?,
    itemName: j['item_name'] as String?,
    quantity: (j['quantity'] as num?)?.toInt() ?? 0,
    remaining: (j['remaining'] as num?)?.toInt(),
    unitCost: j['unit_cost'] ?? j['unit_price'],
    unitCode: (j['unit_code'] ?? j['unit']) as String?,
    locationCode: (j['location_code'] ?? j['location']) as String?,
    batchNumber: j['batch_number'] as String?,
    expiryDate: j['expiry_date'] as String?,
    systemQuantity: (j['system_quantity'] as num?)?.toInt(),
    actualQuantity: (j['actual_quantity'] as num?)?.toInt(),
    physicalQuantity: (j['physical_quantity'] as num?)?.toInt(),
    difference: (j['difference'] as num?)?.toInt(),
    reason: j['reason'] as String?,
    notes: j['notes'] as String?,
  );
}

TxnType? txnTypeFromKey(String? key) {
  for (final type in TxnType.values) {
    if (type.key == key) return type;
  }

  return null;
}

/// Detail dokumen transaksi (header + baris).
class TxnDetail {
  const TxnDetail({
    required this.id,
    required this.number,
    required this.status,
    this.date,
    this.party,
    this.partyLabel,
    this.warehouse,
    this.destination,
    this.reference,
    this.reason,
    this.requiresInspection = false,
    this.notes,
    this.lines = const [],
  });

  final int id;
  final String number;
  final String status;
  final String? date;
  final String? party;
  final String? partyLabel;
  final String? warehouse;
  final String? destination;
  final String? reference;
  final String? reason;
  final bool requiresInspection;
  final String? notes;
  final List<TxnLineItem> lines;

  factory TxnDetail.receipt(Map<String, dynamic> j) => TxnDetail(
    id: (j['id'] as num).toInt(),
    number: '${j['number'] ?? ''}',
    status: '${j['status'] ?? ''}',
    date: j['transaction_date'] as String?,
    party: (j['supplier'] as Map<String, dynamic>?)?['name'] as String?,
    partyLabel: 'Supplier',
    warehouse: (j['warehouse'] as Map<String, dynamic>?)?['name'] as String?,
    destination: j['po_number'] as String?,
    reference: j['delivery_note'] as String?,
    requiresInspection: j['requires_inspection'] == true,
    notes: j['notes'] as String?,
    lines: ((j['items'] as List?) ?? [])
        .map((e) => TxnLineItem.fromJson(e as Map<String, dynamic>))
        .toList(),
  );

  factory TxnDetail.issue(Map<String, dynamic> j) => TxnDetail(
    id: (j['id'] as num).toInt(),
    number: '${j['number'] ?? ''}',
    status: '${j['status'] ?? ''}',
    date: j['transaction_date'] as String?,
    party: (j['customer'] as Map<String, dynamic>?)?['name'] as String?,
    partyLabel: 'Customer',
    warehouse: (j['warehouse'] as Map<String, dynamic>?)?['name'] as String?,
    destination: j['destination'] as String?,
    reference: j['sales_order_number'] as String?,
    notes: j['notes'] as String?,
    lines: ((j['items'] as List?) ?? [])
        .map((e) => TxnLineItem.fromJson(e as Map<String, dynamic>))
        .toList(),
  );

  factory TxnDetail.adjustment(Map<String, dynamic> j) => TxnDetail(
    id: (j['id'] as num).toInt(),
    number: '${j['number'] ?? ''}',
    status: '${j['status'] ?? ''}',
    date: j['transaction_date'] as String?,
    warehouse: (j['warehouse'] as Map<String, dynamic>?)?['name'] as String?,
    reason: j['reason'] as String?,
    notes: j['notes'] as String?,
    lines: ((j['items'] as List?) ?? [])
        .map((e) => TxnLineItem.fromJson(e as Map<String, dynamic>))
        .toList(),
  );

  factory TxnDetail.opname(Map<String, dynamic> j) => TxnDetail(
    id: (j['id'] as num).toInt(),
    number: '${j['number'] ?? ''}',
    status: '${j['status'] ?? ''}',
    date: j['opname_date'] as String?,
    warehouse: (j['warehouse'] as Map<String, dynamic>?)?['name'] as String?,
    reason: (j['location'] as Map<String, dynamic>?)?['code'] as String?,
    notes: j['notes'] as String?,
    lines: ((j['items'] as List?) ?? [])
        .map((e) => TxnLineItem.fromJson(e as Map<String, dynamic>))
        .toList(),
  );

  factory TxnDetail.transfer(Map<String, dynamic> j) => TxnDetail(
    id: (j['id'] as num).toInt(),
    number: '${j['number'] ?? ''}',
    status: '${j['status'] ?? ''}',
    date: j['transfer_date'] as String?,
    warehouse:
        '${(j['from_warehouse'] as Map<String, dynamic>?)?['name'] ?? '-'} → ${(j['to_warehouse'] as Map<String, dynamic>?)?['name'] ?? '-'}',
    notes: j['notes'] as String?,
    lines: ((j['items'] as List?) ?? [])
        .map((e) => TxnLineItem.fromJson(e as Map<String, dynamic>))
        .toList(),
  );

  factory TxnDetail.purchaseOrder(Map<String, dynamic> j) => TxnDetail(
    id: (j['id'] as num).toInt(),
    number: '${j['number'] ?? ''}',
    status: '${j['status'] ?? ''}',
    date: j['order_date'] as String?,
    party: (j['supplier'] as Map<String, dynamic>?)?['name'] as String?,
    partyLabel: 'Supplier',
    warehouse: (j['warehouse'] as Map<String, dynamic>?)?['name'] as String?,
    notes: j['notes'] as String?,
    lines: ((j['items'] as List?) ?? [])
        .map((e) => TxnLineItem.fromJson(e as Map<String, dynamic>))
        .toList(),
  );

  factory TxnDetail.salesOrder(Map<String, dynamic> j) => TxnDetail(
    id: (j['id'] as num).toInt(),
    number: '${j['number'] ?? ''}',
    status: '${j['status'] ?? ''}',
    date: j['order_date'] as String?,
    party: (j['customer'] as Map<String, dynamic>?)?['name'] as String?,
    partyLabel: 'Customer',
    warehouse: (j['warehouse'] as Map<String, dynamic>?)?['name'] as String?,
    notes: j['notes'] as String?,
    lines: ((j['items'] as List?) ?? [])
        .map((e) => TxnLineItem.fromJson(e as Map<String, dynamic>))
        .toList(),
  );

  factory TxnDetail.customerReturn(Map<String, dynamic> j) =>
      _returnDetail(j, partyLabel: 'Customer', customer: true);

  factory TxnDetail.supplierReturn(Map<String, dynamic> j) =>
      _returnDetail(j, partyLabel: 'Supplier', customer: false);

  static String? _partyName(Map<String, dynamic> j, String key) {
    final value = j[key];

    return value is Map ? value['name'] as String? : null;
  }

  static TxnDetail _returnDetail(
    Map<String, dynamic> j, {
    required String partyLabel,
    required bool customer,
  }) {
    final party =
        (j['party'] as String?) ??
        (customer ? _partyName(j, 'customer') : _partyName(j, 'supplier'));

    return TxnDetail(
      id: (j['id'] as num).toInt(),
      number: '${j['number'] ?? ''}',
      status: '${j['status'] ?? ''}',
      date: j['transaction_date'] as String?,
      party: party,
      partyLabel: partyLabel,
      warehouse: j['warehouse'] is Map
          ? (j['warehouse'] as Map<String, dynamic>)['name'] as String?
          : j['warehouse'] as String?,
      reason: j['reason'] as String?,
      notes: j['notes'] as String?,
      lines: ((j['items'] as List?) ?? [])
          .map((e) => TxnLineItem.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}

/// Alur workflow per tipe: urutan status + aksi yang valid per status.
class TxnWorkflow {
  const TxnWorkflow({required this.steps, required this.actions});

  /// Urutan status.
  final List<String> steps;

  /// status → aksi yang tersedia (verb API).
  final Map<String, List<String>> actions;

  static TxnWorkflow of(TxnType type) {
    return switch (type) {
      TxnType.receipt || TxnType.issue => const TxnWorkflow(
        steps: ['draft', 'submitted', 'approved', 'posted'],
        actions: {
          'draft': ['submit'],
          'submitted': ['approve', 'reject'],
          'approved': ['post'],
        },
      ),
      TxnType.adjustment => const TxnWorkflow(
        steps: ['draft', 'submitted', 'approved', 'posted'],
        actions: {
          'draft': ['submit'],
          'submitted': ['approve', 'reject'],
          'approved': ['post'],
        },
      ),
      TxnType.opname => const TxnWorkflow(
        steps: ['draft', 'counting', 'submitted', 'approved'],
        actions: {
          'draft': ['start'],
          'counting': ['submit'],
          'submitted': ['approve', 'reject'],
        },
      ),
      TxnType.transfer => const TxnWorkflow(
        steps: ['requested', 'approved', 'in_transit', 'received', 'completed'],
        actions: {
          'draft': ['request'],
          'requested': ['approve', 'reject'],
          'approved': ['dispatch'],
          'in_transit': ['receive'],
          'received': ['complete'],
        },
      ),
      TxnType.purchaseOrder => const TxnWorkflow(
        steps: ['draft', 'submitted', 'approved', 'received', 'closed'],
        actions: {
          'draft': ['submit'],
          'submitted': ['approve', 'reject'],
          'approved': ['close'],
          'partial': ['close'],
          'received': ['close'],
        },
      ),
      TxnType.salesOrder => const TxnWorkflow(
        steps: ['draft', 'submitted', 'approved', 'fulfilled', 'closed'],
        actions: {
          'draft': ['submit'],
          'submitted': ['approve', 'reject'],
          'approved': ['close'],
          'partial': ['close'],
          'fulfilled': ['close'],
        },
      ),
      TxnType.customerReturn || TxnType.supplierReturn => const TxnWorkflow(
        steps: ['draft', 'submitted', 'approved', 'posted'],
        actions: {
          'draft': ['submit'],
          'submitted': ['approve', 'reject'],
          'approved': ['post'],
        },
      ),
    };
  }

  int stepIndex(String status) {
    final i = steps.indexOf(status);
    return i < 0 ? 0 : i;
  }
}
