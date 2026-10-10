/// Jenis dokumen transaksi yang didukung mobile.
enum TxnType { receipt, issue, adjustment, opname, transfer }

extension TxnTypeX on TxnType {
  String get key => switch (this) {
    TxnType.receipt => 'receipts',
    TxnType.issue => 'issues',
    TxnType.adjustment => 'adjustments',
    TxnType.opname => 'opnames',
    TxnType.transfer => 'transfers',
  };

  String get label => switch (this) {
    TxnType.receipt => 'Barang Masuk',
    TxnType.issue => 'Barang Keluar',
    TxnType.adjustment => 'Adjustment',
    TxnType.opname => 'Opname',
    TxnType.transfer => 'Transfer',
  };

  String get listPath => switch (this) {
    TxnType.receipt => '/goods-receipts',
    TxnType.issue => '/goods-issues',
    TxnType.adjustment => '/stock-adjustments',
    TxnType.opname => '/stock-opnames',
    TxnType.transfer => '/stock-transfers',
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
        (j['transaction_date'] ?? j['transfer_date'] ?? j['opname_date'])
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
    unitCost: j['unit_cost'],
    unitCode: j['unit_code'] as String?,
    locationCode: j['location_code'] as String?,
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
    };
  }

  int stepIndex(String status) {
    final i = steps.indexOf(status);
    return i < 0 ? 0 : i;
  }
}
