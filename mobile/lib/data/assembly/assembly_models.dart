class AssemblyRow {
  const AssemblyRow({
    required this.id,
    required this.number,
    this.type,
    this.sku,
    this.quantity = 0,
    this.warehouse,
    required this.status,
  });

  final int id;
  final String number;
  final String? type;
  final String? sku;
  final int quantity;
  final String? warehouse;
  final String status;

  factory AssemblyRow.fromJson(Map<String, dynamic> j) => AssemblyRow(
    id: (j['id'] as num).toInt(),
    number: '${j['number'] ?? ''}',
    type: j['type'] as String?,
    sku: j['item'] as String?,
    quantity: (j['quantity'] as num?)?.toInt() ?? 0,
    warehouse:
        (j['warehouse'] as Map<String, dynamic>?)?['name'] as String? ??
        j['warehouse'] as String?,
    status: '${j['status'] ?? ''}',
  );
}

class AssemblyLine {
  const AssemblyLine({
    required this.id,
    this.itemId,
    this.role,
    this.quantity = 0,
  });

  final int id;
  final int? itemId;
  final String? role;
  final int quantity;

  factory AssemblyLine.fromJson(Map<String, dynamic> j) => AssemblyLine(
    id: (j['id'] as num?)?.toInt() ?? 0,
    itemId: (j['item_id'] as num?)?.toInt(),
    role: j['role'] as String?,
    quantity: (j['quantity'] as num?)?.toInt() ?? 0,
  );
}

class AssemblyDetail {
  const AssemblyDetail({
    required this.id,
    required this.number,
    required this.status,
    this.type,
    this.itemId,
    this.quantity = 0,
    this.warehouseId,
    this.locationId,
    this.lines = const [],
  });

  final int id;
  final String number;
  final String status;
  final String? type;
  final int? itemId;
  final int quantity;
  final int? warehouseId;
  final int? locationId;
  final List<AssemblyLine> lines;

  factory AssemblyDetail.fromJson(Map<String, dynamic> j) => AssemblyDetail(
    id: (j['id'] as num).toInt(),
    number: '${j['number'] ?? ''}',
    status: '${j['status'] ?? ''}',
    type: j['type'] as String?,
    itemId: (j['item_id'] as num?)?.toInt(),
    quantity: (j['quantity'] as num?)?.toInt() ?? 0,
    warehouseId: (j['warehouse_id'] as num?)?.toInt(),
    locationId: (j['location_id'] as num?)?.toInt(),
    lines: ((j['items'] as List?) ?? [])
        .map((e) => AssemblyLine.fromJson(e as Map<String, dynamic>))
        .toList(),
  );
}
